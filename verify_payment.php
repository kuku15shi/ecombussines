<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();

$orderNum = $_GET['order'] ?? '';
$paymentId = $_GET['razorpay_payment_id'] ?? '';
$razorpayOrderId = $_GET['razorpay_order_id'] ?? '';
$signature = $_GET['razorpay_signature'] ?? '';

if (!$orderNum || !$paymentId || !$signature) {
    die("Invalid payment request session information missing.");
}

// Verify Signature (Signature verification using HMAC-SHA256)
$expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);

if (hash_equals($expectedSignature, $signature)) {
    // Signature is valid
    $stmt = $pdo->prepare("SELECT id, total FROM orders WHERE order_number=? AND user_id=? LIMIT 1");
    $stmt->execute([$orderNum, (int)$_SESSION['user_id']]);
    $order = $stmt->fetch();

    if ($order) {
        $orderId = $order['id'];
        
        // Update Order to Paid
        $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")->execute([$orderId]);
        
        // Notify Admin of Successful Payment
        require_once __DIR__ . '/includes/whatsapp_functions.php';
        sendAdminOrderNotification($orderId);
        
        // Award affiliate commission
        recordAffiliateCommission($orderId, $order['total']);

        // Clear Cart
        unset($_SESSION['cart'], $_SESSION['coupon_discount'], $_SESSION['coupon_code']);
        
        header('Location: ' . SITE_URL . '/order/' . urlencode($orderNum) . '/success');
        exit;
    } else {
        die("Error: Matching order not found in our database for this payment.");
    }
} else {
    // Invalid Signature
    $pdo->prepare("UPDATE orders SET payment_status='failed' WHERE order_number=?")->execute([$orderNum]);
    die("Security Error: Payment verification signature mismatch. This attempt has been logged.");
}
