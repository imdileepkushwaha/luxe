<?php
/**
 * Local development (XAMPP / localhost).
 * cp includes/config.local.example.php includes/config.local.php
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'luxe_shop',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from' => 'LUXE <noreply@localhost.test>',
        'from_email' => 'noreply@localhost.test',
        'from_name' => 'LUXE Local',
        'force_real_email' => false,
        'skip_email_send' => false,
        'smtp' => [
            'host' => '',
            'port' => 587,
            'secure' => 'tls',
            'username' => '',
            'password' => '',
            'timeout' => 30,
            'allow_self_signed' => false,
            'debug' => 0,
        ],
    ],
    'sms' => [
        'withdraw_otp_mode' => 'stub',
        'withdraw_otp_dev_code' => '123456',
        'withdraw_otp_ttl_seconds' => 600,
        'withdraw_otp_stub_log_phone' => '+919999999999',
        'sms_api_url' => '',
        'sms_api_key' => '',
        'sms_sender_id' => 'LUXE',
    ],
    'razorpay' => [
        'key_id' => '',
        'key_secret' => '',
        'dev_skip_gateway' => true,
    ],
    'captcha' => [
        'provider' => 'builtin',
        'enabled' => true,
        'secret_salt' => 'local-dev-captcha-salt',
        'site_key' => '',
        'secret_key' => '',
    ],
];
