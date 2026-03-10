<?php
// WhatsApp Business Cloud API Helper Functions

/**
 * Get dynamic config from DB
 */
function getWhatsAppConfig() {
    global $pdo;
    static $config = null;
    if ($config !== null) return $config;
    
    $config = [
        'token' => WA_ACCESS_TOKEN,
        'phone_id' => WA_PHONE_NUMBER_ID,
        'version' => WA_VERSION
    ];

    try {
        $res = $pdo->query("SELECT config_key, config_value FROM whatsapp_config")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($res['wa_access_token'])) $config['token'] = $res['wa_access_token'];
        if (!empty($res['wa_phone_number_id'])) $config['phone_id'] = $res['wa_phone_number_id'];
        if (!empty($res['wa_version'])) $config['version'] = $res['wa_version'];
    } catch (Exception $e) {}
    
    return $config;
}

/**
 * Send WhatsApp Message using Meta API
 * Enhanced to support Interactive Buttons and Templates with Parameters
 */
function sendWhatsAppMessage($to, $messageBody, $type = 'text', $templateName = '', $templateData = [], $orderId = null) {
    global $pdo;

    $wa = getWhatsAppConfig();
    $toClean = preg_replace('/[^0-9]/', '', $to); // Ensure no + or spaces
    $url = "https://graph.facebook.com/" . $wa['version'] . "/" . $wa['phone_id'] . "/messages";
    
    $data = [
        "messaging_product" => "whatsapp",
        "to" => $toClean,
        "type" => $type
    ];

    if ($type === 'template') {
        $components = [];
        if (!empty($templateData)) {
            $components[] = [
                "type" => "body",
                "parameters" => $templateData
            ];
        }
        
        $data["template"] = [
            "name" => $templateName,
            "language" => ["code" => "en_US"],
            "components" => $components
        ];
    } elseif ($type === 'interactive') {
        // messageBody is expected to be the interactive array
        $data["interactive"] = $messageBody;
    } elseif ($type === 'image') {
        // messageBody = ['link' => url, 'caption' => text]
        $data["image"] = [
            "link" => $messageBody['link'],
            "caption" => $messageBody['caption'] ?? ''
        ];
    } elseif ($type === 'video') {
        $data["video"] = [
            "link" => $messageBody['link'],
            "caption" => $messageBody['caption'] ?? ''
        ];
    } elseif ($type === 'audio') {
        $data["audio"] = [
            "link" => $messageBody['link']
        ];
    } else {
        $data["text"] = ["body" => $messageBody];
    }

    $jsonPayload = json_encode($data);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $wa['token'],
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("WhatsApp cURL Error: " . $curlError);
    }

    $resArr = json_decode($response, true);
    $status = ($info['http_code'] === 200) ? 'success' : 'fail';
    $errorMsg = ($status === 'fail') ? ($resArr['error']['message'] ?? 'Unknown Error') : null;

    // Log the API call
    try {
        $stmt = $pdo->prepare("INSERT INTO whatsapp_logs (order_id, phone, type, api_response, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$orderId, $to, ($type === 'template' ? $templateName : $type), $response, $status, $errorMsg]);
    } catch (Exception $e) {
        error_log("WhatsApp Logging Error: " . $e->getMessage());
    }

    return [
        'status' => $status,
        'response' => $resArr,
        'error' => $errorMsg
    ];
}

/**
 * Send Order Delay Notification
 */
