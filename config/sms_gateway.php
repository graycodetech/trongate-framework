<?php
/*
|--------------------------------------------------------------------------
| SMS Gateway Configuration Profiles
|--------------------------------------------------------------------------
| Here you can configure multiple profiles for sending SMS via different
| credentials/shortcodes, following the structure similar to database.php.
*/

$sms_gateways = [
    'default' => [
        'apikey' => '123456789',
        'partnerID' => '123',
        'shortcode' => 'SENDERID',
        'pass_type' => 'plain' // Optional parameter for bulk / pass type verification
    ]
];
