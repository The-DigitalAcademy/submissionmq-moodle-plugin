<?php
// local/submissionmq/settings.php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Add a new settings page under 'Local plugins'.
    $settings = new admin_settingpage('local_submissionmq', get_string('pluginname', 'local_submissionmq'));
    $ADMIN->add('localplugins', $settings);


    // Message broker host
    $settings->add(new admin_setting_configtext(
        'local_submissionmq/host',
        get_string('host', 'local_submissionmq'),
        get_string('host_desc', component: 'local_submissionmq'),
        'localhost',
        PARAM_RAW // Store as raw string (sensitive info)
    ));

    // Message broker port
    $settings->add(new admin_setting_configtext(
        'local_submissionmq/port',
        get_string('port', 'local_submissionmq'),
        get_string('port_desc', component: 'local_submissionmq'),
        '5672',
        PARAM_INT // Store as raw string (sensitive info)
    ));

    // Message broker exchange name
    $settings->add(new admin_setting_configtext(
        'local_submissionmq/exchange',
        get_string('exchange', 'local_submissionmq'),
        get_string('exchange_desc', component: 'local_submissionmq'),
        'moodle_exchange',
        PARAM_RAW // Store as raw string (sensitive info)
    ));

    // Message broker user
    $settings->add(new admin_setting_configtext(
        'local_submissionmq/user',
        get_string('user', 'local_submissionmq'),
        get_string('user_desc', component: 'local_submissionmq'),
        'guest',
        PARAM_RAW // Store as raw string (sensitive info)
    ));

    // Message broker password (masked in admin UI)
    $settings->add(new admin_setting_configpasswordunmask(
        'local_submissionmq/password',
        get_string('password', 'local_submissionmq'),
        get_string('password_desc', component: 'local_submissionmq'),
        'guest'
    ));

    // Tag prefix for filtering relevant course module tags
    $settings->add(new admin_setting_configtext(
        'local_submissionmq/tag_prefix',
        get_string('tag_prefix', 'local_submissionmq'),
        get_string('tag_prefix_desc', component: 'local_submissionmq'),
        'mqueue_',
        PARAM_RAW // Store as raw string (sensitive info)
    ));
}