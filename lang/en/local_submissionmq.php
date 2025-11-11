<?php

$string['pluginname'] = 'Submission Message Queue';

$string['host'] = 'RabbitMQ Host';
$string['host_desc'] = 'The hostname or IP address of the RabbitMQ message broker. For example: <code>localhost</code> or <code>192.168.1.10</code>.';

$string['port'] = 'RabbitMQ Port';
$string['port_desc'] = 'The TCP port used to connect to RabbitMQ. The default is <code>5672</code> for non-SSL connections.';

$string['exchange'] = 'Exchange Name';
$string['exchange_desc'] = 'The name of the RabbitMQ exchange to publish messages to. Typically configured as a <code>fanout</code> exchange for broadcasting messages to multiple queues.';

$string['user'] = 'Username';
$string['user_desc'] = 'The username for authenticating with the RabbitMQ server. For example: <code>guest</code> or another user created by the administrator.';

$string['password'] = 'Password';
$string['password_desc'] = 'The password associated with the RabbitMQ user account. Ensure this value is kept secure.';

$string['tag_prefix'] = 'Tag Prefix';
$string['tag_prefix_desc'] = 'A prefix string used to identify or filter Moodle tags relevant to message queue processing (for example, assignments tagged with <code>mq_*</code>).';
