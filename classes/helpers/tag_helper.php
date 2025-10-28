<?php
namespace local_autograder\helpers;

defined('MOODLE_INTERNAL') || die();

class tag_helper {
    /**
     * Check if an assignment activity has the autograde tag.
     * 
     * @param  int $cmid The course module ID of the assignment activity.
     * @return bool True if the "autograde" tag is present
     */

    public static function assignment_has_autograde_tag(int $cmid): bool {
        global $CFG, $DB;

        // Make sure tag API is available
        require_once($CFG->dirroot . '/tag/lib.php');

        //  Get tags attached to this assignment activity.
        $tags = \core_tag_tag::get_item_tags_array('core', 'course_modules', $cmid);
        
        if (empty($tags)) {
            return false;
        }

        // Normalise and check.
        foreach ($tags as $tagname) {
            if (strtolower((trim($tagname))) == 'autograde') {
                return true;
            }
        }

        return false;
    }
}