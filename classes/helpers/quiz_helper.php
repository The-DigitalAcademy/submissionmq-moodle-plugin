<?php
namespace local_submissionmq\helpers;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for extracting essay question responses from a quiz attempt.
 *
 * This class provides utility methods to load a finished quiz attempt and
 * pull out the responses for any essay-type questions it contains, along
 * with the question text and max mark for each, and to total up marks
 * for the non-essay (objective) questions in the same attempt.
 *
 * @package    local_submissionmq
 * @subpackage helpers
 */
class quiz_helper {

    /**
     * Get all essay-type question responses for a given quiz attempt.
     *
     * Iterates every slot in the attempt, filters to qtype 'essay', and
     * returns one record per essay question containing the response text,
     * the question text, and the max mark for that question.
     *
     * @param int $attemptid The quiz attempt id (quiz_attempts.id).
     * @return array List of essay response records for this attempt. Empty
     *               array if the attempt has no essay questions.
     */
    public static function get_essay_responses(int $attemptid): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        // Moodle 5.x moved quiz_attempt into the mod_quiz namespace.
        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);

        $essays = [];

        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $question = $qa->get_question();

            // Only interested in essay questions - skip everything else.
            if ($question->qtype->name() !== 'essay') {
                continue;
            }

            // Moodle wraps essay 'answer' responses in a question_file_loader object
            // (not a plain string), since the field supports embedded files/images
            // and needs to rewrite @@PLUGINFILE@@ URLs. Casting to string resolves
            // it to the actual HTML response text.
            $rawanswer = $qa->get_last_qt_var('answer');
            $responsetext = is_null($rawanswer) ? null : (string) $rawanswer;

            // Pull the grading guide from Moodle's own built-in "Information
            // for graders" field on the essay question (qtype_essay_options.
            // graderinfo) - this is the same field Moodle shows a human grader
            // when manually marking the essay, and (for this course) already
            // contains a real marks-breakdown rubric per question.
            global $DB;
            $graderinfo = $DB->get_field('qtype_essay_options', 'graderinfo', ['questionid' => $question->id]);
            $gradingguide = !empty($graderinfo) ? trim(strip_tags($graderinfo)) : '';

            $essays[] = [
                'questionattemptid' => $qa->get_database_id(),
                'slot' => $slot,
                'questionid' => $question->id,
                'questionname' => $question->name,
                'questiontext' => $question->questiontext,
                'questiongeneralfeedback' => $question->generalfeedback ?? null,
                'responsetext' => $responsetext,
                'maxmark' => $qa->get_max_mark(),
                'gradingguide' => $gradingguide,
            ];
        }

        return $essays;
    }

    /**
     * Sum the marks for all non-essay (objective) questions in a quiz attempt.
     *
     * Mirrors the Python-side moodle_client.get_objective_score_from_attempt(),
     * so a real-time (event-driven) quiz job carries the same objective score
     * a pull-based moodle_quiz_producer.py job would, allowing the worker to
     * add it to the accumulated essay total once all essays are graded.
     *
     * @param int $attemptid The quiz attempt id (quiz_attempts.id).
     * @return float Total marks earned on non-essay questions in this attempt.
     */
    public static function get_objective_score(int $attemptid): float {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attemptobj = \mod_quiz\quiz_attempt::create($attemptid);

        $total = 0.0;

        foreach ($attemptobj->get_slots() as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $question = $qa->get_question();

            // Skip essay questions - only sum objective (auto-gradeable) ones.
            if ($question->qtype->name() === 'essay') {
                continue;
            }

            $mark = $qa->get_mark();
            if ($mark !== null) {
                $total += (float) $mark;
            }
        }

        return $total;
    }
}