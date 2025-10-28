<?php
namespace local_autograder\helpers;

defined('MOODLE_INTERNAL') || die();

class api_helper {

    /**
     * Sends the JSON payload to the configured external API endpoint.
     *
     * @param array $payload Data to send.
     * @return array|bool Array with 'httpcode' and 'response' or false on failure.
     */
    public static function send_submission(array $payload) {

        // 1. Get configuration settings
        $apiurl = get_config('local_autograder', 'api_endpoint');
        $apikey = get_config('local_autograder', 'api_key');

        if (empty($apiurl)) {
            debugging("❌ Autograder API Endpoint is not configured. Skipping submission.", DEBUG_DEVELOPER);
            return false;
        }

        // 2. Encode payload as JSON
        $jsondata = json_encode($payload);

        // 3. Setup cURL
        $curl = new \curl();
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($apikey)) {
            // Add API Key for simple authentication
            $headers[] = 'X-API-Key: ' . $apikey;
        }

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        // 4. Send the JSON payload via cURL
        $response = $curl->post($apiurl, $jsondata, $options);
        $httpcode = $curl->get_info()['http_code'] ?? null;

        return ['httpcode' => $httpcode, 'response' => $response];
    }
}