<?php

declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

/**
 * CMS / CRM helpers — storefront page content, FAQ items and global brand/contact info.
 *
 * Pages are keyed by short slugs (contact, about, faq, terms, privacy, return_policy).
 * Each row stores the hero strip + optional body_html + meta description.
 */

const CMS_PAGE_KEYS = ['contact', 'about', 'faq', 'terms', 'privacy', 'return_policy'];

/**
 * @return array{page_key:string, hero_kicker:string, hero_title:string, hero_lead:string, body_html:string, meta_description:string, updated_at:?string}
 */
function cms_page_get(PDO $pdo, string $key, array $defaults = []): array
{
    $row = [];
    try {
        $st = $pdo->prepare(
            'SELECT page_key, hero_kicker, hero_title, hero_lead, body_html, meta_description, updated_at
             FROM cms_pages WHERE page_key = ? LIMIT 1'
        );
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $row = [];
    }

    return [
        'page_key'         => $key,
        'hero_kicker'      => (string) ($row['hero_kicker']      ?? ($defaults['hero_kicker']      ?? '')),
        'hero_title'       => (string) ($row['hero_title']       ?? ($defaults['hero_title']       ?? '')),
        'hero_lead'        => (string) ($row['hero_lead']        ?? ($defaults['hero_lead']        ?? '')),
        'body_html'        => (string) ($row['body_html']        ?? ($defaults['body_html']        ?? '')),
        'meta_description' => (string) ($row['meta_description'] ?? ($defaults['meta_description'] ?? '')),
        'updated_at'       => $row['updated_at'] ?? null,
    ];
}

function cms_page_save(PDO $pdo, string $key, array $data): void
{
    $kicker  = mb_substr(trim((string) ($data['hero_kicker']      ?? '')), 0, 120);
    $title   = mb_substr(trim((string) ($data['hero_title']       ?? '')), 0, 255);
    $lead    = mb_substr(trim((string) ($data['hero_lead']        ?? '')), 0, 1000);
    $body    = (string)  ($data['body_html'] ?? '');
    $meta    = mb_substr(trim((string) ($data['meta_description'] ?? '')), 0, 500);

    $st = $pdo->prepare(
        'INSERT INTO cms_pages (page_key, hero_kicker, hero_title, hero_lead, body_html, meta_description)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             hero_kicker = VALUES(hero_kicker),
             hero_title  = VALUES(hero_title),
             hero_lead   = VALUES(hero_lead),
             body_html   = VALUES(body_html),
             meta_description = VALUES(meta_description),
             updated_at  = CURRENT_TIMESTAMP'
    );
    $st->execute([$key, $kicker, $title, $lead, $body, $meta]);
}

/**
 * @return list<array{id:int, question:string, answer:string, sort_order:int, is_active:int}>
 */
function cms_faqs_all(PDO $pdo, bool $activeOnly = false): array
{
    try {
        $sql = 'SELECT id, question, answer, sort_order, is_active FROM cms_faqs';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'         => (int) $r['id'],
            'question'   => (string) $r['question'],
            'answer'     => (string) $r['answer'],
            'sort_order' => (int) $r['sort_order'],
            'is_active'  => (int) $r['is_active'],
        ];
    }

    return $out;
}

function cms_faq_save(PDO $pdo, ?int $id, string $question, string $answer, int $sortOrder, bool $isActive): int
{
    $question = mb_substr(trim($question), 0, 500);
    $answer   = trim($answer);
    if ($question === '' || $answer === '') {
        throw new InvalidArgumentException('Question and answer are required.');
    }
    $sortOrder = max(0, min(65535, $sortOrder));
    $active    = $isActive ? 1 : 0;

    if ($id !== null && $id > 0) {
        $st = $pdo->prepare(
            'UPDATE cms_faqs SET question = ?, answer = ?, sort_order = ?, is_active = ?
             WHERE id = ? LIMIT 1'
        );
        $st->execute([$question, $answer, $sortOrder, $active, $id]);

        return $id;
    }

    $st = $pdo->prepare(
        'INSERT INTO cms_faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, ?)'
    );
    $st->execute([$question, $answer, $sortOrder, $active]);

    return (int) $pdo->lastInsertId();
}

function cms_faq_delete(PDO $pdo, int $id): void
{
    if ($id <= 0) {
        return;
    }
    $st = $pdo->prepare('DELETE FROM cms_faqs WHERE id = ? LIMIT 1');
    $st->execute([$id]);
}

/* ---------- Brand / contact info (stored in site_settings) ---------- */

function site_brand_name(PDO $pdo): string
{
    $v = trim(site_setting_get($pdo, 'site_brand_name', 'LUXE'));

    return $v !== '' ? $v : 'LUXE';
}

function site_logo_path(PDO $pdo): string
{
    return trim(site_setting_get($pdo, 'site_logo_path', ''));
}

function site_contact_email(PDO $pdo): string
{
    return trim(site_setting_get($pdo, 'site_contact_email', 'info@luxe.com'));
}

function site_contact_phone(PDO $pdo): string
{
    return trim(site_setting_get($pdo, 'site_contact_phone', '+123 324 5879 39'));
}

function site_contact_address(PDO $pdo): string
{
    return trim(site_setting_get($pdo, 'site_contact_address', '37 W 24th St, New York, NY'));
}

function site_contact_hours(PDO $pdo): string
{
    return trim(site_setting_get($pdo, 'site_contact_hours', 'Mon–Sat, 9:00–18:00 IST'));
}

/**
 * @return array{brand:string, logo:string, email:string, phone:string, address:string, hours:string}
 */
function site_contact_bundle(PDO $pdo): array
{
    return [
        'brand'   => site_brand_name($pdo),
        'logo'    => site_logo_path($pdo),
        'email'   => site_contact_email($pdo),
        'phone'   => site_contact_phone($pdo),
        'address' => site_contact_address($pdo),
        'hours'   => site_contact_hours($pdo),
    ];
}

/**
 * Sanitised tel: href (strip spaces and disallowed chars).
 */
function site_contact_phone_href(string $phone): string
{
    $clean = preg_replace('/[^0-9+]/', '', $phone) ?? '';

    return $clean;
}
