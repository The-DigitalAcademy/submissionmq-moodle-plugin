<?php
define('CLI_SCRIPT', true);

require '/var/www/html/public/config.php';
require_once '/var/www/html/public/local/submissionmq/classes/helpers/rabbitmq_helper.php';

try {
    \local_submissionmq\helpers\rabbitmq_helper::send_message('test_queue', json_encode(['hello' => 'world']));
    echo "SUCCESS: message sent" . PHP_EOL;
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . PHP_EOL;
}