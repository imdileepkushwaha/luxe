<?php

declare(strict_types=1);

/** @return list<array<string,mixed>> */
function addresses_fetch_for_user(PDO $pdo, int $userId): array
{
    $st = $pdo->prepare(
        'SELECT id, type_label AS type, full_name AS name, phone, line1, line2, city, state, pin, is_default AS isDefault
         FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC'
    );
    $st->execute([$userId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['isDefault'] = (bool) (int) $r['isDefault'];
    }

    return $rows;
}

/** @return array<string,mixed>|null */
function addresses_get_for_user(PDO $pdo, int $userId, int $addressId): ?array
{
    $st = $pdo->prepare(
        'SELECT id, type_label AS type, full_name AS name, phone, line1, line2, city, state, pin, is_default AS isDefault
         FROM user_addresses WHERE user_id = ? AND id = ? LIMIT 1'
    );
    $st->execute([$userId, $addressId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        return null;
    }
    $r['id'] = (int) $r['id'];
    $r['isDefault'] = (bool) (int) $r['isDefault'];

    return $r;
}

function addresses_normalize_type(string $raw): string
{
    $t = trim($raw);
    if (!in_array($t, ['Home', 'Work', 'Other'], true)) {
        return 'Home';
    }

    return $t;
}

/** @param array{type?:string,name?:string,phone?:string,line1?:string,line2?:string,city?:string,state?:string,pin?:string,is_default?:bool} $d */
function addresses_validate_payload(array $d): array
{
    $type = addresses_normalize_type((string) ($d['type'] ?? 'Home'));
    $name = trim((string) ($d['name'] ?? ''));
    $phone = trim((string) ($d['phone'] ?? ''));
    $line1 = trim((string) ($d['line1'] ?? ''));
    $line2 = trim((string) ($d['line2'] ?? ''));
    $city = trim((string) ($d['city'] ?? ''));
    $state = trim((string) ($d['state'] ?? ''));
    $pin = trim((string) ($d['pin'] ?? ''));
    $isDefault = filter_var($d['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($name === '' || $line1 === '' || $city === '' || $state === '' || $pin === '') {
        return ['ok' => false, 'message' => 'Name, address line 1, city, state, and PIN are required.'];
    }
    if (strlen($name) > 255 || strlen($line1) > 255 || strlen($line2) > 255) {
        return ['ok' => false, 'message' => 'Address fields are too long.'];
    }
    if (strlen($city) > 100 || strlen($state) > 100 || strlen($pin) > 20 || strlen($phone) > 40) {
        return ['ok' => false, 'message' => 'City, state, PIN, or phone is too long.'];
    }

    return [
        'ok' => true,
        'type' => $type,
        'name' => $name,
        'phone' => $phone,
        'line1' => $line1,
        'line2' => $line2,
        'city' => $city,
        'state' => $state,
        'pin' => $pin,
        'is_default' => $isDefault,
    ];
}

function addresses_clear_default_for_user(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE user_addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
}

/** @param array{type:string,name:string,phone:string,line1:string,line2:string,city:string,state:string,pin:string,is_default:bool} $v */
function addresses_insert(PDO $pdo, int $userId, array $v): int
{
    $stc = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?');
    $stc->execute([$userId]);
    $count = (int) $stc->fetchColumn();
    $isDef = $v['is_default'] || $count === 0;

    $pdo->beginTransaction();
    try {
        if ($isDef) {
            addresses_clear_default_for_user($pdo, $userId);
        }
        $ins = $pdo->prepare(
            'INSERT INTO user_addresses (user_id, type_label, full_name, phone, line1, line2, city, state, pin, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $userId,
            $v['type'],
            $v['name'],
            $v['phone'],
            $v['line1'],
            $v['line2'],
            $v['city'],
            $v['state'],
            $v['pin'],
            $isDef ? 1 : 0,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        addresses_repair_default($pdo, $userId);

        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @param array{type:string,name:string,phone:string,line1:string,line2:string,city:string,state:string,pin:string,is_default:bool} $v */
function addresses_update(PDO $pdo, int $userId, int $addressId, array $v): bool
{
    $pdo->beginTransaction();
    try {
        if ($v['is_default']) {
            addresses_clear_default_for_user($pdo, $userId);
        }
        $st = $pdo->prepare(
            'UPDATE user_addresses SET type_label = ?, full_name = ?, phone = ?, line1 = ?, line2 = ?, city = ?, state = ?, pin = ?, is_default = ?
             WHERE user_id = ? AND id = ?'
        );
        $st->execute([
            $v['type'],
            $v['name'],
            $v['phone'],
            $v['line1'],
            $v['line2'],
            $v['city'],
            $v['state'],
            $v['pin'],
            $v['is_default'] ? 1 : 0,
            $userId,
            $addressId,
        ]);
        if ($st->rowCount() === 0) {
            $pdo->rollBack();

            return false;
        }
        $pdo->commit();
        addresses_repair_default($pdo, $userId);

        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function addresses_delete(PDO $pdo, int $userId, int $addressId): bool
{
    $st = $pdo->prepare('SELECT is_default FROM user_addresses WHERE user_id = ? AND id = ? LIMIT 1');
    $st->execute([$userId, $addressId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $wasDefault = (int) $row['is_default'] === 1;

    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM user_addresses WHERE user_id = ? AND id = ?');
        $del->execute([$userId, $addressId]);
        if ($del->rowCount() === 0) {
            $pdo->rollBack();

            return false;
        }
        if ($wasDefault) {
            $pick = $pdo->prepare('SELECT id FROM user_addresses WHERE user_id = ? ORDER BY id ASC LIMIT 1');
            $pick->execute([$userId]);
            $next = $pick->fetchColumn();
            if ($next !== false) {
                $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE user_id = ? AND id = ?')->execute([$userId, (int) $next]);
            }
        }
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function addresses_set_default(PDO $pdo, int $userId, int $addressId): bool
{
    $pdo->beginTransaction();
    try {
        $chk = $pdo->prepare('SELECT id FROM user_addresses WHERE user_id = ? AND id = ? LIMIT 1');
        $chk->execute([$userId, $addressId]);
        if (!$chk->fetchColumn()) {
            $pdo->rollBack();

            return false;
        }
        addresses_clear_default_for_user($pdo, $userId);
        $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE user_id = ? AND id = ?')->execute([$userId, $addressId]);
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Format a saved address row for order shipping text. */
function addresses_format_shipping(array $addr): string
{
    $name = trim((string) ($addr['name'] ?? ''));
    $line1 = trim((string) ($addr['line1'] ?? ''));
    $line2 = trim((string) ($addr['line2'] ?? ''));
    $city = trim((string) ($addr['city'] ?? ''));
    $state = trim((string) ($addr['state'] ?? ''));
    $pin = trim((string) ($addr['pin'] ?? ''));
    $phone = trim((string) ($addr['phone'] ?? ''));

    $line = $line1;
    if ($line2 !== '') {
        $line .= ', ' . $line2;
    }
    $cityLine = trim($city . ', ' . $state . ' ' . $pin);
    $parts = array_filter([
        $name,
        $line,
        $cityLine,
        $phone !== '' ? 'Ph: ' . $phone : '',
    ], static fn(string $p): bool => $p !== '');

    return implode(' · ', $parts);
}

/** If user has addresses but none marked default, mark the first row default. */
function addresses_repair_default(PDO $pdo, int $userId): void
{
    $c = $pdo->prepare('SELECT COUNT(*) FROM user_addresses WHERE user_id = ? AND is_default = 1');
    $c->execute([$userId]);
    if ((int) $c->fetchColumn() > 0) {
        return;
    }
    $p = $pdo->prepare('SELECT id FROM user_addresses WHERE user_id = ? ORDER BY id ASC LIMIT 1');
    $p->execute([$userId]);
    $id = $p->fetchColumn();
    if ($id !== false) {
        $pdo->prepare('UPDATE user_addresses SET is_default = 1 WHERE user_id = ? AND id = ?')->execute([$userId, (int) $id]);
    }
}
