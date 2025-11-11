<?php
namespace local_submissionmq\helpers;

defined('MOODLE_INTERNAL') || die();

// Load Composer dependencies for RabbitMQ (PhpAmqpLib).
require_once __DIR__ . '/../../../../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Helper class for sending messages to RabbitMQ message queues.
 * 
 * This class provides an abstraction layer for interacting with a RabbitMQ message broker
 * from within Moodle. It is designed to send structured or plain text messages to one or
 * more queues, using configuration settings defined in the plugin settings (via Moodle admin).
 *
 * 
 * @package   local_submissionmq
 * @category  helper

 */
class rabbitmq_helper {

    /**
     * Sends a message body to one or more RabbitMQ queues.
     * 
     * This method creates a connection to the RabbitMQ broker using plugin configuration values,
     * declares the exchange and queues if they do not exist, binds each queue to the exchange,
     * and publishes a persistent message to the exchange.
     * 
     * @param string|string[] $queues The queue name(s) to send the message to. Can be a single string or an array of queue names.
     * @param string $body The message body to send (usually JSON-encoded data).
     * @return void
     * 
     * @throws \Exception If unable to connect or publish the message.
     * 
     */
    public static function send_message(string|array $queues, string $body) {

        // Early exit if required parameters are missing.
        if (empty($queues) || empty($body)) {
            return;
        }

        // Normalize queue input into an array.
        $targetQueues = is_array($queues) ? $queues : [$queues];

        // Retrieve RabbitMQ connection and exchange configuration from Moodle plugin settings.
        $host = get_config('local_submissionmq', 'host');
        $port = get_config('local_submissionmq', 'port');
        $exchange = get_config('local_submissionmq', 'exchange');
        $user = get_config('local_submissionmq', 'user');
        $password = get_config('local_submissionmq', 'password');

        // Establish a connection to the RabbitMQ broker.
        $connection = new AMQPStreamConnection($host, $port, $user, $password);
        $channel = $connection->channel();

        // Declare the exchange if it doesn't exist.
        // 'fanout' means messages will be sent to all queues bound to this exchange.
        // Durable = true ensures the exchange survives a broker restart.
        $channel->exchange_declare($exchange, 'fanout', false, true, false);

        // Create a persistent message.
        $message = new AMQPMessage($body, ['delivery_mode'=> AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        
        // Ensure each queue exists and is bound to the exchange.
        foreach ($targetQueues as $queueName) {
            $channel->queue_declare($queueName, false, true, false, false);
            $channel->queue_bind($queueName, $exchange);
        }

        // Publish the message to the exchange.
        $channel->basic_publish($message, $exchange);

        // Close the channel and connection to free resources.
        $channel->close();
        $connection->close();
    }
}