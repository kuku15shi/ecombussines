<?php
file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . "Webhook Hit! Method: " . $_SERVER['REQUEST_METHOD'] . " Query: " . $_SERVER['QUERY_STRING'] . "\n", FILE_APPEND);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/whatsapp_functions.php';

// Webhook Verification (for Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? null;
    $token = $_GET['hub_verify_token'] ?? null;
    $challenge = $_GET['hub_challenge'] ?? null;

    if ($mode && $token) {
        if ($mode === 'subscribe' && $token === WA_WEBHOOK_VERIFY_TOKEN) {
            echo $challenge;
            exit;
        } else {
            http_response_code(403);
            exit;
        }
    }
}

// Receive Webhook Notifications
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
    exit; // Not a message event
}

$message = $data['entry'][0]['changes'][0]['value']['messages'][0];
$from = $message['from']; // Customer Phone
$text = strtolower(trim($message['text']['body'] ?? ''));
$messageId = $message['id'];

// Store Incoming Message
try {
    $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, message_id, status) VALUES (?, ?, 'incoming', ?, 'received')")
        ->execute([$from, $text, $messageId]);
} catch (Exception $e) {
    error_log("Webhook DB Error: " . $e->getMessage());
}

$reply = "";

// Chatbot Logic
if ($text === 'hi' || $text === 'hello') {
    $reply = "Welcome to " . SITE_NAME . " 👋\nSend:\nTrack <OrderID> to track order\nHelp for support options";
} elseif (preg_match('/^track\s+([a-zA-Z0-9]+)$/', $text, $matches)) {
    $reply = trackOrderOnWhatsApp($matches[1], $from);
} elseif (is_numeric($text)) {
    // If only number, treat as Order ID
    $reply = trackOrderOnWhatsApp($text, $from);
} elseif ($text === 'help') {
    $reply = "Support Options:\n1. Track Order (Send: Track <ID>)\n2. Return Policy (Send: Return Policy)\n3. Contact Support (+91 11111 22222)";
} elseif ($text === 'return policy') {
    $reply = "Our Return Policy:\nYou can return products within 7 days of delivery. Items must be in original condition with tags and packaging intact. Refund will be credited within 5-7 working days.";
} else {
    // Default fallback or partial matches?
    // Let's keep it simple for now, or just don't reply if it's not recognized?
    // User requested "If order not found: Reply 'Order not found or number mismatch.'"
    // But that's only if they tried tracking.
}

if ($reply) {
    // Send Outgoing Reply
    sendWhatsAppMessage($from, $reply, 'text');
    
    // Store Outgoing Message
    try {
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
            ->execute([$from, $reply]);
    } catch (Exception $e) {
        error_log("Webhook Reply DB Error: " . $e->getMessage());
    }
}
