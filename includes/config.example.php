<?php
/**
 * Copy this file to config.php and set your MySQL credentials.
 * cp includes/config.example.php includes/config.php
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
    // Sign-up email: on localhost / 127.0.0.1 the app skips sending and shows the code in the UI.
    // On a real host, configure SMTP (install vendor + PHPMailer) or working PHP mail().
    'mail' => [
        'from' => 'LUXE <noreply@yourdomain.com>',
        'from_email' => 'noreply@yourdomain.com',
        'from_name' => 'LUXE',
        // Force real mail even on localhost (for testing SMTP).
        'force_real_email' => false,
        // Always skip sending and return code in API (local only — never on production).
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
    // Seller withdraw OTP (SMS). stub = fixed code + log only; production = random OTP + sms_api_url.
    'sms' => [
        'withdraw_otp_mode' => 'stub',
        'withdraw_otp_dev_code' => '123456',
        'withdraw_otp_ttl_seconds' => 600,
        'withdraw_otp_stub_log_phone' => '+919999999999',
        'sms_api_url' => '',
        'sms_api_key' => '',
        'sms_sender_id' => 'LUXE',
    ],
];
