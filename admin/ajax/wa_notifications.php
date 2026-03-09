<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/whatsapp_functions.php';

if (!isAdminLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'preview') {
    $orderRef = $_POST['order_ref'] ?? '';
    $tempName = $_POST['template'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ?");
    $stmt->execute([$orderRef, $orderRef]);
    $order = $stmt->fetch();
    
    if (!$order) { echo json_encode(['success' => false, 'message' => 'Order not found']); exit; }
    
    $stmt = $pdo->prepare("SELECT content FROM whatsapp_templates WHERE name = ?");
    $stmt->execute([$tempName]);
    $template = $stmt->fetchColumn();
    
    if (!$template) { echo json_encode(['success' => false, 'message' => 'Template not found']); exit; }
    
    // Replace variables
    $vars = [
        '{customer_name}' => $order['name'],
        '{order_id}' => $order['order_number'],
        '{delivery_date}' => date('d M Y', strtotime($order['expected_delivery'] ?: '+3 days')),
        '{tracking_link}' => SITE_URL . "/track_order?order=" . $order['order_number'],
        '{address}' => $order['address'],
        '{courier}' => $order['courier_name'] ?: 'Standard Shipping'
    ];
    
    $preview = str_replace(array_keys($vars), array_values($vars), $template);
    
    echo json_encode([
        'success' => true,
        'preview' => $preview,
        'phone' => $order['phone'],
        'order_id' => $order['id']
    ]);
    exit;
}

if ($action === 'send') {
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    $orderId = $_POST['order_id'] ?? null;
    
    if (!$phone || !$message) { echo json_encode(['success' => false, 'message' => 'Invalid data']); exit; }
    
    $res = sendWhatsAppMessage($phone, $message, 'text', '', [], $orderId);
    
    if ($res['status'] === 'success') {
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
            ->execute([$phone, $message]);
        echo json_encode(['success' => true, 'message' => 'Notification sent!']);
    } else {
        echo json_encode(['success' => false, 'message' => $res['error']]);
    }
    exit;
}
