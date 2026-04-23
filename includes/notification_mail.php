<?php

declare(strict_types=1);

require_once __DIR__ . '/signup_mail.php';

function luxe_mail_status_label(string $status): string
{
    return match (strtolower(trim($status))) {
        'processing' => 'Order placed',
        'confirmed' => 'Order confirmed',
        'shipped' => 'Order shipped',
        'out' => 'Out for delivery',
        'delivered' => 'Order delivered',
        'cancelled' => 'Order cancelled',
        default => ucfirst(trim($status)) !== '' ? ucfirst(trim($status)) : 'Order update',
    };
}

/**
 * @param list<string> $metaLines
 */
function luxe_mail_layout(string $title, string $subtitle, array $metaLines, string $bodyHtml, string $ctaHtml = ''): string
{
    $safeTitle = h($title);
    $safeSubtitle = h($subtitle);
    $metaHtml = '';
    foreach ($metaLines as $line) {
        $metaHtml .= '<tr><td style="padding:0 0 8px;">'
            . '<span style="display:inline-block;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;border-radius:999px;padding:6px 11px;font-size:12px;font-weight:600;letter-spacing:.01em;">'
            . h($line)
            . '</span>'
            . '</td></tr>';
    }

    $logoUrl = luxe_app_base_url() . '/images/logo.png';
    $preheader = 'LUXE update: ' . $title . ' - ' . $subtitle;

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f3f4f8;font-family:Inter,Segoe UI,Arial,sans-serif;color:#101828;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . h($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:linear-gradient(180deg,#fff1f2 0%,#f3f4f8 35%);padding:28px 0;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #f1f5f9;box-shadow:0 14px 32px rgba(15,23,42,.08);">'
        . '<tr><td style="padding:22px 26px;background:linear-gradient(120deg,#7f1d1d 0%,#dc2626 58%,#fb7185 100%);color:#fff;">'
        . '<div style="display:flex;align-items:center;gap:10px;margin:0 0 8px;">'
        . '<img src="' . h($logoUrl) . '" alt="LUXE" width="32" height="32" style="display:block;border-radius:8px;background:#fff;object-fit:cover;" />'
        . '<p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.9;">LUXE Notification</p>'
        . '</div>'
        . '<h1 style="margin:0;font-size:24px;line-height:1.28;font-weight:700;">' . $safeTitle . '</h1>'
        . '<p style="margin:8px 0 0;font-size:14px;line-height:1.6;opacity:.96;">' . $safeSubtitle . '</p>'
        . '</td></tr>'
        . '<tr><td style="padding:5px 26px 0;"><div style="height:4px;background:linear-gradient(90deg,#ef4444,#fb7185,#fda4af);border-radius:999px;"></div></td></tr>'
        . '<tr><td style="padding:18px 26px 2px;"><table role="presentation" cellspacing="0" cellpadding="0">' . $metaHtml . '</table></td></tr>'
        . '<tr><td style="padding:4px 26px 8px;font-size:15px;line-height:1.72;color:#334155;">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:2px 26px 22px;">' . $ctaHtml . '</td></tr>'
        . '<tr><td style="padding:16px 26px 22px;border-top:1px solid #eaecf0;background:#fafbff;color:#667085;font-size:12px;line-height:1.6;">'
        . '<strong style="color:#475467;display:block;margin-bottom:4px;">LUXE Customer Care</strong>'
        . 'This is an automated mail from LUXE. Yeh system generated message hai. Help ke liye is email ka reply karein.'
        . '</td></tr>'
        . '</table>'
        . '</td></tr></table></body></html>';
}

function luxe_app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }

    return $scheme . '://' . $host;
}

function luxe_mail_button(string $url, string $label, bool $secondary = false): string
{
    $style = $secondary
        ? 'display:inline-block;padding:11px 16px;border-radius:10px;background:#ffffff;color:#b42318;text-decoration:none;font-weight:600;font-size:14px;border:1px solid #fda4af;'
        : 'display:inline-block;padding:11px 16px;border-radius:10px;background:linear-gradient(120deg,#b42318,#ef4444);color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border:1px solid #ef4444;';

    return '<a href="' . h($url) . '" style="' . $style . '">'
        . h($label)
        . '</a>';
}

function luxe_mail_cta_group(array $buttons): string
{
    if ($buttons === []) {
        return '';
    }
    $html = '<div style="margin-top:8px;">';
    foreach ($buttons as $btn) {
        $url = trim((string) ($btn['url'] ?? ''));
        $label = trim((string) ($btn['label'] ?? ''));
        if ($url === '' || $label === '') {
            continue;
        }
        $isSecondary = $html !== '<div style="margin-top:8px;">';
        $html .= '<span style="display:inline-block;margin-right:10px;margin-bottom:8px;">' . luxe_mail_button($url, $label, $isSecondary) . '</span>';
    }
    $html .= '</div>';

    return $html;
}

