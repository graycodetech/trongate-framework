<?php
class Sms_gateway extends Trongate {

    /**
     * Helper to load the SMS gateway configuration
     *
     * @param string $profile
     * @return array
     */
    private function _get_config(string $profile = 'default'): array {
        $config_file = APPPATH . 'config/sms_gateway.php';
        if (file_exists($config_file)) {
            include $config_file;
            if (isset($sms_gateways[$profile])) {
                return $sms_gateways[$profile];
            }
        }
        // Fallback or empty defaults if not defined
        return [
            'apikey' => '',
            'partnerID' => '',
            'shortcode' => '',
            'pass_type' => 'plain'
        ];
    }

    /**
     * Helper to perform a cURL POST request
     *
     * @param string $url
     * @param array $payload
     * @return array
     */
    private function _make_post_request(string $url, array $payload): array {
        $json_data = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $http_code
            ];
        }

        $decoded_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'raw_response' => $response,
                'error' => 'Invalid JSON response format from gateway api.',
                'http_code' => $http_code
            ];
        }

        return [
            'success' => true,
            'http_code' => $http_code,
            'data' => $decoded_response
        ];
    }

    /**
     * Send a single/scheduled SMS using Advanta API
     *
     * @param string $mobile Number to send SMS (e.g. 254712345678)
     * @param string $message The message body content
     * @param string|null $timeToSend Optional parameter with a valid date string
     * @param string $profile Config profile to use (defaults to 'default')
     * @return array Response array containing cURL result and parsed data
     */
    public function send_sms(string $mobile, string $message, ?string $timeToSend = null, string $profile = 'default'): array {
        $config = $this->_get_config($profile);

        $payload = [
            'apikey' => $config['apikey'],
            'partnerID' => $config['partnerID'],
            'message' => $message,
            'shortcode' => $config['shortcode'],
            'mobile' => $mobile
        ];

        if ($timeToSend !== null) {
            $payload['timeToSend'] = $timeToSend;
        }

        $url = 'https://quicksms.advantasms.com/api/services/sendsms/';
        return $this->_make_post_request($url, $payload);
    }

    /**
     * Send bulk messages (up to 20 per call) using Advanta Bulk API
     *
     * Each entry in $smsList is expected to be an associative array with:
     * - 'mobile' => string
     * - 'message' => string
     * - 'clientsmsid' => optional unique string/int
     *
     * @param array $smsList List of SMS records to send
     * @param string $profile Config profile to use (defaults to 'default')
     * @return array Response array containing cURL result and parsed data
     */
    public function send_bulk(array $smsList, string $profile = 'default'): array {
        $config = $this->_get_config($profile);

        $formatted_smslist = [];
        foreach ($smsList as $sms) {
            $formatted_smslist[] = [
                'partnerID' => $config['partnerID'],
                'apikey' => $config['apikey'],
                'mobile' => $sms['mobile'],
                'message' => $sms['message'],
                'shortcode' => $config['shortcode'],
                'clientsmsid' => $sms['clientsmsid'] ?? rand(1000, 99999),
                'pass_type' => $config['pass_type'] ?? 'plain'
            ];
        }

        $payload = [
            'count' => count($formatted_smslist),
            'smslist' => $formatted_smslist
        ];

        $url = 'https://quicksms.advantasms.com/api/services/sendbulk/';
        return $this->_make_post_request($url, $payload);
    }

    /**
     * Fetch the delivery report for a specific message ID
     *
     * @param string|int $messageID The API-generated message ID to query
     * @param string $profile Config profile to use (defaults to 'default')
     * @return array Response array containing cURL result and parsed data
     */
    public function get_delivery_report($messageID, string $profile = 'default'): array {
        $config = $this->_get_config($profile);

        $payload = [
            'apikey' => $config['apikey'],
            'partnerID' => $config['partnerID'],
            'messageID' => (string) $messageID
        ];

        $url = 'https://quicksms.advantasms.com/api/services/getdlr/';
        return $this->_make_post_request($url, $payload);
    }

    /**
     * Get account credits / balance from Advanta API
     *
     * @param string $profile Config profile to use (defaults to 'default')
     * @return array Response array containing cURL result and parsed data
     */
    public function get_balance(string $profile = 'default'): array {
        $config = $this->_get_config($profile);

        $payload = [
            'apikey' => $config['apikey'],
            'partnerID' => $config['partnerID']
        ];

        $url = 'https://quicksms.advantasms.com/api/services/getbalance/';
        return $this->_make_post_request($url, $payload);
    }
}
