<?php

/**
 * CLI: Razorpay keys + Orders API smoke test (no DB).
 * Run from repo root:
 *   C:\xampp\php\php.exe scripts\razorpay-smoke-test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/bootstrap.php';
require_once $root . '/includes/razorpay.php';

$pdo = db();

if (!luxe_razorpay_configured($pdo)) {
    fwrite(STDERR, "FAIL: Razorpay keys nahi mile.\n");
    fwrite(STDERR, "  • Admin → Settings → Payments: Gateway = Razorpay, Public + Secret keys, ya\n");
    fwrite(STDERR, "  • includes/config.php → razorpay.key_id / key_secret, ya\n");
    fwrite(STDERR, "  • PowerShell: \$env:LUXE_RAZORPAY_KEY_ID='rzp_test_...'; \$env:LUXE_RAZORPAY_KEY_SECRET='...'\n");
    fwrite(STDERR, "  Keys: https://dashboard.razorpay.com/app/keys (Test mode)\n");
    exit(1);
}

$c = luxe_razorpay_credentials($pdo);
$receipt = 'smoke_' . substr((string) time(), -10);
$notes = [
    'luxe_user_id' => '0',
    'luxe_nonce' => 'smoke_test',
];

$res = luxe_razorpay_create_order_api(100, $receipt, $notes, $c['key_id'], $c['key_secret']);

if (!($res['ok'] ?? false) || !is_array($res['order'] ?? null)) {
    fwrite(STDERR, 'FAIL: Razorpay order create — ' . ($res['error'] ?? 'unknown') . "\n");
    exit(2);
}

$order = $res['order'];
$id = (string) ($order['id'] ?? '');
$amount = (int) ($order['amount'] ?? 0);
$currency = (string) ($order['currency'] ?? '');

if ($id === '' || $amount !== 100 || strtoupper($currency) !== 'INR') {
    fwrite(STDERR, "FAIL: Unexpected response: id={$id} amount={$amount} currency={$currency}\n");
    exit(3);
}

echo "OK: Razorpay Orders API chal rahi hai.\n";
echo "  order_id: {$id}\n";
echo "  amount:   {$amount} paise (₹1 test)\n";
echo "  receipt:  {$receipt}\n";
echo "\nAb browser se checkout → Online → Place order try karo (test card Razorpay docs se).\n";
exit(0);
