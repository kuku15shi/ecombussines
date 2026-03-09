<?php
file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . "Webhook Hit! Method: " . $_SERVER['REQUEST_METHOD'] . " Query: " . $_SERVER['QUERY_STRING'] . "\n", FILE_APPEND);
// We'll log the actual data after decoding later for security but for now let's log method
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
$messageId = $message['id'];
$text = '';
$buttonId = '';

if (isset($message['text']['body'])) {
    $text = trim($message['text']['body']);
} elseif (isset($message['interactive']['button_reply']['id'])) {
    $buttonId = $message['interactive']['button_reply']['id'];
    $text = $message['interactive']['button_reply']['title'];
}

file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . "Message from: $from | Text: $text | ButtonID: $buttonId\n", FILE_APPEND);

// Store Incoming Message
try {
    $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, message_id, status) VALUES (?, ?, 'incoming', ?, 'received')")
        ->execute([$from, $text ?: ($buttonId ? "Button: $buttonId" : "Other"), $messageId]);
} catch (Exception $e) {
    error_log("Webhook DB Error: " . $e->getMessage());
}

// Receiver and Session Logic
require_once __DIR__ . '/bot_engine.php';

$bot = new WhatsAppBot($pdo, $from, $text);
if ($buttonId) {
    $reply = $bot->handleButton($buttonId);
} else {
    $reply = $bot->process();
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
