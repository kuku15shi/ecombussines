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

$value   = $data['entry'][0]['changes'][0]['value'];
$message = $value['messages'][0];
$from    = $message['from']; // Customer Phone
$messageId = $message['id'];
$msgType = $message['type']; // text, audio, image, video, document, sticker, location, interactive, order

$text      = '';
$buttonId  = '';
$mediaTag  = ''; // e.g. [AUDIO], [IMAGE], [VIDEO], [DOCUMENT], [STICKER], [LOCATION]

// ── Resolve Sender Name from contacts ──────────────────────────
$senderName = '';
if (!empty($value['contacts'][0]['profile']['name'])) {
    $senderName = $value['contacts'][0]['profile']['name'];
}

switch ($msgType) {
    case 'text':
        $text = trim($message['text']['body']);
        break;

    case 'interactive':
        if (isset($message['interactive']['button_reply'])) {
            $buttonId = $message['interactive']['button_reply']['id'];
            $text     = $message['interactive']['button_reply']['title'];
        } elseif (isset($message['interactive']['list_reply'])) {
            $buttonId = $message['interactive']['list_reply']['id'];
            $text     = $message['interactive']['list_reply']['title'];
        }
        break;

    case 'audio':
        $mediaId  = $message['audio']['id'];
        $localUrl = downloadWhatsAppMedia($mediaId, 'voice');
        $text     = $localUrl ? "[AUDIO]:" . $localUrl : "[Voice Message - Failed to download]";
        break;

    case 'image':
        $mediaId  = $message['image']['id'];
        $caption  = $message['image']['caption'] ?? '';
        $localUrl = downloadWhatsAppMedia($mediaId, 'images');
        $text     = $localUrl ? "[IMAGE]:" . $localUrl . ($caption ? "|CAPTION:" . $caption : '') : "[Image - Failed to download]";
        break;

    case 'video':
        $mediaId  = $message['video']['id'];
        $caption  = $message['video']['caption'] ?? '';
        $localUrl = downloadWhatsAppMedia($mediaId, 'videos');
        $text     = $localUrl ? "[VIDEO]:" . $localUrl . ($caption ? "|CAPTION:" . $caption : '') : "[Video - Failed to download]";
        break;

    case 'document':
        $mediaId  = $message['document']['id'];
        $filename = $message['document']['filename'] ?? ('document_' . time());
        $localUrl = downloadWhatsAppMedia($mediaId, 'documents', $filename);
        $text     = $localUrl ? "[DOCUMENT]:" . $localUrl . "|FILENAME:" . $filename : "[Document - Failed to download]";
        break;

    case 'sticker':
        $mediaId  = $message['sticker']['id'];
        $localUrl = downloadWhatsAppMedia($mediaId, 'stickers');
        $text     = $localUrl ? "[STICKER]:" . $localUrl : "[Sticker - Failed to download]";
        break;

    case 'location':
        $lat      = $message['location']['latitude']  ?? '';
        $lng      = $message['location']['longitude'] ?? '';
        $locName  = $message['location']['name']      ?? '';
        $locAddr  = $message['location']['address']   ?? '';
        $text     = "[LOCATION]:lat={$lat}|lng={$lng}|name={$locName}|addr={$locAddr}";
        break;

    case 'order':
        $text = "[ORDER]: Customer placed an order via WhatsApp catalog";
        break;

    default:
        $text = "[" . strtoupper($msgType) . " message]";
        break;
}

file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . "Message from: $from | Type: $msgType | Text: " . substr($text, 0, 100) . "\n", FILE_APPEND);

// ── Store Incoming Message ──────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "INSERT INTO whatsapp_messages (phone, sender_name, message, message_type, direction, message_id, status)
         VALUES (?, ?, ?, ?, 'incoming', ?, 'received')
         ON DUPLICATE KEY UPDATE status = 'received'"
    );
    $stmt->execute([$from, $senderName, $text ?: ($buttonId ? "Button: $buttonId" : "Other"), $msgType, $messageId]);
} catch (Exception $e) {
    // Fallback without message_type if column doesn't exist yet
    try {
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, message_id, status) VALUES (?, ?, 'incoming', ?, 'received')")
            ->execute([$from, $text ?: ($buttonId ? "Button: $buttonId" : "Other"), $messageId]);
    } catch (Exception $e2) {
        error_log("Webhook DB Error: " . $e2->getMessage());
    }
}

// ── Bot Engine (only for text/button messages) ──────────────────
if (in_array($msgType, ['text', 'interactive'])) {
    require_once __DIR__ . '/bot_engine.php';

    $bot = new WhatsAppBot($pdo, $from, $text);
    if ($buttonId) {
        $reply = $bot->handleButton($buttonId);
    } else {
        $reply = $bot->process();
    }

    if ($reply) {
        $sendOneMessage = function($msg) use ($from, $pdo) {
            if (is_string($msg)) {
                sendWhatsAppMessage($from, $msg, 'text');
                return $msg;
            } elseif (is_array($msg)) {
                $type    = $msg['_type']    ?? 'text';
                $payload = $msg['_payload'] ?? $msg;
                if ($type === 'interactive') {
                    sendWhatsAppMessage($from, $payload, 'interactive');
                    return '[Interactive]';
                } elseif ($type === 'image') {
                    sendWhatsAppMessage($from, $payload, 'image');
                    return '[Image] ' . ($payload['caption'] ?? '');
                } else {
                    sendWhatsAppMessage($from, is_string($payload) ? $payload : json_encode($payload), 'text');
                    return is_string($payload) ? $payload : json_encode($payload);
                }
            }
            return '';
        };

        $logMsg = '';
        if (is_array($reply) && isset($reply['_type'])) {
            if ($reply['_type'] === 'multi') {
                foreach ($reply['_messages'] as $msg) {
                    $logMsg = $sendOneMessage($msg);
                    usleep(300000);
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
            sendWhatsAppMessage($from, $reply, 'text');
            $logMsg = $reply;
        }

        // Store outgoing bot reply
        try {
            $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, message_type, direction, status) VALUES (?, ?, 'text', 'outgoing', 'sent')")
                ->execute([$from, is_string($logMsg) ? substr($logMsg, 0, 500) : '[Message]']);
        } catch (Exception $e) {
            try {
                $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
                    ->execute([$from, is_string($logMsg) ? substr($logMsg, 0, 500) : '[Message]']);
            } catch (Exception $e2) {
                error_log("Webhook Reply DB Error: " . $e2->getMessage());
            }
        }
    }
}
