<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

$orderNum = $_GET['order'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=?");
$stmt->execute([$orderNum]);
$order = $stmt->fetch();

if (!$order) {
  die('Order not found');
}

$stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id=?");
$stmtItems->execute([$order['id']]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Invoice - <?= $order['order_number'] ?></title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Arial', sans-serif;
      background: #fff;
      color: #1a1a2e;
      font-size: 13px;
    }

    .invoice-wrapper {
      max-width: 800px;
      margin: 0 auto;
      padding: 30px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 20px;
      border-bottom: 2px solid #6C63FF;
      margin-bottom: 24px;
    }

    .brand {
      font-size: 22px;
      font-weight: 900;
      color: #6C63FF;
      letter-spacing: -0.5px;
    }

    .brand-sub {
      font-size: 11px;
      color: #888;
      margin-top: 3px;
    }

    .invoice-title {
      font-size: 24px;
      font-weight: 800;
      color: #1a1a2e;
      text-align: right;
    }

    .invoice-num {
      font-size: 12px;
      color: #6C63FF;
      font-weight: 700;
      margin-top: 4px;
    }

    .badges {
      display: flex;
      gap: 8px;
      margin-top: 8px;
      justify-content: flex-end;
    }

    .badge {
      padding: 3px 10px;
      border-radius: 50px;
      font-size: 10px;
      font-weight: 700;
      border: 1px solid;
    }

    .badge-status {
      background: #e8f5e9;
      color: #2e7d32;
      border-color: #2e7d32;
    }

    .badge-payment {
      background: #e3f2fd;
      color: #1565c0;
      border-color: #1565c0;
    }

    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 24px;
    }

    .info-box {
      background: #f8f8ff;
      border-radius: 8px;
      padding: 16px;
      border: 1px solid #e8e8f0;
    }

    .info-box h4 {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: #6C63FF;
      margin-bottom: 10px;
    }

    .info-box p {
      font-size: 12px;
      color: #555;
      line-height: 1.7;
    }

    .info-box strong {
      color: #1a1a2e;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
    }

    th {
      background: #6C63FF;
      color: #fff;
      padding: 10px 12px;
      text-align: left;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td {
      padding: 10px 12px;
      border-bottom: 1px solid #f0f0f8;
      font-size: 12px;
    }

    tr:nth-child(even) td {
      background: #f8f8ff;
    }

    .text-right {
      text-align: right;
    }

    .summary-table {
      width: 300px;
      margin-left: auto;
    }

    .summary-table tr td {
      border: none;
      padding: 5px 12px;
    }

    .summary-table .total-row td {
      border-top: 2px solid #6C63FF;
      font-weight: 800;
      font-size: 14px;
      color: #6C63FF;
      padding-top: 10px;
    }

    .footer {
      border-top: 1px solid #e0e0f0;
      padding-top: 20px;
      margin-top: 20px;
      text-align: center;
      color: #999;
      font-size: 11px;
    }

    .stamp {
      display: inline-block;
      border: 2px solid #2e7d32;
      color: #2e7d32;
      border-radius: 8px;
      padding: 6px 20px;
      font-size: 14px;
      font-weight: 800;
      transform: rotate(-5deg);
      margin: 20px 0;
      opacity: 0.8;
    }

    @media print {
      .no-print {
        display: none;
      }

      body {
        font-size: 12px;
      }

      .invoice-wrapper {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <div class="invoice-wrapper">
    <div class="no-print"
      style="background:#6C63FF; color:#fff; padding:10px 20px; margin:-30px -30px 30px; display:flex; justify-content:space-between; align-items:center; border-radius:0;">
      <strong>🧾 Invoice Preview</strong>
      <div style="display:flex; gap:10px;">
        <button onclick="window.print()"
          style="background:#fff; color:#6C63FF; border:none; padding:6px 16px; border-radius:6px; font-weight:700; cursor:pointer;">🖨️
          Print</button>
        <a href="javascript:window.close()"
          style="color:rgba(255,255,255,0.8); text-decoration:none; padding:6px 12px;">✕ Close</a>
      </div>
    </div>

    <!-- Header -->
    <div class="header">
      <div>
        <div class="brand">✦ MIZ MAX</div>
        <div class="brand-sub">Premium Online Shopping</div>
        <div style="margin-top:8px; font-size:11px; color:#888; line-height:1.6;">support@MIZ MAX.com<br>+91 6282 626
          989<br>Mumbai, India</div>
      </div>
      <div style="text-align:right;">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-num">#<?= $order['order_number'] ?></div>
        <div class="badges">
          <span class="badge badge-status"><?= strtoupper($order['order_status']) ?></span>
          <span class="badge badge-payment"><?= strtoupper($order['payment_method']) ?></span>
        </div>
      </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
      <div class="info-box">
        <h4>📦 Bill To</h4>
        <p><strong><?= htmlspecialchars($order['name']) ?></strong><br>
          <?= htmlspecialchars($order['address']) ?><br>
          <?= htmlspecialchars($order['city']) ?>, <?= htmlspecialchars($order['state']) ?> –
          <?= htmlspecialchars($order['pincode']) ?><br>
          📞 <?= htmlspecialchars($order['phone']) ?><br>
          ✉ <?= htmlspecialchars($order['email']) ?>
        </p>
      </div>
      <div class="info-box">
        <h4>📋 Invoice Details</h4>
        <p>
          <strong>Invoice No:</strong> <?= $order['order_number'] ?><br>
          <strong>Date:</strong> <?= date('d M Y', strtotime($order['created_at'])) ?><br>
          <strong>Payment:</strong> <?= strtoupper($order['payment_method']) ?><br>
          <strong>Status:</strong> <?= ucfirst($order['order_status']) ?><br>
          <?php if ($order['coupon_code']): ?><strong>Coupon:</strong> <?= $order['coupon_code'] ?><?php endif; ?>
        </p>
      </div>
    </div>

    <!-- Items Table -->
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Product</th>
          <th class="text-right">Price</th>
          <th class="text-right">Qty</th>
          <th class="text-right">Discount</th>
          <th class="text-right">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $item): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
            <td class="text-right"><?= formatPrice($item['price']) ?></td>
            <td class="text-right"><?= $item['quantity'] ?></td>
            <td class="text-right"><?= $item['discount_percent'] > 0 ? $item['discount_percent'] . '%' : '–' ?></td>
            <td class="text-right"><strong><?= formatPrice($item['total']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Summary -->
    <table class="summary-table">
      <tr>
        <td style="color:#777;">Subtotal</td>
        <td class="text-right"><?= formatPrice($order['subtotal']) ?></td>
      </tr>
      <?php if ($order['discount'] > 0): ?>
        <tr>
          <td style="color:#27ae60;">Coupon Discount</td>
          <td class="text-right" style="color:#27ae60;">–<?= formatPrice($order['discount']) ?></td>
        </tr><?php endif; ?>
      <tr>
        <td style="color:#777;">Shipping</td>
        <td class="text-right"><?= $order['shipping'] == 0 ? 'FREE' : formatPrice($order['shipping']) ?></td>
      </tr>
      <tr>
        <td style="color:#777;">GST (<?= GST_PERCENT ?>%)</td>
        <td class="text-right"><?= formatPrice($order['gst']) ?></td>
      </tr>
      <?php if (isset($order['cod_fee']) && $order['cod_fee'] > 0): ?>
        <tr>
          <td style="color:#777;">COD Fee</td>
          <td class="text-right"><?= formatPrice($order['cod_fee']) ?></td>
        </tr>
      <?php endif; ?>
      <tr class="total-row">
        <td><strong>TOTAL</strong></td>
        <td class="text-right"><strong><?= formatPrice($order['total']) ?></strong></td>
      </tr>
    </table>

    <?php if ($order['order_status'] === 'delivered'): ?>
      <div style="text-align:center; margin:20px 0;">
        <div class="stamp">✓ PAID & DELIVERED</div>
      </div>
    <?php endif; ?>

    <div class="footer">
      <div style="margin-bottom:8px;">Thank you for shopping with <strong style="color:#6C63FF;">MIZ MAX</strong>!
      </div>
      <div>For support: support@MIZ MAX.com | +91 6282 626 989</div>
      <div style="margin-top:8px; font-size:10px;">This is a computer-generated invoice. No signature required.</div>
      <div style="margin-top:8px; font-size:10px;">© <?= date('Y') ?> MIZ MAX. All rights reserved.</div>
    </div>
  </div>
</body>

</html>