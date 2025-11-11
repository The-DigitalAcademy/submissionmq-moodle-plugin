<?php
namespace local_submissionmq\helpers;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for retrieving grading rubric details for a given assignment.
 * 
 * This class provides a utility method to extract a rubric definition,
 * its criteria, and associated levels from Moodle's grading tables.
 * 
 * @package    local_submissionmq
 * @subpackage helpers
 */
class rubric_helper {
    /**
     * Fetch the rubric definition, criteria, and levels for a given assignment context.
     * 
     * This method looks up the grading area for the given assignment,
     * finds its associated rubric definition, and returns all rubric criteria
     * with their corresponding performance levels (including scores).
     * 
     * @param int $contextid The context id of the assignment
     * @return array{name: string, description: string, criteria: array}|null Returns rubric data as an associative array, or null if no rubric is found.
     */
    public static function get_rubric_for_assignment(int $contextid): ?array {
        global $DB;

        // 1️⃣ Find the grading area for this assignment context.
        $gradingarea = $DB->get_record('grading_areas', [
            'contextid' => $contextid,
            'component' => 'mod_assign',
            'areaname' => 'submissions'
        ]);

        if (!$gradingarea) {
            return null;
        }

        // 2️⃣ Get rubric definition
        $definition = $DB->get_record('grading_definitions', [
            'areaid' => $gradingarea->id
        ]);

        if (!$definition) {
            return null;
        }

        // 3️⃣ Fetch all criteria associated with this rubric definition.
        $criteria = $DB->get_records('gradingform_rubric_criteria', [
            'definitionid' => $definition->id,
        ]);

        $rubric_criteria = [];

        foreach ($criteria as $criterion) {
            $levels = $DB->get_records('gradingform_rubric_levels', [
                'criterionid' => $criterion->id
            ]);

            // Build a structured array for this criterion.
            $rubric_criteria[] = [
                'criterionid' => $criterion->id,
                'criteriondescription' => $criterion->description,
                'levels' => array_values(array_map(function($level) {
                    return [
                        'id' => $level->id,
                        'definition' => $level->definition,
                        'score' => $level->score
                    ];
                }, $levels))
            ];
        }


        // 4️⃣ Step 4: Return the assembled rubric data as an associative array.
        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'criteria' => $rubric_criteria
        ];
    }
}