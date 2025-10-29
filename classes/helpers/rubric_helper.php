<?php
namespace local_autograder\helpers;

defined('MOODLE_INTERNAL') || die();

class rubric_helper {
    /**
     * Fetch the rubric definition, criteria, and levels for a given assignment context.
     * 
     * @param int $contextid The context id of the assignment
     * @return array{name: string, description: string, criteria: array} Rubric data as array or null if not found.
     */
    public static function get_rubric_for_assignment(int $contextid): ?array {
        global $DB;

        // 1️⃣ find grading area for context
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

        // 3️⃣ Fetch all rubric criteria
        $criteria = $DB->get_records('gradingform_rubric_criteria', [
            'definitionid' => $definition->id,
        ]);

        $rubric_criteria = [];
        foreach ($criteria as $criterion) {
            $levels = $DB->get_records('gradingform_rubric_levels', [
                'criterionid' => $criterion->id
            ]);

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


        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'criteria' => $rubric_criteria
        ];
    }
}