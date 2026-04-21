<?php

declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Strip inline styles from seller-provided description HTML so theme (dark/light) controls color.
 */
function luxe_sanitize_product_description_html(string $html): string
{
    $out = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/iu', '', $html);
    $out = preg_replace('/\sstyle\s*=\s*[^>\s]+/iu', '', $out ?? $html);

    return $out ?? $html;
}

/** Mask email for UI (e.g. ra***@example.com). */
function luxe_mask_email(string $email): string
{
    $email = trim($email);
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }
    [$local, $domain] = $parts;
    $len = strlen($local);
    if ($len <= 1) {
        return '*@' . $domain;
    }
    $show = $len <= 3 ? 1 : 2;

    return substr($local, 0, $show) . str_repeat('*', max(1, $len - $show)) . '@' . $domain;
}