function luxe_send_professional_mail(
    string $to,
    string $subject,
    string $title,
    string $subtitle,
    array $metaLines,
    string $bodyHtml,
    array $ctaButtons = []
): bool
{
    $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));
    $footer = 'Thank you for choosing LUXE.';
    if ($text !== '') {
        $text .= "\n\n";
    }
    $text .= $footer;

    return luxe_send_transactional_email(
        $to,
        $subject,
        luxe_mail_layout($title, $subtitle, $metaLines, $bodyHtml, luxe_mail_cta_group($ctaButtons)),
        $text
    );
}

function luxe_send_order_update_email(string $to, string $customerName, string $orderRef, string $status): bool
{
    $statusLabel = luxe_mail_status_label($status);
    $base = luxe_app_base_url();
    $firstName = trim($customerName) !== '' ? trim($customerName) : 'Customer';
    $body = '<p style="margin:0 0 12px;">Hi <strong>' . h($firstName) . '</strong>,</p>'
        . '<p style="margin:0 0 12px;">Your order status has been updated to <strong>' . h($statusLabel) . '</strong>.</p>'
        . '<p style="margin:0 0 12px;">Hum aapko har important delivery stage par update bhejte rahenge.</p>'
        . '<p style="margin:0 0 0;">Thank you for shopping with LUXE.</p>';

    return luxe_send_professional_mail(
        $to,
        'LUXE Order Update - ' . $statusLabel . ' (' . $orderRef . ')',
        $statusLabel,
        'Order #' . $orderRef,
        ['Order Reference: ' . $orderRef, 'Current Status: ' . $statusLabel],
        $body,
        [
            ['url' => $base . '/orders.php', 'label' => 'Track My Order'],
            ['url' => $base . '/profile.php', 'label' => 'My Account'],
        ]
    );
}

function luxe_send_return_update_email(string $to, string $customerName, string $orderRef, string $returnStatus): bool
{
    $friendly = ucwords(str_replace('_', ' ', strtolower(trim($returnStatus))));
    $base = luxe_app_base_url();
    $body = '<p style="margin:0 0 12px;">Hi <strong>' . h($customerName !== '' ? $customerName : 'Customer') . '</strong>,</p>'
        . '<p style="margin:0 0 12px;">Your return request has been updated to <strong>' . h($friendly) . '</strong>.</p>'
        . '<p style="margin:0 0 12px;">Zarurat padne par pickup aur refund updates bhi isi email par milte rahenge.</p>'
        . '<p style="margin:0;">Thanks for your patience and trust.</p>';

    return luxe_send_professional_mail(
        $to,
        'LUXE Return Update - ' . $friendly . ' (' . $orderRef . ')',
        'Return request update',
        'Order #' . $orderRef,
        ['Order Reference: ' . $orderRef, 'Return Status: ' . $friendly],
        $body,
        [
            ['url' => $base . '/orders.php', 'label' => 'View Order Details'],
            ['url' => $base . '/contact-us.php', 'label' => 'Get Support'],
        ]
    );
}

function luxe_send_welcome_email(string $to, string $name, string $accountType): bool
{
    $cleanType = strtolower(trim($accountType)) === 'seller' ? 'Seller' : 'User';
    $base = luxe_app_base_url();
    $display = trim($name) !== '' ? trim($name) : 'there';
    $body = '<p style="margin:0 0 12px;">Hi <strong>' . h($display) . '</strong>,</p>'
        . '<p style="margin:0 0 12px;">Congratulations! Your LUXE ' . h($cleanType) . ' registration is successful.</p>'
        . '<p style="margin:0 0 12px;">Badhaai ho! Aapka account successfully register ho gaya hai.</p>'
        . '<p style="margin:0;">We are excited to have you with us. Dashboard open karke abhi start karein.</p>';
    $buttons = $cleanType === 'Seller'
        ? [
            ['url' => $base . '/seller/login.php', 'label' => 'Open Seller Login'],
            ['url' => $base . '/seller/profile.php', 'label' => 'Seller Profile'],
        ]
        : [
            ['url' => $base . '/profile.php', 'label' => 'Open My Profile'],
            ['url' => $base . '/index.php', 'label' => 'Continue Shopping'],
        ];

    return luxe_send_professional_mail(
        $to,
        'Welcome to LUXE - Registration Successful',
        'Congratulations and welcome',
        'Your ' . $cleanType . ' account is ready',
        ['Account Type: ' . $cleanType, 'Registered Email: ' . $to],
        $body,
        $buttons
    );
}
