<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\mod_assign\event\assessable_submitted',
        'callback'    => '\local_submissionmq\observer::assignment_submitted',
        'priority'    => 9999,
        'internal'    => false,
    ],
];
