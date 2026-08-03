<?php
/**
 * SMTP Email Configuration for Trongate Email Module
 *
 * Supports multiple SMTP profiles.
 */
$config['trongate_email'] = [
    'default' => [
        // Your SMTP server hostname
        'smtp_host' => '',

        // SMTP port (465 for SSL, 587 for STARTTLS)
        'smtp_port' => 465,

        // SMTP username (also used as the 'From' email address)
        'smtp_user' => '',

        // SMTP password
        'smtp_pass' => '',

        // Security: 'ssl', 'tls', or '' for no encryption
        'smtp_secure' => 'ssl',

        // Optional: Display name shown as the sender
        'smtp_from_name' => 'Trongate Support'
    ],
    'alternate' => [
        // Your alternate SMTP server hostname
        'smtp_host' => '',

        // SMTP port (465 for SSL, 587 for STARTTLS)
        'smtp_port' => 587,

        // SMTP username (also used as the 'From' email address)
        'smtp_user' => '',

        // SMTP password
        'smtp_pass' => '',

        // Security: 'ssl', 'tls', or '' for no encryption
        'smtp_secure' => 'tls',

        // Optional: Display name shown as the sender
        'smtp_from_name' => 'Trongate Alternate Support'
    ]
];
