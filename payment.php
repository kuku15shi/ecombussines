<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();

requireUserLogin();

$orderNum = $_GET['order'] ?? '';
if (!$orderNum) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=? AND user_id=? LIMIT 1");
$stmt->execute([$orderNum, (int)$_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) { header('Location: index.php'); exit; }
if ($order['payment_status'] === 'paid') { header('Location: order_success.php?order=' . urlencode($orderNum)); exit; }

// Razorpay Amount is in paise
$razorpayAmount = $order['total'] * 100;

// Create Razorpay Order
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
$requestData = json_encode([
    'amount' => (int)$razorpayAmount,
    'currency' => 'INR',
    'receipt' => (string)$orderNum
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);

// Manual Basic Auth Header
$authHeader = base64_encode(trim(RAZORPAY_KEY_ID) . ':' . trim(RAZORPAY_KEY_SECRET));
$headers = [
    'Content-Type: application/json',
    'Authorization: Basic ' . $authHeader
];

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// Optional: Skip SSL verification if having certificate issues on local server
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
}
curl_close($ch);

$rzpOrder = json_decode($result, true);
$razorpayOrderId = $rzpOrder['id'] ?? '';

if (!$razorpayOrderId) {
    $detail = $rzpOrder['error']['description'] ?? ($error_msg ?? 'Unknown error');
    echo "<div class='page-wrapper'><div class='container-sm'><div class='alert alert-danger'>
            <h4 style='margin-bottom:10px;'><i class='bi bi-exclamation-triangle'></i> Payment Initiation Failed</h4>
            <p><strong>Reason:</strong> $detail</p>
            <p style='font-size:0.8rem; margin-top:10px;'>HTTP Code: $httpCode | Please check if your Razorpay API Keys are active and correct in <code>config/db.php</code>.</p>
            <a href='checkout.php' class='btn-outline-luxury' style='margin-top:15px; display:inline-block;'>Back to Checkout</a>
          </div></div></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment - <?= SITE_NAME ?></title>
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        .payment-loader {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            background: var(--bg);
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--glass-border);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="payment-loader">
        <div class="spinner"></div>
        <h2 style="font-weight: 800;">Initiating Secure Payment</h2>
        <p style="color: var(--text-secondary);">Please do not refresh or close this page.</p>
        <button id="rzp-button" class="btn-primary-luxury" style="margin-top: 20px;">Pay Now <?= formatPrice($order['total']) ?></button>
    </div>

    <script>
    var options = {
        "key": "<?= RAZORPAY_KEY_ID ?>",
        "amount": "<?= $razorpayAmount ?>",
        "currency": "INR",
        "name": "<?= SITE_NAME ?>",
        "description": "Payment for Order <?= $orderNum ?>",
        "image": "<?= SITE_URL ?>/assets/img/logo.png",
        "order_id": "<?= $razorpayOrderId ?>", 
        "handler": function (response){
            // Success callback
            window.location.href = "verify_payment.php?order=<?= $orderNum ?>&razorpay_payment_id=" + response.razorpay_payment_id + "&razorpay_order_id=" + response.razorpay_order_id + "&razorpay_signature=" + response.razorpay_signature;
        },
        "prefill": {
            "name": "<?= $order['name'] ?>",
            "email": "<?= $order['email'] ?>",
            "contact": "<?= $order['phone'] ?>"
        },
        "theme": {
            "color": "#6C63FF"
        },
        "modal": {
            "ondismiss": function(){
                // On dismiss, stay here or go back
                alert("Payment cancelled. You can retry from your orders page.");
            }
        }
    };
    var rzp1 = new Razorpay(options);
    
    // Auto open
    window.onload = function() {
        rzp1.open();
    };

    document.getElementById('rzp-button').onclick = function(e){
        rzp1.open();
        e.preventDefault();
    }
    </script>
</body>
</html>
