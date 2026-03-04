<?php
// WhatsApp Business Cloud API Helper Functions

/**
 * Send WhatsApp Message using Meta API
 */
function sendWhatsAppMessage($to, $messageBody, $type = 'template', $templateName = '', $templateData = [], $orderId = null) {
    global $pdo;

    $url = "https://graph.facebook.com/" . WA_VERSION . "/" . WA_PHONE_NUMBER_ID . "/messages";
    
    $data = [
        "messaging_product" => "whatsapp",
        "to" => $to,
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

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . WA_ACCESS_TOKEN,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    $resArr = json_decode($response, true);
    $status = ($info['http_code'] === 200) ? 'success' : 'fail';
    $errorMsg = ($status === 'fail') ? ($resArr['error']['message'] ?? 'Unknown Error') : null;

    // Log the API call
    try {
        $stmt = $pdo->prepare("INSERT INTO whatsapp_logs (order_id, phone, type, api_response, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$orderId, $to, ($type === 'template' ? $templateName : 'chat'), $response, $status, $errorMsg]);

        // Update WhatsApp status in orders if relevant
        if ($orderId && $templateName === 'order_confirmation') {
            $pdo->prepare("UPDATE orders SET whatsapp_status = ? WHERE id = ?")->execute([($status === 'success' ? 'sent' : 'failed'), $orderId]);
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
