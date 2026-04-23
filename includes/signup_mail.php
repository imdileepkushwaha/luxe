<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function luxe_app_config(): array
{
    static $cfg;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        $cfg = is_file($path) ? include $path : [];
    }

    return is_array($cfg) ? $cfg : [];
}

function luxe_signup_hmac_secret(): string
{
    $cfg = luxe_app_config();
    $s = (string) ($cfg['app_secret'] ?? '');
    if ($s === '' || $s === 'change-this-to-a-long-random-string') {
        return hash('sha256', 'luxe-default-dev-secret', true);
    }

    return hash('sha256', $s, true);
}

function luxe_signup_code_hash(string $verificationCode): string
{
    return hash_hmac('sha256', $verificationCode, luxe_signup_hmac_secret(), false);
}

/** Generate a random 6-digit OTP for email verification. */
function luxe_signup_otp_code(): string
{
    return (string) random_int(100000, 999999);
}

/**
 * @return array{0: string, 1: string} [email, display name]
 */
function luxe_mail_from_parts(array $mailCfg): array
{
    $fromHeader = (string) ($mailCfg['from'] ?? '');
    $email = trim((string) ($mailCfg['from_email'] ?? ''));
    $name = trim((string) ($mailCfg['from_name'] ?? 'LUXE'));

    if ($email === '' && $fromHeader !== '' && preg_match('/<\s*([^>\s]+)\s*>/', $fromHeader, $m)) {
        $email = $m[1];
    }
    if ($email === '') {
        $email = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }
    if ($fromHeader !== '' && preg_match('/^([^<]+)</', $fromHeader, $m2)) {
        $name = trim($m2[1], " \t\"'");
    }

    return [$email, $name !== '' ? $name : 'LUXE'];
}

function luxe_http_host_base(): string
{
    $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));

    return preg_replace('/:\d+$/', '', $h);
}

/** True when we skip real SMTP/mail() and return the code to the client (local dev only). */
function luxe_signup_email_skip_send(): bool
{
    $cfg = luxe_app_config();
    $mail = is_array($cfg['mail'] ?? null) ? $cfg['mail'] : [];
    if (!empty($mail['force_real_email'])) {
        return false;
    }
    if (!empty($mail['skip_email_send'])) {
        return true;
    }
    $base = luxe_http_host_base();

    return $base === 'localhost' || $base === '127.0.0.1' || $base === '::1';
}

/**
 * Send a 6-digit OTP by email (signup, profile email/phone flows).
 *
 * @return array{ok: bool, dev_code: ?string, dev_note: ?string}
 */
function luxe_deliver_verification_code_email(string $to, string $subject, string $code, string $footerSentence): array
{
    if (luxe_signup_email_skip_send()) {
        return [
            'ok' => true,
            'dev_code' => $code,
            'dev_note' => 'Email is not sent on localhost / dev mode. Use the code shown in the message below.',
        ];
    }

    $cfg = luxe_app_config();
    $mailCfg = is_array($cfg['mail'] ?? null) ? $cfg['mail'] : [];
    $smtpCfg = is_array($mailCfg['smtp'] ?? null) ? $mailCfg['smtp'] : [];
    $host = trim((string) ($smtpCfg['host'] ?? ''));

    $safeCode = h($code);
    $safeFooter = h($footerSentence);
    $html = '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;line-height:1.5;color:#1e1e3a;">'
        . '<p>Your verification code is:</p>'
        . '<p style="font-size:28px;font-weight:700;letter-spacing:0.25em;">' . $safeCode . '</p>'
        . '<p>This code expires in <strong>15 minutes</strong>. ' . $safeFooter . '</p>'
        . '</body></html>';
    $plain = "Your LUXE verification code is {$code}. It expires in 15 minutes. {$footerSentence}\n";

    // When mail.smtp.host is set, all verification emails go only through PHPMailer + SMTP (no mail() fallback).
    if ($host !== '') {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        } else {
            // Non-composer fallback: support bundled PHPMailer source files.
            $phpMailerSrc = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src';
            $exceptionFile = $phpMailerSrc . '/Exception.php';
            $smtpFile = $phpMailerSrc . '/SMTP.php';
            $mailerFile = $phpMailerSrc . '/PHPMailer.php';
            if (is_file($exceptionFile) && is_file($smtpFile) && is_file($mailerFile)) {
                require_once $exceptionFile;
                require_once $smtpFile;
                require_once $mailerFile;
            } else {
                error_log('LUXE mail: PHPMailer files missing (autoload and direct src include both unavailable).');

                return ['ok' => false, 'dev_code' => null, 'dev_note' => null];
            }
        }
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            error_log('LUXE mail: PHPMailer class not found after autoload.');

            return ['ok' => false, 'dev_code' => null, 'dev_note' => null];
        }
        try {
            [$fromEmail, $fromName] = luxe_mail_from_parts($mailCfg);
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int) ($smtpCfg['port'] ?? 587);
            $mail->Timeout = (int) ($smtpCfg['timeout'] ?? 30);
            $mail->SMTPDebug = (int) ($smtpCfg['debug'] ?? 0);
            $secure = strtolower((string) ($smtpCfg['secure'] ?? 'tls'));
            if ($secure === 'ssl' || $secure === 'smtps') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls' || $secure === 'starttls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $user = (string) ($smtpCfg['username'] ?? '');
            $pass = (string) ($smtpCfg['password'] ?? '');
            if ($user !== '' || $pass !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = $pass;
            }
            if (!empty($smtpCfg['allow_self_signed'])) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            $mail->setFrom($fromEmail, $fromName, false);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $plain;
            $mail->send();

            return ['ok' => true, 'dev_code' => null, 'dev_note' => null];
        } catch (\Throwable $e) {
            error_log('LUXE PHPMailer: ' . $e->getMessage());

            return ['ok' => false, 'dev_code' => null, 'dev_note' => null];
        }
    }

    [$fromEmail, $fromName] = luxe_mail_from_parts($mailCfg);
    $fromHeader = sprintf('%s <%s>', $fromName === '' ? 'LUXE' : $fromName, $fromEmail);
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . $fromHeader . "\r\n";
    $extra = '';
    if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $extra = '-f' . $fromEmail;
    }
    $sent = $extra !== ''
        ? @mail($to, $subject, $html, $headers, $extra)
        : @mail($to, $subject, $html, $headers);

    if ($sent) {
        return ['ok' => true, 'dev_code' => null, 'dev_note' => null];
    }

    if (luxe_http_host_base() === 'localhost' || luxe_http_host_base() === '127.0.0.1') {
        error_log("LUXE signup: mail() failed for {$to}; allowing dev fallback with code in API.");

        return [
            'ok' => true,
            'dev_code' => $code,
            'dev_note' => 'Email could not be sent from this machine; use the code below (see server log).',
        ];
    }

    return [
        'ok' => false,
        'dev_code' => null,
        'dev_note' => null,
    ];
}

/**
 * Send signup verification email, or skip on localhost / when configured.
 *
 * @return array{ok: bool, dev_code: ?string, dev_note: ?string}
 */
function luxe_deliver_signup_verification_code(string $to, string $code): array
{
    return luxe_deliver_verification_code_email(
        $to,
        'Your LUXE sign-up code',
        $code,
        'If you did not sign up for LUXE, ignore this email.'
    );
}
