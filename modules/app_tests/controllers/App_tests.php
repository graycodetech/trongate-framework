<?php
class App_tests extends Trongate {

    /**
     * Run all integration and unit usage examples for SMS Gateway and Trongate Email
     */
    public function index() {
        echo "<h1>App Tests & Usage Examples</h1>";
        echo "<ul>";
        echo "<li><a href='" . BASE_URL . "app_tests/test_email_simple'>Test Email (Simple Attachment)</a></li>";
        echo "<li><a href='" . BASE_URL . "app_tests/test_email_multiple'>Test Email (Multiple Custom Attachments)</a></li>";
        echo "<li><a href='" . BASE_URL . "app_tests/test_sms_single'>Test Single SMS</a></li>";
        echo "<li><a href='" . BASE_URL . "app_tests/test_sms_bulk'>Test Bulk SMS</a></li>";
        echo "<li><a href='" . BASE_URL . "app_tests/test_sms_balance'>Test SMS Balance Check</a></li>";
        echo "</ul>";
    }

    /**
     * Test simple email attachment
     */
    public function test_email_simple() {
        // Create a dummy attachment file
        $temp_file = sys_get_temp_dir() . '/trongate_test_simple.txt';
        file_put_contents($temp_file, "This is a simple email attachment test.");

        $this->module('trongate_email');
        $email = new Trongate_email(); // loads 'default' SMTP profile

        $params = [
            'to_email' => 'user@example.com',
            'subject' => 'Hello with Attachment',
            'body_html' => '<h1>Check your file</h1>',
            'attachments' => [$temp_file]
        ];

        // Since SMTP credentials may be empty in config, this might return false if stream fails.
        // We'll output the built structure parameters and attempt to execute.
        echo "<h2>Testing Simple Email Attachment with Default Profile</h2>";
        echo "<pre>Parameters: " . print_r($params, true) . "</pre>";

        try {
            $result = $email->send($params);
            echo "<p>Result: " . ($result ? "Success" : "Failed (this is expected if SMTP host is unconfigured/empty)") . "</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Exception: " . $e->getMessage() . "</p>";
        }

        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        echo "<p><a href='" . BASE_URL . "app_tests'>Back</a></p>";
    }

    /**
     * Test email with multiple custom attachments using alternate profile
     */
    public function test_email_multiple() {
        // Create dummy files
        $temp_pdf = sys_get_temp_dir() . '/invoice.pdf';
        $temp_jpg = sys_get_temp_dir() . '/image.jpg';
        file_put_contents($temp_pdf, "%PDF-1.4 dummy pdf content");
        file_put_contents($temp_jpg, "dummy jpeg content");

        $this->module('trongate_email');
        $email = new Trongate_email('alternate'); // loads 'alternate' SMTP profile

        $params = [
            'to_email' => 'user@example.com',
            'subject' => 'Multiple Attachments',
            'body_html' => '<h1>Files attached</h1>',
            'attachments' => [
                $temp_pdf,
                [
                    'path' => $temp_jpg,
                    'name' => 'Profile_Photo.jpg',
                    'mime_type' => 'image/jpeg'
                ]
            ]
        ];

        echo "<h2>Testing Multiple Email Attachments with 'alternate' SMTP Profile</h2>";
        echo "<pre>Parameters: " . print_r($params, true) . "</pre>";

        try {
            $result = $email->send($params);
            echo "<p>Result: " . ($result ? "Success" : "Failed (this is expected if SMTP host is unconfigured/empty)") . "</p>";
        } catch (Exception $e) {
            echo "<p style='color:red;'>Exception: " . $e->getMessage() . "</p>";
        }

        if (file_exists($temp_pdf)) unlink($temp_pdf);
        if (file_exists($temp_jpg)) unlink($temp_jpg);
        echo "<p><a href='" . BASE_URL . "app_tests'>Back</a></p>";
    }

    /**
     * Test Single SMS Sending
     */
    public function test_sms_single() {
        $this->module('sms_gateway');

        echo "<h2>Testing Single SMS sending via Sms_gateway</h2>";

        $mobile = '254712345678';
        $message = 'This is a single test message';

        echo "<p>Target Mobile: {$mobile}</p>";
        echo "<p>Message: {$message}</p>";

        $response = $this->sms_gateway->send_sms($mobile, $message);
        echo "<pre>Response Payload: " . print_r($response, true) . "</pre>";
        echo "<p><a href='" . BASE_URL . "app_tests'>Back</a></p>";
    }

    /**
     * Test Bulk SMS Sending (Up to 20 messages)
     */
    public function test_sms_bulk() {
        $this->module('sms_gateway');

        echo "<h2>Testing Bulk SMS sending via Sms_gateway</h2>";

        $sms_list = [
            [
                'mobile' => '254733123456',
                'message' => 'This is a test message 1',
                'clientsmsid' => 1234
            ],
            [
                'mobile' => '254711123456',
                'message' => 'This is a test message 2',
                'clientsmsid' => 1235
            ]
        ];

        echo "<pre>Input SMS list: " . print_r($sms_list, true) . "</pre>";

        $response = $this->sms_gateway->send_bulk($sms_list);
        echo "<pre>Response Payload: " . print_r($response, true) . "</pre>";
        echo "<p><a href='" . BASE_URL . "app_tests'>Back</a></p>";
    }

    /**
     * Test SMS Credit Balance Querying
     */
    public function test_sms_balance() {
        $this->module('sms_gateway');

        echo "<h2>Testing SMS account credit/balance fetch</h2>";

        $response = $this->sms_gateway->get_balance();
        echo "<pre>Response Payload: " . print_r($response, true) . "</pre>";
        echo "<p><a href='" . BASE_URL . "app_tests'>Back</a></p>";
    }
}
