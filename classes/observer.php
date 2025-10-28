<?php
namespace local_autograder;

defined('MOODLE_INTERNAL') || die();

use local_autograder\helpers\rubric_helper;
use local_autograder\helpers\tag_helper;
use local_autograder\helpers\api_helper;

class observer {

    /**
     * Triggered when an assignment is submitted.
     *
     * @param \mod_assign\event\assessable_submitted $event
     */
    public static function assignment_submitted(\mod_assign\event\assessable_submitted $event) {
        global $DB;

         // Event data
        $event_data = $event->get_data();

        // Submission data
        $submission = $DB->get_record('assign_submission', [
            'id' => $event_data['objectid'],
        ]);

        // check if assignment activity has "autograde" tag.
        if (!tag_helper::assignment_has_autograde_tag((string) $event_data['contextinstanceid'])) {
            return true; // skip silently
        }

        // Online text content (if exists)
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'submission' => $event_data['objectid']
        ]);

        // Assignment instance
        $assignment = $DB->get_record('assign', [
            'id' => $submission->assignment
        ]);

        // grading rubric
        $rubric = rubric_helper::get_rubric_for_assignment($event_data['contextid']);

        $payload = [
            'onlinetextid' => $onlinetext->id,
            'submissionid' => $onlinetext->submission,
            'onlinetext' => $onlinetext->onlinetext,
            'userid' => $submission->userid,
            'status' => $submission->status,
            'courseid' => $event_data['courseid'],
            'assignmentid' => $onlinetext->assignment,
            'assignmentname' => $assignment->name,
            'assignmentintro' => $assignment->intro,
            'assignmentactivity' => $assignment->activity,
            'assignmentgrade' => $assignment->grade,
            'timecreated' => $submission->timecreated,
            'assignmentrubric' => $rubric,
        ];


        $result = api_helper::send_submission($payload);

        if ($result === false) {
             return true; // The helper logged the reason for skipping (missing URL)
        }

        $response = $result['response'];
        $httpcode = $result['httpcode'];

        // Log success or failure
        if ($httpcode >= 200 && $httpcode < 300) {
            debugging("✅ Successfully sent submission for user {$event_data['userid']} to external API.", DEBUG_DEVELOPER);
        } else {
            debugging("❌ Failed to send submission. HTTP Code: {$httpcode}, Response: {$response}", DEBUG_DEVELOPER);
        }

        return true; // Always return true for event handlers
    }
}
