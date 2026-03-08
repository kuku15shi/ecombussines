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
 */
function sendWhatsAppMessage($to, $messageBody, $type = 'template', $templateName = '', $templateData = [], $orderId = null) {
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
        $data["template"] = [
            "name" => $templateName,
            "language" => ["code" => "en_US"],
            "components" => [
                [
                    "type" => "body",
                    "parameters" => $templateData
                ]
            ]
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local/hosting issues
    
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
        $stmt->execute([$orderId, $to, ($type === 'template' ? $templateName : 'chat'), $response, $status, $errorMsg]);

        // Update WhatsApp status in orders if relevant (check if column exists first to prevent fatal error)
        if ($orderId && $templateName === 'order_confirmation') {
            try {
                $pdo->prepare("UPDATE orders SET whatsapp_status = ? WHERE id = ?")->execute([($status === 'success' ? 'sent' : 'failed'), $orderId]);
            } catch (Exception $e) {
                // Column might be missing on some environments
                error_log("WhatsApp Status Update Failed (Check if column exists): " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        // Silently log DB errors if logging fails
        error_log("WhatsApp Logging Error: " . $e->getMessage());
    }

    return [
        'status' => $status,
        'response' => $resArr,
        'error' => $errorMsg
    ];
}

/**
 * Handle Order Tracking Lookup
 */
function trackOrderOnWhatsApp($orderId, $phone) {
    global $pdo;
    
    // Clean inputs
    $orderId = trim($orderId);
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Search by ID (since orders table has 'id' and 'order_number')
    // Check both for convenience
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE (id = ? OR order_number = ?) AND (phone LIKE ? OR phone LIKE ?)");
    // Phone matching (can be partial or with/without +91)
    $phoneParam = "%" . substr($phone, -10) . "%";
    $stmt->execute([$orderId, $orderId, $phoneParam, $phoneParam]);
    $order = $stmt->fetch();

    if (!$order) {
        return "Order not found or number mismatch.";
    }

    $msg = "Order ID: " . $order['order_number'] . "\n" .
           "Status: " . ucfirst($order['order_status']) . "\n" .
           "Payment Status: " . ucfirst($order['payment_status']) . "\n";
    
    if ($order['order_status'] === 'shipped' && $order['tracking_id']) {
        $msg .= "Tracking ID: " . $order['tracking_id'] . "\n" .
                "Courier: " . ($order['courier_name'] ?: 'Standard') . "\n";
    }

    if ($order['order_status'] === 'delivered') {
        $msg .= "Thank you for shopping with us! 🎉";
    }

    return $msg;
}