function sendOrderDelayNotification($orderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    // Fetch product name from items if summary is missing
    $pName = $order['product_summery'];
    if (!$pName) {
        $st = $pdo->prepare("SELECT product_name FROM order_items WHERE order_id = ? LIMIT 1");
        $st->execute([$orderId]);
        $pName = $st->fetchColumn() ?: 'Your ordered item';
    }
    
    $date = $order['delivery_date'] ? date('j March', strtotime($order['delivery_date'])) : ($order['expected_delivery'] ?: 'Soon');
    $trackLink = SITE_URL . "/track_order.php?order=" . $order['order_number'];

    $msg = "📢 *Order Delay Update*\n\n" .
           "Sorry, your order has been delayed due to an unexpected issue.\n\n" .
           "Order ID: #" . $order['order_number'] . "\n" .
           "Product: " . $pName . "\n\n" .
           "Your order is important and we are tracking it closely.\n\n" .
           "*New Delivery Date*: " . $date . "\n\n" .
           "Track Order:\n" . $trackLink;

    $interactive = [
        "type" => "button",
        "header" => ["type" => "text", "text" => "Order Update"],
        "body" => ["text" => $msg],
        "footer" => ["text" => "Thank you for your patience."],
        "action" => [
            "buttons" => [
                ["type" => "reply", "reply" => ["id" => "track_" . $orderId, "title" => "Track Order"]]
            ]
        ]
    ];

    return sendWhatsAppMessage($order['phone'], $interactive, 'interactive', '', [], $orderId);
}

/**
 * Send Shipping Update (Shipped)
 */
function sendShippingUpdate($orderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    $trackLink = SITE_URL . "/track_order.php?order=" . $order['order_number'];

    $msg = "📦 *Good News!*\n\n" .
           "Your order *" . $order['order_number'] . "* has been shipped.\n\n" .
           "Courier: " . ($order['courier_name'] ?: 'Standard') . "\n" .
           "Tracking ID: " . ($order['tracking_id'] ?: 'N/A') . "\n\n" .
           "Track your order:\n" . $trackLink;

    return sendWhatsAppMessage($order['phone'], $msg, 'text', '', [], $orderId);
}

/**
 * Send Out For Delivery Notification
 */
function sendOutForDelivery($orderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    $msg = "🚚 *Out for Delivery*\n\n" .
           "Your order *" . $order['order_number'] . "* is out for delivery today.\n\n" .
           "Delivery Agent: " . ($order['delivery_agent_name'] ?: 'Our Executive') . "\n" .
           "Phone: " . ($order['delivery_agent_phone'] ?: 'N/A');

    return sendWhatsAppMessage($order['phone'], $msg, 'text', '', [], $orderId);
}

/**
 * Send Delivered Notification
 */
function sendDeliveredNotification($orderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    $msg = "🎉 *Order Delivered!*\n\n" .
           "Your order *" . $order['order_number'] . "* has been delivered successfully.\n\n" .
           "Thank you for shopping with *" . SITE_NAME . "* ❤️";

    return sendWhatsAppMessage($order['phone'], $msg, 'text', '', [], $orderId);
}

/**
 * Send Delivery Availability Confirmation
 */
function sendDeliveryAvailabilityConfirmation($orderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    // Fetch product name
    $pName = $order['product_summery'];
    if (!$pName) {
        $st = $pdo->prepare("SELECT product_name FROM order_items WHERE order_id = ? LIMIT 1");
        $st->execute([$orderId]);
        $pName = $st->fetchColumn() ?: 'Your ordered item';
    }
    
    $msg = "🚚 *Delivery Update*\n\n" .
           "Great news! Your order is ready for delivery.\n\n" .
           "Order ID: #" . $order['order_number'] . "\n" .
           "Product: " . $pName . "\n\n" .
           "Please confirm your availability to receive the order today between:\n" .
           "*7 AM – 11 PM*";

    $interactive = [
        "type" => "button",
        "body" => ["text" => $msg],
        "footer" => ["text" => "Reply by clicking a button below"],
        "action" => [
            "buttons" => [
                ["type" => "reply", "reply" => ["id" => "del_yes_" . $orderId, "title" => "Yes, I'm available"]],
                ["type" => "reply", "reply" => ["id" => "del_no_" . $orderId, "title" => "Reschedule"]],
                ["type" => "reply", "reply" => ["id" => "del_cancel_" . $orderId, "title" => "Cancel order"]]
            ]
        ]
    ];

    return sendWhatsAppMessage($order['phone'], $interactive, 'interactive', '', [], $orderId);
}

/**
 * Send COD Confirmation
 */
