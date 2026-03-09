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
    // Helper to send one message based on its type
    $sendOneMessage = function($msg) use ($from, $pdo) {
        if (is_string($msg)) {
            sendWhatsAppMessage($from, $msg, 'text');
            $logMsg = $msg;
        } elseif (is_array($msg)) {
            $type = $msg['_type'] ?? 'text';
            $payload = $msg['_payload'] ?? $msg;
            if ($type === 'interactive') {
                sendWhatsAppMessage($from, $payload, 'interactive');
                $logMsg = '[Interactive]';
            } elseif ($type === 'image') {
                sendWhatsAppMessage($from, $payload, 'image');
                $logMsg = '[Image] ' . ($payload['caption'] ?? '');
            } else {
                sendWhatsAppMessage($from, $payload, 'text');
                $logMsg = is_string($payload) ? $payload : json_encode($payload);
            }
        }
        return $logMsg ?? '';
    };

    $logMsg = '';
    if (is_array($reply) && isset($reply['_type'])) {
        if ($reply['_type'] === 'multi') {
            // Send multiple messages sequentially
            foreach ($reply['_messages'] as $msg) {
                $logMsg = $sendOneMessage($msg);
                usleep(300000); // 300ms delay between messages
            }
        } elseif ($reply['_type'] === 'interactive') {
            sendWhatsAppMessage($from, $reply['_payload'], 'interactive');
            $logMsg = '[Interactive Menu]';
        } elseif ($reply['_type'] === 'image') {
            sendWhatsAppMessage($from, $reply['_payload'], 'image');
            $logMsg = '[Image]';
        } else {
            sendWhatsAppMessage($from, $reply, 'text');
            $logMsg = json_encode($reply);
        }
    } else {
        // Plain text string
        sendWhatsAppMessage($from, $reply, 'text');
        $logMsg = $reply;
    }

    // Store last outgoing message for chat log
    try {
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
            ->execute([$from, is_string($logMsg) ? substr($logMsg, 0, 500) : '[Message]']);
    } catch (Exception $e) {
        error_log("Webhook Reply DB Error: " . $e->getMessage());
    }
}
