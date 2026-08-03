<?php
/**
 * Email sending class for handling SMTP email delivery.
 * Supports HTML and plain text emails with file attachments via authenticated SMTP.
 * 
 * Configuration is read from config/trongate_email.php
 */
class Trongate_email {

    private string $smtp_host;
    private int $smtp_port;
    private string $smtp_user;
    private string $smtp_pass;
    private string $smtp_secure;
    private string $from_email;
    private string $from_name;
    private $socket = null;
    private array $attachments = [];

    /**
     * Class constructor.
     *
     * Prevents direct URL invocation of the module while allowing
     * safe internal usage via application code.
     * Loads configuration from config/trongate_email.php
     * Can specify an optional config profile (defaults to 'default').
     */
    public function __construct(string $profile = 'default') {
        block_url('trongate_email');
        
        // Load configuration
        $config = $this->load_config($profile);
        
        $this->smtp_host = $config['smtp_host'];
        $this->smtp_port = (int) ($config['smtp_port'] ?? 465);
        $this->smtp_user = $config['smtp_user'];
        $this->smtp_pass = $config['smtp_pass'];
        $this->smtp_secure = $config['smtp_secure'] ?? 'ssl';
        $this->from_email = $config['smtp_user'];
        $this->from_name = $config['smtp_from_name'] ?? '';
    }

    /**
     * Load configuration from config/trongate_email.php.
     *
     * @param string $profile The profile config key (e.g. 'default')
     * @return array The email configuration array
     * @throws Exception If config file is missing or required keys are absent
     */
    private function load_config(string $profile = 'default'): array {
        $config_path = APPPATH . 'config/trongate_email.php';
        
        if (!file_exists($config_path)) {
            throw new Exception('Trongate Email configuration file not found at ' . $config_path);
        }
        
        require $config_path;
        
        $profiles = $config['trongate_email'] ?? [];
        
        if (empty($profiles)) {
            throw new Exception('Configuration array $config[\'trongate_email\'] not found in ' . $config_path);
        }

        // Support both old flat single profile structures and new multi-profile structures
        if (isset($profiles[$profile]) && is_array($profiles[$profile])) {
            $config = $profiles[$profile];
        } elseif ($profile === 'default' && isset($profiles['smtp_host'])) {
            // Backward compatibility with previous single config style
            $config = $profiles;
        } else {
            throw new Exception("SMTP configuration profile '{$profile}' not found in " . $config_path);
        }
        
        $required_keys = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'];
        foreach ($required_keys as $key) {
            if (!isset($config[$key]) || $config[$key] === '') {
                throw new Exception("Required config key '{$key}' not set in config/trongate_email.php under profile '{$profile}'");
            }
        }
        
        return $config;
    }

    /**
     * Send an email with optional attachments.
     *
     * @param array $params Required keys: 'to_email', 'subject', 'body_html'
     *                      Optional keys: 'to_name', 'body_plain', 'attachments'
     *                      Attachments: array of file paths or arrays with 'path', 'name', 'mime_type'
     * @return bool True on success, false on failure.
     */
    public function send(array $params): bool {
        $to_email = $params['to_email'] ?? '';
        $to_name = $params['to_name'] ?? '';
        $subject = $params['subject'] ?? '';
        $body_html = $params['body_html'] ?? '';
        $body_plain = $params['body_plain'] ?? $this->html_to_plain($body_html);
        $attachments = $params['attachments'] ?? [];

        if (empty($to_email) || empty($subject) || empty($body_html)) {
            return false;
        }

        $message = $this->build_message($to_email, $to_name, $subject, $body_html, $body_plain, $attachments);
        return $this->smtp_send($to_email, $message);
    }

    /**
     * Convert HTML to plain text.
     */
    private function html_to_plain(string $html): string {
        $plain = str_replace('</p>', "\n\n", $html);
        $plain = str_replace('<br>', "\n", $plain);
        $plain = str_replace('<br/>', "\n", $plain);
        $plain = str_replace('<br />', "\n", $plain);
        $plain = strip_tags($plain);
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        $plain = trim($plain);
        return $plain;
    }