function sendCODConfirmation($orderId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) return false;

    // Fetch product name
    $pName = $order['product_summery'];
    if (!$pName) {
        $st = $pdo->prepare("SELECT product_name FROM order_items WHERE order_id = ? LIMIT 1");
        $st->execute([$orderId]);
        $pName = $st->fetchColumn() ?: 'Your ordered item';
    }
    
    $msg = "✅ *Order Confirmed!*\n\n" .
           "Hi " . $order['name'] . " 👋\n\n" .
           "Your order *" . $order['order_number'] . "* has been successfully placed.\n\n" .
           "Product: " . $pName . "\n" .
           "Payment: " . ($order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment') . "\n\n" .
           "📦 *Expected Delivery*: " . ($order['expected_delivery'] ?: '3-5 Days');

    $interactive = [
        "type" => "button",
        "body" => ["text" => $msg],
        "footer" => ["text" => "Please confirm your order preference below:"],
        "action" => [
            "buttons" => [
                ["type" => "reply", "reply" => ["id" => "cod_confirm_" . $orderId, "title" => "Confirm Order"]],
                ["type" => "reply", "reply" => ["id" => "cod_cancel_" . $orderId, "title" => "Cancel Order"]],
                ["type" => "reply", "reply" => ["id" => "support", "title" => "Talk to Support"]]
            ]
        ]
    ];

    return sendWhatsAppMessage($order['phone'], $interactive, 'interactive', '', [], $orderId);
}

/**
 * Handle Order Tracking Lookup
 */
function trackOrderOnWhatsApp($orderId, $phone) {
    global $pdo;
    
    // Clean inputs
    $orderId = trim($orderId);
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE (id = ? OR order_number = ?) AND (phone LIKE ? OR phone LIKE ?)");
    $phoneParam = "%" . substr($phone, -10) . "%";
    $stmt->execute([$orderId, $orderId, $phoneParam, $phoneParam]);
    $order = $stmt->fetch();

    if (!$order) {
        return "Order not found or number mismatch.";
    }

    // Fetch product name
    $st = $pdo->prepare("SELECT product_name FROM order_items WHERE order_id = ? LIMIT 1");
    $st->execute([$order['id']]);
    $pName = $st->fetchColumn() ?: 'Ordered Item';

    $statusLabel = ucfirst($order['order_status']);
    if ($order['order_status'] === 'out_for_delivery') $statusLabel = "Out for Delivery 🚚";

    $msg = "📦 *Order Details*\n\n" .
           "Order ID: " . $order['order_number'] . "\n" .
           "Product: " . $pName . "\n" .
           "Status: " . $statusLabel . "\n" .
           "Expected Delivery: " . ($order['expected_delivery'] ?: 'Soon') . "\n\n" .
           "Track here:\n" . SITE_URL . "/track_order.php?order=" . $order['order_number'];

    return $msg;
}
/**
 * Download Media from Meta
 */
function downloadWhatsAppMedia($mediaId) {
    if (!$mediaId) return false;
    $wa = getWhatsAppConfig();
    
    // 1. Get the URL for the media
    $getUrl = "https://graph.facebook.com/" . $wa['version'] . "/" . $mediaId;
    $ch = curl_init($getUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $wa['token']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    
    if (empty($data['url'])) return false;
    $waUrl = $data['url'];
    $ext = explode('/', $data['mime_type'])[1] ?? 'bin';
    if ($ext === 'ogg; codecs=opus') $ext = 'ogg';

    // 2. Download actual bytes
    $ch = curl_init($waUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $wa['token'], "User-Agent: curl"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $bytes = curl_exec($ch);
    curl_close($ch);

    if (!$bytes) return false;

    // 3. Save locally
    $dir = __DIR__ . '/../uploads/whatsapp_media/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $fileName = 'media_' . time() . '_' . $mediaId . '.' . $ext;
    file_put_contents($dir . $fileName, $bytes);

    return SITE_URL . '/uploads/whatsapp_media/' . $fileName;
}
