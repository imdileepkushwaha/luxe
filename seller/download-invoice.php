<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);
$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    echo 'Invalid order id.';
    exit;
}

/**
 * Keep text PDF-safe for Type1 fonts.
 */
function seller_invoice_text_safe(string $value): string
{
    $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    $value = trim($value);
    return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
}

function seller_invoice_pdf_escape(string $value): string
{
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace('(', '\\(', $value);
    return str_replace(')', '\\)', $value);
}

/**
 * @param list<string> $lines
 */
function seller_invoice_build_pdf(array $lines): string
{
    $content = "BT\n/F1 11 Tf\n50 800 Td\n";
    foreach ($lines as $line) {
        $safe = seller_invoice_pdf_escape(seller_invoice_text_safe($line));
        $content .= '(' . $safe . ") Tj\n";
        $content .= "0 -16 Td\n";
    }
    $content .= "ET\n";

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Count 1 /Kids [3 0 R] >>';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $idx => $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= ($idx + 1) . " 0 obj\n" . $obj . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

$orderSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.payment_method, o.shipping_address, o.created_at, o.delivered_at,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Guest') AS customer_name,
            COALESCE(u.email, '-') AS customer_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?
       AND LOWER(TRIM(o.status)) = 'delivered'
       AND EXISTS (
         SELECT 1
         FROM order_items oi
         INNER JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = o.id
           AND p.seller_id = ?
       )
     LIMIT 1"
);
$orderSt->execute([$orderId, (int) $seller['id']]);
$order = $orderSt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404);
    echo 'Delivered order not found.';
    exit;
}

$itemSt = $pdo->prepare(
    "SELECT oi.name, oi.variant_text, oi.price, oi.qty
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? AND p.seller_id = ?
     ORDER BY oi.id ASC"
);
$itemSt->execute([(int) $order['id'], (int) $seller['id']]);
$items = $itemSt->fetchAll(PDO::FETCH_ASSOC);
if (!$items) {
    http_response_code(404);
    echo 'Invoice items not found.';
    exit;
}

$invoiceNo = 'INV-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($order['order_ref'] ?? '')) . '-' . (int) $order['id'];
$invoiceDateRaw = trim((string) ($order['delivered_at'] ?? '')) !== '' ? (string) $order['delivered_at'] : (string) $order['created_at'];
$invoiceDate = $invoiceDateRaw;
try {
    $invoiceDate = (new DateTimeImmutable($invoiceDateRaw))->format('M j, Y g:i A');
} catch (Throwable) {
}

$lines = [];
$lines[] = 'LUXE SELLER INVOICE';
$lines[] = '------------------------------------------------------------';
$lines[] = 'Invoice No: ' . $invoiceNo;
$lines[] = 'Order Ref : ' . seller_invoice_text_safe((string) ($order['order_ref'] ?? ''));
$lines[] = 'Order ID  : #' . (int) $order['id'];
$lines[] = 'Invoice Date: ' . seller_invoice_text_safe($invoiceDate);
$lines[] = 'Payment Method: ' . seller_invoice_text_safe((string) ($order['payment_method'] ?? '-'));
$lines[] = '------------------------------------------------------------';
$lines[] = 'Seller: ' . seller_invoice_text_safe((string) ($seller['full_name'] ?? 'Seller'));
$lines[] = 'Customer: ' . seller_invoice_text_safe((string) ($order['customer_name'] ?? 'Guest'));
$lines[] = 'Customer Email: ' . seller_invoice_text_safe((string) ($order['customer_email'] ?? '-'));
$lines[] = 'Shipping Address: ' . seller_invoice_text_safe((string) ($order['shipping_address'] ?? '-'));
$lines[] = '------------------------------------------------------------';
$lines[] = 'Item                                      Qty   Rate    Total';
$lines[] = '------------------------------------------------------------';

$subtotal = 0;
foreach ($items as $it) {
    $name = seller_invoice_text_safe((string) ($it['name'] ?? 'Item'));
    $variant = seller_invoice_text_safe(trim((string) ($it['variant_text'] ?? '')));
    $qty = max(1, (int) ($it['qty'] ?? 1));
    $price = max(0, (int) ($it['price'] ?? 0));
    $lineTotal = $qty * $price;
    $subtotal += $lineTotal;

    $label = $name;
    if ($variant !== '') {
        $label .= ' [' . $variant . ']';
    }
    if (strlen($label) > 38) {
        $label = substr($label, 0, 38);
    }
    $lines[] = str_pad($label, 40) . str_pad((string) $qty, 5, ' ', STR_PAD_LEFT) . str_pad((string) $price, 8, ' ', STR_PAD_LEFT) . str_pad((string) $lineTotal, 9, ' ', STR_PAD_LEFT);
}

$lines[] = '------------------------------------------------------------';
$lines[] = str_pad('Seller Items Subtotal (INR):', 52) . str_pad((string) $subtotal, 10, ' ', STR_PAD_LEFT);
$lines[] = str_pad('Tax / Charges:', 52) . str_pad('Included', 10, ' ', STR_PAD_LEFT);
$lines[] = str_pad('Invoice Total (INR):', 52) . str_pad((string) $subtotal, 10, ' ', STR_PAD_LEFT);
$lines[] = '------------------------------------------------------------';
$lines[] = 'This invoice reflects only this seller\'s items from the order.';
$lines[] = 'Generated at: ' . seller_invoice_text_safe((new DateTimeImmutable('now'))->format('M j, Y g:i A'));

$pdf = seller_invoice_build_pdf($lines);
$fileName = 'invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($order['order_ref'] ?? 'order')) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdf;
exit;

