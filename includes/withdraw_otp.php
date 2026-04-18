<?php

declare(strict_types=1);

/**
 * Seller withdraw OTP: stub (fixed dev code + log) or production (random OTP + SMS hook).
 */

function withdraw_otp_sms_defaults(): array
{
    return [
        'withdraw_otp_mode' => 'stub',
        'withdraw_otp_dev_code' => '123456',
        'withdraw_otp_ttl_seconds' => 600,
        /** Log line me default / test destination (asli SMS abhi nahi) */
        'withdraw_otp_stub_log_phone' => '+919999999999',
        /** Production: HTTP SMS API (implement withdraw_otp_sms_http_send) */
        'sms_api_url' => '',
        'sms_api_key' => '',
        'sms_sender_id' => 'LUXE',
    ];
}

/** @return array<string, mixed> */
function withdraw_otp_sms_config(): array
{
    $path = __DIR__ . '/config.php';
    $extra = [];
    if (is_readable($path)) {
        $cfg = require $path;
        if (is_array($cfg['sms'] ?? null)) {
            $extra = $cfg['sms'];
        }
    }

    return array_merge(withdraw_otp_sms_defaults(), $extra);
}

function withdraw_otp_normalize_phone_digits(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($d) === 10) {
        return '91' . $d;
    }

    return $d;
}

function withdraw_otp_seller_phone(PDO $pdo, int $sellerId): string
{
    $st = $pdo->prepare('SELECT phone_number FROM seller_users WHERE id = ? LIMIT 1');
    $st->execute([$sellerId]);

    return trim((string) $st->fetchColumn());
}

/**
 * Stub: error_log only. Production: sms_api_url set hone par HTTP hook (extend karein).
 *
 * @param array<string, mixed> $cfg
 */
function withdraw_otp_deliver_sms(string $e164Digits, string $message, array $cfg): bool
{
    $mode = (string) ($cfg['withdraw_otp_mode'] ?? 'stub');
    if ($mode !== 'production') {
        $stubPhone = (string) ($cfg['withdraw_otp_stub_log_phone'] ?? '');
        error_log('[LUXE withdraw OTP stub] seller_phone_e164=' . $e164Digits . ' default_log_phone=' . $stubPhone . ' | ' . $message);

        return true;
    }

    $url = trim((string) ($cfg['sms_api_url'] ?? ''));
    if ($url === '') {
        error_log('[LUXE withdraw OTP] production mode but sms_api_url empty — SMS not sent');

        return false;
    }

    return withdraw_otp_sms_http_send($url, $e164Digits, $message, $cfg);
}

/**
 * Real SMS integration yahan add karein (POST JSON, Twilio, MSG91, etc.).
 *
 * @param array<string, mixed> $cfg
 */
