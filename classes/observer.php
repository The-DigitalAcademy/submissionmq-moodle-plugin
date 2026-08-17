<?php
namespace local_submissionmq;

defined('MOODLE_INTERNAL') || die();

use local_submissionmq\helpers\rubric_helper;
use local_submissionmq\helpers\tag_helper;
use local_submissionmq\helpers\rabbitmq_helper;
use local_submissionmq\helpers\quiz_helper;

/**
 * Observer class for handling Moodle events related to submissions.
 *
 * Listens for assignment submission events and quiz attempt submission
 * events, and pushes submission data to configured RabbitMQ queues if
 * the relevant activity has a tag matching the configured prefix.
 *
 * @package   local_submissionmq
 * @category  event
 */
class observer {

    /**
     * Triggered when an assignment is submitted.
     *
     * This event handler gathers all relevant submission data including
     * online text, assignment info, and grading rubric. It checks if the
     * assignment has any tags matching the configured message queue prefix.
     * If so, it sends the data to RabbitMQ.
     *
     * @param \mod_assign\event\assessable_submitted $event The event object.
     * @return bool Always returns true for Moodle event handlers.
     */
    public static function assignment_submitted(\mod_assign\event\assessable_submitted $event) {
        global $DB;

         // Retrieve Basic event data
        $event_data = $event->get_data();

        // Fetch the submission record
        $submission = $DB->get_record('assign_submission', [
            'id' => $event_data['objectid'],
        ]);

        if (!$submission) {
            debugging("Submission record not found for ID {$event_data['objectid']}", DEBUG_DEVELOPER);
            return true; // Exit silently if submission does not exist
        }

        // Get configured tag prefix to filter queues
        $substring = get_config('local_submissionmq', 'tag_prefix');

        // Fetch course module tags matching the prefix
        $queues = tag_helper::get_course_module_tags_containing($event_data['contextinstanceid'], substring: $substring);

        if (empty($queues)) {
            return true; // skip silently
        }

        // Fetch online text submission (if it exists)
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'submission' => $event_data['objectid']
        ]);

        // Fetch the assignment instance
        $assignment = $DB->get_record('assign', [
            'id' => $submission->assignment
        ]);

        // Fetch grading rubric (if configured)
        $rubric = rubric_helper::get_rubric_for_assignment($event_data['contextid']);

        // Build payload to send to RabbitMQ
        $payload = [
            'onlinetextid' => $onlinetext->id ?? null,
            'submissionid' => $onlinetext->submission  ?? null,
            'onlinetext' => $onlinetext->onlinetext  ?? null,
            'userid' => $submission->userid,
            'status' => $submission->status,
            'courseid' => $event_data['courseid'],
            'cmid' => $event_data['contextinstanceid'],
            'assignmentid' => $onlinetext->assignment  ?? $submission->assignment,
            'assignmentname' => $assignment->name ?? '',
            'assignmentintro' => $assignment->intro ?? '',
            'assignmentactivity' => $assignment->activity ?? '',
            'assignmentgrade' => $assignment->grade ?? 0,
            'timecreated' => $submission->timecreated,
            'assignmentrubric' => $rubric,
        ];

        try {
            // Convert payload to JSON
            $jsondata = json_encode($payload);

            // Send message to RabbitMQ queues
            rabbitmq_helper::send_message($queues, $jsondata);
        } catch (\Throwable $th) {
            debugging("Failed to send submission to queue. {$th->getMessage()}", DEBUG_DEVELOPER);
        }

        return true; // Always return true for event handlers
    }

    /**
     * Triggered when a quiz attempt is submitted.
     *
     * Extracts any essay-type question responses from the attempt and,
     * if the quiz activity is tagged with the configured prefix, sends
     * one message per essay question to the RabbitMQ queues.
     *
     * Payload shape matches the existing grading_jobs job format used by
     * moodle_quiz_producer.py / worker.py (a "submission" object plus
     * top-level course_name/assignment_name/subject_area), so a tagged
     * quiz's essay responses can be consumed by the same worker without
     * any changes on that side.
     *
     * @param \mod_quiz\event\attempt_submitted $event The event object.
     * @return bool Always returns true for Moodle event handlers.
     */
    public static function quiz_attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        // Retrieve Basic event data
        $event_data = $event->get_data();

        // Fetch the quiz attempt record
        $attempt = $DB->get_record('quiz_attempts', [
            'id' => $event_data['objectid'],
        ]);

        if (!$attempt) {
            debugging("Quiz attempt record not found for ID {$event_data['objectid']}", DEBUG_DEVELOPER);
            return true; // Exit silently if the attempt does not exist
        }

        // Skip preview attempts - these are not real student submissions.
        if (!empty($attempt->preview)) {
            return true;
        }

        // Get configured tag prefix to filter queues
        $substring = get_config('local_submissionmq', 'tag_prefix');

        // Fetch course module tags matching the prefix (tag lives on the quiz activity)
        $queues = tag_helper::get_course_module_tags_containing($event_data['contextinstanceid'], substring: $substring);

        if (empty($queues)) {
            return true; // skip silently
        }

        // Pull out only the essay-type question responses from this attempt.
        $essays = quiz_helper::get_essay_responses($event_data['objectid']);

        if (empty($essays)) {
            return true; // No essay questions in this quiz/attempt, nothing to grade.
        }

        // Fetch the quiz instance and course, needed for course_name/assignment_name.
        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        $course = $DB->get_record('course', ['id' => $event_data['courseid']]);

        // Look up the learner's idnumber - worker.py identifies learners by
        // learner_id in its logs/output, preferring idnumber where set. If
        // idnumber isn't populated (common for accounts not yet provisioned
        // through the idnumber-population step), fall back to the raw Moodle
        // userid rather than silently dropping the submission - a missing
        // profile field shouldn't mean a real student's quiz never gets graded.
        $user = $DB->get_record('user', ['id' => $attempt->userid], 'id, idnumber');
        $learnerid = !empty($user->idnumber) ? $user->idnumber : (string) $attempt->userid;

        if (empty($user->idnumber)) {
            debugging("No idnumber set for userid {$attempt->userid} — falling back to raw userid as learner_id.", DEBUG_DEVELOPER);
        }

        // Non-essay (objective) question marks for this attempt, to be added
        // to the essay total later once all essays are graded — mirrors
        // moodle_client.get_objective_score_from_attempt() on the Python side.
        $objectivescore = quiz_helper::get_objective_score($event_data['objectid']);

        // Build and send one payload per essay question in the attempt.
        foreach ($essays as $index => $essay) {
            $questionnumber = $index + 1; // 1-based, for the "— Q1" style label below

            $submission = [
                'learner_id' => $learnerid,
                'question_text' => $essay['questiontext'],
                'learner_response' => $essay['responsetext'],
                'max_grade' => $essay['maxmark'],
                // Real grading guide pulled from Moodle's own "Information for
                // graders" field on the essay question (see quiz_helper.php).
                'grading_guide' => $essay['gradingguide'],
                // Internal Moodle fields for reference/audit - same keys as
                // moodle_quiz_producer.py's job payload.
                '_moodle_userid' => (int) $attempt->userid,
                '_moodle_quiz_id' => (int) $attempt->quiz,
                '_moodle_attempt_id' => (int) $attempt->id,
                '_moodle_slot' => (int) $essay['slot'],
                '_moodle_question_number' => $questionnumber,
                '_moodle_objective_score' => $objectivescore,
            ];

            $payload = [
                'course_name' => $course->fullname ?? '',
                'assignment_name' => ($quiz->name ?? '') . ' — Q' . $questionnumber,
                'subject_area' => '', // no direct Moodle equivalent for this yet
                'submission' => $submission,
            ];

            try {
                // Convert payload to JSON
                $jsondata = json_encode($payload);

                // Send message to RabbitMQ queues
                rabbitmq_helper::send_message($queues, $jsondata);
            } catch (\Throwable $th) {
                debugging("Failed to send quiz essay to queue. {$th->getMessage()}", DEBUG_DEVELOPER);
            }
        }

        return true; // Always return true for event handlers
    }
}