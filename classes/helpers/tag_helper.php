<?php
namespace local_submissionmq\helpers;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for working with tags in the context of a course module.
 * 
 * This class provides utility functions for interacting with Moodle's tag system
 * 
 * @package   local_submissionmq
 * @category  helper
 */
class tag_helper {
    /**
     * Get course module tags that contain a specific substring.
     * 
     * This method fetches all tags attached to a given course module (cmid),
     * then filters and returns only those whose names contain the provided substring.
     * 
     * @param int $cmid The course module ID. (ID from the `course_modules` table).
     * @param string $substring The substring to search for within tag names.
     * @return string[] A list of matching tag names (case-sensitive).
     */

    public static function get_course_module_tags_containing(int $cmid, string $substring) {
        global $CFG, $DB;

        // Ensure the Moodle tag API functions are available.
        require_once($CFG->dirroot . '/tag/lib.php');

        // Get all tags attached to this course module (e.g., assignment, quiz, etc.).
        $tags = \core_tag_tag::get_item_tags_array('core', 'course_modules', $cmid);
        
        if (empty($tags)) {
            return [];
        }

        
        // Filter tags to include only those that contain the specified substring.
        $filteredtags = array_filter(array_map('trim', $tags), callback: function($tag) use ($substring) {
            return str_contains(strtolower($tag),  strtolower($substring));
        });

        return $filteredtags;
    }
}