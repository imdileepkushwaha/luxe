<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$uid = auth_user_id();
if ($uid === null) {
    header('Location: login.php');
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatInvoiceDt(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('d/m/Y');
    } catch (Throwable) {
        return $raw;
    }
}

$orderRef = trim((string) ($_GET['order_ref'] ?? ''));
if ($orderRef === '') {
    http_response_code(400);
    echo 'Invalid order reference.';
    exit;
}

$orderSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at, o.delivered_at,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Customer') AS customer_name,
            COALESCE(u.email, '-') AS customer_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.user_id = ?
       AND o.order_ref = ?
       AND LOWER(TRIM(o.status)) = 'delivered'
     LIMIT 1"
);
$orderSt->execute([(int) $uid, $orderRef]);
$order = $orderSt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404);
    echo 'Delivered order not found.';
    exit;
}

$itemSt = $pdo->prepare(
    "SELECT oi.name, oi.variant_text, oi.price, oi.qty, COALESCE(p.original_price, oi.price) AS original_price
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC"
);
$itemSt->execute([(int) $order['id']]);
$items = $itemSt->fetchAll(PDO::FETCH_ASSOC);
if (!$items) {
    http_response_code(404);
    echo 'Order items not found.';
    exit;
}

$invoiceNo = '#INV' . str_pad((string) (int) $order['id'], 4, '0', STR_PAD_LEFT);
$invoiceDateRaw = trim((string) ($order['delivered_at'] ?? '')) !== '' ? (string) $order['delivered_at'] : (string) $order['created_at'];
$invoiceDate = formatInvoiceDt($invoiceDateRaw);

$subtotal = 0;
$discount = 0;
foreach ($items as $it) {
    $qty = max(1, (int) ($it['qty'] ?? 1));
    $price = max(0, (int) ($it['price'] ?? 0));
    $orig = max(0, (int) ($it['original_price'] ?? 0));
    $basePrice = $orig > 0 ? max($orig, $price) : $price;
    $subtotal += $basePrice * $qty;
    $discount += max(0, $basePrice - $price) * $qty;
}
$taxable = max(0, $subtotal - $discount);
$vatRate = 0.05;
$grandTotal = $taxable;
$vat = (int) round($grandTotal - ($grandTotal / (1 + $vatRate)));
if ($grandTotal <= 0) {
    $fallbackTotal = max(0, (int) ($order['total_amount'] ?? 0));
    if ($fallbackTotal > 0) {
        $taxable = $fallbackTotal;
        $grandTotal = $fallbackTotal;
        $vat = (int) round($grandTotal - ($grandTotal / (1 + $vatRate)));
    }
}

