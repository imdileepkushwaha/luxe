<?php

declare(strict_types=1);

/**
 * Read page and per-page from query string.
 *
 * @return array{page: int, perPage: int}
 */
function admin_pagination_read(int $defaultPerPage = 25, int $maxPerPage = 100): array
{
    $raw = (int) ($_GET['per_page'] ?? $defaultPerPage);
    $perPage = $raw;
    if ($perPage < 5 || $perPage > $maxPerPage) {
        $perPage = $defaultPerPage;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));

    return ['page' => $page, 'perPage' => $perPage];
}

/**
 * Clamp page to valid range and compute offset.
 *
 * @return array{page: int, totalPages: int, offset: int, perPage: int}
 */
function admin_pagination_resolve(int $total, int $page, int $perPage): array
{
    $perPage = max(1, $perPage);
    $totalPages = $total < 1 ? 1 : (int) max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'perPage' => $perPage,
    ];
}

/**
 * @return array{from: int, to: int}
 */
function admin_pagination_visible_range(int $total, int $page, int $perPage): array
{
    if ($total < 1) {
        return ['from' => 0, 'to' => 0];
    }
    $from = ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);

    return ['from' => $from, 'to' => $to];
}

/** @param array<string, scalar|array|null> $extra */
function admin_pagination_href(string $script, int $page, int $perPage, array $extra = []): string
{
    $q = array_merge($_GET, $extra, ['page' => $page, 'per_page' => $perPage]);

    return $script . '?' . http_build_query($q);
}
