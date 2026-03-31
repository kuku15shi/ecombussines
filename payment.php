<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/functions.php';

requireUserLogin();

requireUserLogin();

$orderNum = $_GET['order'] ?? '';
if (!$orderNum) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number=? AND user_id=? LIMIT 1");
$stmt->execute([$orderNum, (int) $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}
if ($order['payment_status'] === 'paid') {
    header('Location: order_success.php?order=' . urlencode($orderNum));
    exit;
}

// Razorpay Amount is in paise
$razorpayAmount = $order['total'] * 100;

// Create Razorpay Order
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
$requestData = json_encode([
    'amount' => (int) $razorpayAmount,
    'currency' => 'INR',
    'receipt' => (string) $orderNum
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
            <div style='display:flex; gap:10px; justify-content:center; margin-top:15px;'>
                <a href='<?= SITE_URL ?>/cart' class='btn-primary-luxury' style='display:inline-block;'>Back to Cart</a>
                <a href='<?= SITE_URL ?>/checkout' class='btn-outline-luxury' style='display:inline-block;'>Back to Checkout</a>
            </div>
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
        :root {
            --primary-gradient: linear-gradient(135deg, #6C63FF, #FF6584);
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .payment-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            z-index: 10;
        }

        .payment-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 30px;
            letter-spacing: -1px;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .spinner-wrapper {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
        }

        .spinner {
            width: 100%;
            height: 100%;
            border: 4px solid var(--glass-border);
            border-top: 4px solid #6C63FF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .spinner-inner {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border: 4px solid var(--glass-border);
            border-top: 4px solid #FF6584;
            border-radius: 50%;
            animation: spin 1.5s linear infinite reverse;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .order-info {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 16px;
            padding: 15px;
            margin: 25px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .order-val {
            font-weight: 800;
            font-size: 1.1rem;
            color: #fff;
        }

        .payment-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 10px;
        }

        .btn-pay {
            background: var(--primary-gradient);
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(108, 99, 255, 0.4);
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(108, 99, 255, 0.5);
        }

        .btn-back {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--glass-border);
            padding: 14px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: var(--glass);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Background decorative elements */
        .bg-glow {
            position: fixed;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(108, 99, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
            filter: blur(50px);
        }

        .glow-1 {
            top: -100px;
            right: -100px;
        }

        .glow-2 {
            bottom: -100px;
            left: -100px;
        }
    </style>
</head>

<body>
    <div class="bg-glow glow-1"></div>
    <div class="bg-glow glow-2"></div>

    <div class="payment-container">
        <div class="payment-card">
            <div class="brand-logo">✦ MIZ MAX</div>

            <div class="spinner-wrapper">
                <div class="spinner"></div>
                <div class="spinner-inner"></div>
            </div>

            <h2 style="font-weight: 800; color: #fff; margin-bottom: 8px;">Secure Checkout</h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Connecting to Razorpay secure gateway...</p>

            <div class="order-info">
                <div style="text-align: left;">
                    <div class="order-label">Order Reference</div>
                    <div class="order-val"><?= $orderNum ?></div>
                </div>
                <div style="text-align: right;">
                    <div class="order-label">Amount Payable</div>
                    <div class="order-val" style="color: #6C63FF;"><?= formatPrice($order['total']) ?></div>
                </div>
            </div>

            <div class="payment-actions">
                <button id="rzp-button" class="btn-pay">Pay Securely Now</button>
                <a href="<?= SITE_URL ?>/cart" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Return to Shopping Cart
                </a>
            </div>

            <p
                style="margin-top: 25px; font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="bi bi-shield-lock-fill"></i> PCI-DSS Compliant • 256-bit SSL Encryption
            </p>
        </div>
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
            "handler": function (response) {
                // Success callback
                window.location.href = "<?= SITE_URL ?>/order/<?= $orderNum ?>/verify_payment?razorpay_payment_id=" + response.razorpay_payment_id + "&razorpay_order_id=" + response.razorpay_order_id + "&razorpay_signature=" + response.razorpay_signature;
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
                "ondismiss": function () {
                    // On dismiss, stay here or go back
                    // alert("Payment cancelled. You can retry from your orders page.");
                }
            }
        };
        var rzp1 = new Razorpay(options);

        // Auto open
        window.onload = function () {
            rzp1.open();
        };

        document.getElementById('rzp-button').onclick = function (e) {
            rzp1.open();
            e.preventDefault();
        }
    </script>
</body>

</html>