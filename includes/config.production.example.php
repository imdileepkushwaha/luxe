<?php
/**
 * Live server / hosting.
 * cp includes/config.production.example.php includes/config.production.php
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'admin_luxeecomm',
        'user' => 'luxeecomm',
        'pass' => 'zETQ#p33ltq?0fxz',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from' => 'LUXE <noreply@yourdomain.com>',
        'from_email' => 'noreply@yourdomain.com',
        'from_name' => 'LUXE',
        'force_real_email' => false,
        'skip_email_send' => false,
        'smtp' => [
            'host' => 'smtp.yourdomain.com',
            'port' => 587,
            'secure' => 'tls',
            'username' => 'noreply@yourdomain.com',
            'password' => '',
            'timeout' => 30,
            'allow_self_signed' => false,
            'debug' => 0,
        ],
    ],
    'sms' => [
        'withdraw_otp_mode' => 'production',
        'withdraw_otp_dev_code' => '123456',
        'withdraw_otp_ttl_seconds' => 600,
        'withdraw_otp_stub_log_phone' => '+919999999999',
        'sms_api_url' => '',
        'sms_api_key' => '',
        'sms_sender_id' => 'LUXE',
    ],
    'razorpay' => [
        'key_id' => 'rzp_live_xxxxxxxx',
        'key_secret' => '',
        'dev_skip_gateway' => false,
    ],
    'captcha' => [
        'provider' => 'builtin',
        'enabled' => true,
        'secret_salt' => 'change-this-to-a-long-random-string',
        'site_key' => '',
        'secret_key' => '',
    ],
];
