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
    // Sign-up / profile verification: on localhost the app can skip sending and return the code in the API.
    // If mail.smtp.host is non-empty, OTP emails are sent only via PHPMailer (SMTP) — no PHP mail() fallback.
    // Leave smtp.host empty only if you rely on the server’s PHP mail() instead.
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
    // Storefront checkout: Razorpay Standard (Orders API). Test keys: https://dashboard.razorpay.com/app/keys
    // CLI smoke test: C:\xampp\php\php.exe scripts\razorpay-smoke-test.php — ya env: LUXE_RAZORPAY_KEY_ID / LUXE_RAZORPAY_KEY_SECRET
    'razorpay' => [
        'key_id' => '',
        'key_secret' => '',
        // true = online checkout bina Razorpay (dev only). Production: false + keys.
        'dev_skip_gateway' => false,
    ],
];
