<?php
namespace local_submissionmq;

defined('MOODLE_INTERNAL') || die();

use local_submissionmq\helpers\rubric_helper;
use local_submissionmq\helpers\tag_helper;
use local_submissionmq\helpers\rabbitmq_helper;

/**
 * Observer class for handling Moodle events related to submissions.
 * 
 * Currently listens for assignment submission events and pushes submission
 * data to configured RabbitMQ queues if the assignment has a relevant tag.
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
            debugging("❌ Submission record not found for ID {$event_data['objectid']}", DEBUG_DEVELOPER);
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
            debugging("❌ Failed to send submission to queue. {$th->getMessage()}", DEBUG_DEVELOPER);
        }

        return true; // Always return true for event handlers
    }
}