$paymentStatus = strtolower(trim((string) ($order['payment_method'] ?? ''))) === 'cod' ? 'Paid on delivery' : 'Paid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?= e((string) $order['order_ref']) ?></title>
  <style>
    :root { --line:#e5e7eb; --muted:#6b7280; --text:#111827; --accent:#dc2626; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Outfit", Arial, sans-serif; background: #f3f4f6; color: var(--text); padding: 22px; font-size: 14px; }
    .invoice { max-width: 1080px; margin: 0 auto; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 20px; }
    .header { display:flex; justify-content:space-between; gap:20px; border-bottom:1px solid var(--line); padding-bottom:14px; }
    .brand h1 { margin:0; font-size:34px; letter-spacing:.04em; }
    .brand p { margin:6px 0 0; color:var(--muted); }
    .meta { text-align:right; min-width:240px; }
    .meta div { margin-bottom:8px; color:#374151; }
    .meta strong { color:#111827; }
    .meta .accent { color: var(--accent); font-weight:700; }
    .row3 { display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; padding:16px 0; border-bottom:1px solid var(--line); }
    .box h3 { margin:0 0 8px; font-size:16px; }
    .box p { margin:2px 0; color:#374151; line-height:1.4; }
    .pill { display:inline-flex; background:#dc2626; color:#fff; border-radius:999px; padding:3px 10px; font-size:12px; font-weight:700; }
    .section-title { margin:14px 0; font-size:17px; }
    table { width:100%; border-collapse:collapse; border:1px solid var(--line); }
    th, td { border-bottom:1px solid var(--line); padding:10px; text-align:left; }
    th { background:#f9fafb; font-size:13px; }
    td { font-size:13px; color:#1f2937; }
    .summary-wrap { display:grid; grid-template-columns: 1fr 360px; gap:20px; margin-top:14px; }
    .terms h4 { margin:0 0 6px; font-size:18px; }
    .terms p { margin:0 0 12px; color:var(--muted); line-height:1.5; }
    .totals { border-top:1px solid var(--line); padding-top:6px; }
    .totals .line { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #e5e7eb; }
    .totals .line strong { font-size:20px; }
    .foot { margin-top:18px; border-top:1px solid var(--line); padding-top:14px; text-align:center; color:var(--muted); }
    .actions { margin-top:16px; display:flex; justify-content:flex-end; gap:10px; }
    .btn { border:1px solid var(--line); background:#fff; color:#111827; border-radius:8px; padding:9px 12px; cursor:pointer; font-weight:600; text-decoration:none; }
    .btn.primary { background:#dc2626; border-color:#dc2626; color:#fff; }
    @media print {
      body { background:#fff; padding:0; }
      .invoice { border:none; border-radius:0; max-width:none; padding:0; }
      .actions { display:none; }
    }
    @media (max-width:900px) {
      .row3, .summary-wrap { grid-template-columns:1fr; }
      .meta { text-align:left; }
    }
  </style>
</head>
<body>
  <div class="invoice">
    <div class="header">
      <div class="brand">
        <h1>LUXE</h1>
        <p>22 Fashion Street, New Delhi, India</p>
      </div>
      <div class="meta">
        <div><strong>Invoice No :</strong> <span class="accent"><?= e($invoiceNo) ?></span></div>
        <div><strong>Invoice Date :</strong> <?= e($invoiceDate) ?></div>
      </div>
    </div>

    <div class="row3">
      <div class="box">
        <h3>From</h3>
        <p><strong>LUXE Store</strong></p>
        <p>Email: support@luxe.com</p>
        <p>Phone: +91 98765 43210</p>
      </div>
      <div class="box">
        <h3>To</h3>
        <p><strong><?= e((string) ($order['customer_name'] ?? 'Customer')) ?></strong></p>
        <p>Email: <?= e((string) ($order['customer_email'] ?? '-')) ?></p>
        <p><?= e((string) ($order['shipping_address'] ?? '-')) ?></p>
      </div>
      <div class="box">
        <h3>Payment Status</h3>
        <p><span class="pill"><?= e($paymentStatus) ?></span></p>
        <p>Method: <?= e((string) ($order['payment_method'] ?? '-')) ?></p>
        <p>Order Ref: <?= e((string) ($order['order_ref'] ?? '-')) ?></p>
      </div>
    </div>

    <p class="section-title">Invoice For : Order <?= e((string) ($order['order_ref'] ?? '-')) ?></p>

    <table>
      <thead>
        <tr>
          <th>Job Description</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Discount</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <?php
          $qty = max(1, (int) ($it['qty'] ?? 1));
          $price = max(0, (int) ($it['price'] ?? 0));
          $orig = max(0, (int) ($it['original_price'] ?? $price));
          $lineDiscount = max(0, $orig - $price) * $qty;
          $lineTotal = $price * $qty;
          $itemLabel = trim((string) ($it['name'] ?? 'Item'));
          $variant = trim((string) ($it['variant_text'] ?? ''));
          if ($variant !== '') {
              $itemLabel .= ' (' . $variant . ')';
          }
        ?>
        <tr>
          <td><?= e($itemLabel) ?></td>
          <td><?= $qty ?></td>
          <td>₹<?= number_format($price) ?></td>
          <td>₹<?= number_format($lineDiscount) ?></td>
          <td>₹<?= number_format($lineTotal) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="summary-wrap">
      <div class="terms">
        <h4>Terms and Conditions</h4>
        <p>Please keep this invoice for records. Returns/refunds follow LUXE return policy.</p>
        <h4>Notes</h4>
        <p>Please quote invoice number when contacting support.</p>
      </div>
      <div class="totals">
        <div class="line"><span>Sub Total</span><span>₹<?= number_format($subtotal) ?></span></div>
        <div class="line"><span>Discount</span><span>₹<?= number_format($discount) ?></span></div>
        <div class="line"><span>VAT Included (5%)</span><span>₹<?= number_format($vat) ?></span></div>
        <div class="line"><strong>Total Amount</strong><strong>₹<?= number_format($grandTotal) ?></strong></div>
      </div>
    </div>

    <div class="foot">
      <p><strong>LUXE</strong> · Payment via UPI/Card/COD (as applicable)</p>
      <p>Bank Name: HDFC Bank · IFSC: HDFC0000001 · A/C: XXXX-XXXX-9879</p>
    </div>

    <div class="actions" data-html2canvas-ignore="true">
      <a class="btn" href="javascript:window.close();">Close</a>
      <button class="btn primary" type="button" onclick="downloadPDF()">Download PDF</button>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    function downloadPDF() {
      const element = document.querySelector('.invoice');
      const opt = {
        margin:       0.5,
        filename:     'Invoice_<?= e((string) $order['order_ref']) ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
      };
      html2pdf().set(opt).from(element).save();
    }
    
    // Automatically trigger PDF generation when page loads
    window.addEventListener('load', () => {
        setTimeout(downloadPDF, 500);
    });
  </script>
</body>
</html>

