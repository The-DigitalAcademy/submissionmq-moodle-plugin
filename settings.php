<?php
// local/autograder/settings.php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Add a new settings page for the local plugin.
    $settings = new admin_settingpage('local_autograder', get_string('pluginname', 'local_autograder'));

    // Create a new settings category under 'Local plugins' if it doesn't exist
    $ADMIN->add('localplugins', $settings);

    // Setting for the Autograder API Endpoint
    $settings->add(new admin_setting_configtext(
        'local_autograder/api_endpoint', // Setting name
        get_string('api_endpoint', 'local_autograder'), // Title
        get_string('api_endpoint_desc', 'local_autograder'), // Description
        'http://localhost:3000/submission', // Default value
        PARAM_URL // Parameter type
    ));

    // Setting for an optional API Key/Secret (recommended for security)
    $settings->add(new admin_setting_configtext(
        'local_autograder/api_key',
        get_string('api_key', 'local_autograder'),
        get_string('api_key_desc', 'local_autograder'),
        '', // Default value: empty
        PARAM_RAW // Store as raw string (sensitive info)
    ));
}