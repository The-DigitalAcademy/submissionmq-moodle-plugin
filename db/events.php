<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\mod_assign\event\assessable_submitted',
        'callback'    => '\local_submissionmq\observer::assignment_submitted',
        'priority'    => 9999,
        'internal'    => false,
    ],
    [
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => '\local_submissionmq\observer::quiz_attempt_submitted',
        'priority'    => 9999,
        'internal'    => false,
    ],
];