function withdraw_otp_sms_http_send(string $url, string $e164Digits, string $message, array $cfg): bool
{
    if (!function_exists('curl_init')) {
        error_log('[LUXE withdraw OTP] PHP curl extension required for SMS HTTP');

        return false;
    }

    // Template: POST JSON { to, text, key } — apne provider ke hisaab se badlein
    $key = (string) ($cfg['sms_api_key'] ?? '');
    try {
        $payload = json_encode([
            'to' => $e164Digits,
            'text' => $message,
            'sender' => (string) ($cfg['sms_sender_id'] ?? 'LUXE'),
        ], JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return false;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array_filter([
            'Content-Type: application/json',
            $key !== '' ? 'Authorization: Bearer ' . $key : null,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        error_log('[LUXE withdraw OTP] SMS HTTP failed code=' . $code . ' body=' . substr((string) $body, 0, 500));

        return false;
    }

    return true;
}

/**
 * @param array{amount:int, method:string, account_ref:string, note:string} $payload
 */
function withdraw_otp_begin_challenge(PDO $pdo, int $sellerId, array $payload): ?string
{
    $phoneRaw = withdraw_otp_seller_phone($pdo, $sellerId);
    $digits = withdraw_otp_normalize_phone_digits($phoneRaw);
    if ($digits === '' || strlen($digits) < 10) {
        return 'Withdraw ke liye pehle profile me apna sahi mobile number save karein.';
    }

    $cfg = withdraw_otp_sms_config();
    $mode = (string) ($cfg['withdraw_otp_mode'] ?? 'stub');
    $ttl = (int) ($cfg['withdraw_otp_ttl_seconds'] ?? 600);
    if ($ttl < 60) {
        $ttl = 600;
    }

    if ($mode === 'production') {
        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    } else {
        $otp = (string) ($cfg['withdraw_otp_dev_code'] ?? '123456');
        $otpHash = '';
    }

    $msg = 'LUXE Seller: Withdraw OTP ' . $otp . '.10 min valid. Kisi ke sath share na karein.';

    if (!withdraw_otp_deliver_sms($digits, $msg, $cfg)) {
        return 'OTP SMS abhi bheja nahi ja saka. Thodi der baad try karein.';
    }

    $_SESSION['seller_withdraw_otp_challenge'] = [
        'seller_id' => $sellerId,
        'exp' => time() + $ttl,
        'payload' => $payload,
        'mode' => $mode,
        'otp_hash' => $otpHash,
        'phone_last4' => substr($digits, -4),
    ];

    return null;
}

/**
 * @return ?string error message or null on success (withdraw row inserted)
 */
function withdraw_otp_confirm_and_create_request(PDO $pdo, int $sellerId, string $otpInput): ?string
{
    $ch = $_SESSION['seller_withdraw_otp_challenge'] ?? null;
    if (!is_array($ch) || (int) ($ch['seller_id'] ?? 0) !== $sellerId) {
        return 'OTP session invalid. Dobara OTP mangwaein.';
    }
    if (time() > (int) ($ch['exp'] ?? 0)) {
        unset($_SESSION['seller_withdraw_otp_challenge']);

        return 'OTP expire ho gaya. Dobara OTP mangwaein.';
    }

    $otpTrim = trim($otpInput);
    if ($otpTrim === '' || !preg_match('/^[0-9]{4,8}$/', $otpTrim)) {
        return 'OTP 4–8 digits me enter karein.';
    }

    $mode = (string) ($ch['mode'] ?? 'stub');
    $cfg = withdraw_otp_sms_config();
    if ($mode === 'production') {
        $hash = (string) ($ch['otp_hash'] ?? '');
        if ($hash === '' || !password_verify($otpTrim, $hash)) {
            return 'Galat OTP.';
        }
    } else {
        $dev = (string) ($cfg['withdraw_otp_dev_code'] ?? '123456');
        if (!hash_equals($dev, $otpTrim)) {
            return 'Galat OTP. Dev mode me config ka withdraw_otp_dev_code use karein.';
        }
    }

    $payload = $ch['payload'] ?? null;
    if (!is_array($payload)) {
        unset($_SESSION['seller_withdraw_otp_challenge']);

        return 'Invalid session. Form dubara bhar ke OTP mangwaein.';
    }

    require_once __DIR__ . '/../seller/_finance.php';

    $amount = max(0, (int) ($payload['amount'] ?? 0));
    $method = strtolower(trim((string) ($payload['method'] ?? 'bank')));
    $accountRef = trim((string) ($payload['account_ref'] ?? ''));
    $note = trim((string) ($payload['note'] ?? ''));
    if (!in_array($method, ['bank', 'upi'], true)) {
        $method = 'bank';
    }
    if (strlen($note) > 255) {
        $note = substr($note, 0, 255);
    }

    $summary = seller_finance_summary($pdo, $sellerId);
    $available = (int) $summary['withdrawable_balance'];

    if ($amount < 100) {
        return 'Minimum withdraw amount Rs 100 hai.';
    }
    if ($accountRef === '') {
        return 'Account / UPI details required hai.';
    }
    if ($amount > $available) {
        return 'Requested amount withdrawable balance se zyada hai.';
    }

    $ins = $pdo->prepare(
        'INSERT INTO seller_withdraw_requests (seller_id, amount, method, account_ref, note, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$sellerId, $amount, $method, $accountRef, $note, 'pending']);
    unset($_SESSION['seller_withdraw_otp_challenge']);

    return null;
}

function withdraw_otp_cancel_challenge(): void
{
    unset($_SESSION['seller_withdraw_otp_challenge']);
}

/** @return array<string, mixed>|null */
function withdraw_otp_pending_challenge_for_seller(int $sellerId): ?array
{
    $ch = $_SESSION['seller_withdraw_otp_challenge'] ?? null;
    if (!is_array($ch) || (int) ($ch['seller_id'] ?? 0) !== $sellerId) {
        return null;
    }
    if (time() > (int) ($ch['exp'] ?? 0)) {
        unset($_SESSION['seller_withdraw_otp_challenge']);

        return null;
    }

    return $ch;
}