    /**
     * Build the MIME message with optional attachments.
     */
    private function build_message(
        string $to_email,
        string $to_name,
        string $subject,
        string $body_html,
        string $body_plain,
        array $attachments = []
    ): string {
        $boundary = md5(uniqid(time()));
        $boundary_alt = md5(uniqid(time()) . 'alt');

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $this->format_address($this->from_email, $this->from_name);
        $headers[] = 'To: ' . $this->format_address($to_email, $to_name);
        $headers[] = 'Subject: ' . $this->encode_header($subject);

        // Determine if we have attachments
        if (!empty($attachments)) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $message = implode("\r\n", $headers) . "\r\n\r\n";

            // Add the multipart/alternative part for the email body
            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary_alt}\"\r\n\r\n";

            // Plain text part
            $message .= '--' . $boundary_alt . "\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= $this->quoted_printable_encode($body_plain) . "\r\n\r\n";

            // HTML part
            $message .= '--' . $boundary_alt . "\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= $this->quoted_printable_encode($body_html) . "\r\n\r\n";

            // End of alternative part
            $message .= '--' . $boundary_alt . "--\r\n\r\n";

            // Add attachments
            foreach ($attachments as $attachment) {
                $message .= $this->build_attachment_part($attachment, $boundary);
            }

            // End of mixed part
            $message .= '--' . $boundary . "--\r\n";
        } else {
            // No attachments - use multipart/alternative only
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $message = implode("\r\n", $headers) . "\r\n\r\n";

            // Plain text part
            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= $this->quoted_printable_encode($body_plain) . "\r\n\r\n";

            // HTML part
            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $message .= $this->quoted_printable_encode($body_html) . "\r\n\r\n";

            $message .= '--' . $boundary . "--\r\n";
        }

        return $message;
    }

    /**
     * Build an attachment part for the email.
     *
     * @param array|string $attachment Attachment data (path string or array with 'path', 'name', 'mime_type')
     * @param string $boundary The boundary string for the MIME message
     * @return string The formatted attachment part
     */
    private function build_attachment_part($attachment, string $boundary): string {
        // Parse attachment data
        if (is_string($attachment)) {
            $file_path = $attachment;
            $file_name = basename($file_path);
            $mime_type = function_exists('mime_content_type') && file_exists($file_path) ? (mime_content_type($file_path) ?: 'application/octet-stream') : 'application/octet-stream';
        } else {
            $file_path = $attachment['path'] ?? '';
            $file_name = $attachment['name'] ?? basename($file_path);
            $mime_type = $attachment['mime_type'] ?? (function_exists('mime_content_type') && file_exists($file_path) ? (mime_content_type($file_path) ?: 'application/octet-stream') : 'application/octet-stream');
        }

        // Read file content
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return ''; // Skip this attachment if file is not accessible
        }

        $file_content = file_get_contents($file_path);
        $encoded_content = chunk_split(base64_encode($file_content));

        $part = '--' . $boundary . "\r\n";
        $part .= "Content-Type: " . $mime_type . "; name=\"" . $this->encode_header($file_name) . "\"\r\n";
        $part .= "Content-Transfer-Encoding: base64\r\n";
        $part .= "Content-Disposition: attachment; filename=\"" . $this->encode_header($file_name) . "\"\r\n\r\n";
        $part .= $encoded_content . "\r\n";

        return $part;
    }

    /**
     * Format an email address with optional name.
     */
    private function format_address(string $email, string $name = ''): string {
        if (empty($name)) {
            return $email;
        }
        return $this->encode_header($name) . ' <' . $email . '>';
    }

    /**
     * Encode a header value for non-ASCII characters.
     */
    private function encode_header(string $value): string {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /**
     * Encode content as quoted-printable.
     */
    private function quoted_printable_encode(string $string): string {
        return quoted_printable_encode($string);
    }

    /**
     * Send the message via SMTP.
     */
    private function smtp_send(string $to_email, string $message): bool {
        if (!$this->smtp_connect()) {
            return false;
        }

        if (!$this->smtp_authenticate()) {
            $this->smtp_disconnect();
            return false;
        }

        if (!$this->smtp_mail_from()) {
            $this->smtp_disconnect();
            return false;
        }

        if (!$this->smtp_rcpt_to($to_email)) {
            $this->smtp_disconnect();
            return false;
        }

        if (!$this->smtp_data($message)) {
            $this->smtp_disconnect();
            return false;
        }

        $this->smtp_quit();
        $this->smtp_disconnect();

        return true;
    }

    /**
     * Connect to the SMTP server.
     */
    private function smtp_connect(): bool {
        $host = $this->smtp_host;

        if ($this->smtp_secure === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $this->socket = @stream_socket_client(
            $host . ':' . $this->smtp_port,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->socket) {
            return false;
        }

        stream_set_timeout($this->socket, 30);

        $response = $this->smtp_get_response();
        if (!$this->smtp_response_ok($response, 220)) {
            return false;
        }

        $ehlo_host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->smtp_send_command('EHLO ' . $ehlo_host);
        $response = $this->smtp_get_response();

        if (!$this->smtp_response_ok($response, 250)) {
            return false;
        }

        if ($this->smtp_secure === 'tls') {
            $this->smtp_send_command('STARTTLS');
            $response = $this->smtp_get_response();

            if (!$this->smtp_response_ok($response, 220)) {
                return false;
            }

            $crypto = stream_socket_enable_crypto(
                $this->socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if (!$crypto) {
                return false;
            }

            $this->smtp_send_command('EHLO ' . $ehlo_host);
            $response = $this->smtp_get_response();

            if (!$this->smtp_response_ok($response, 250)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Authenticate with the SMTP server.
     */
    private function smtp_authenticate(): bool {
        $this->smtp_send_command('AUTH LOGIN');
        $response = $this->smtp_get_response();

        if (!$this->smtp_response_ok($response, 334)) {
            return false;
        }

        $this->smtp_send_command(base64_encode($this->smtp_user));
        $response = $this->smtp_get_response();

        if (!$this->smtp_response_ok($response, 334)) {
            return false;
        }

        $this->smtp_send_command(base64_encode($this->smtp_pass));
        $response = $this->smtp_get_response();

        if (!$this->smtp_response_ok($response, 235)) {
            return false;
        }

        return true;
    }

    /**
     * Send MAIL FROM command.
     */
    private function smtp_mail_from(): bool {
        $this->smtp_send_command('MAIL FROM:<' . $this->from_email . '>');
        $response = $this->smtp_get_response();
        return $this->smtp_response_ok($response, 250);
    }

    /**
     * Send RCPT TO command.
     */
    private function smtp_rcpt_to(string $email): bool {
        $this->smtp_send_command('RCPT TO:<' . $email . '>');
        $response = $this->smtp_get_response();
        return $this->smtp_response_ok($response, 250);
    }

    /**
     * Send the DATA command and message content.
     */
    private function smtp_data(string $message): bool {
        $this->smtp_send_command('DATA');
        $response = $this->smtp_get_response();

        if (!$this->smtp_response_ok($response, 354)) {
            return false;
        }

        $message = str_replace("\r\n.", "\r\n..", $message);

        fwrite($this->socket, $message . "\r\n.\r\n");
        $response = $this->smtp_get_response();

        return $this->smtp_response_ok($response, 250);
    }

    /**
     * Send QUIT command.
     */
    private function smtp_quit(): void {
        $this->smtp_send_command('QUIT');
        $this->smtp_get_response();
    }

    /**
     * Disconnect from the SMTP server.
     */
    private function smtp_disconnect(): void {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Send an SMTP command.
     */
    private function smtp_send_command(string $command): void {
        fwrite($this->socket, $command . "\r\n");
    }

    /**
     * Get the SMTP server response.
     */
    private function smtp_get_response(): string {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * Check if the response code matches the expected code.
     */
    private function smtp_response_ok(string $response, int $expected_code): bool {
        $code = (int) substr($response, 0, 3);
        return $code === $expected_code;
    }

}
