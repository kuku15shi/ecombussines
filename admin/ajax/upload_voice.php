<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/whatsapp_functions.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio'])) {
    $phone = $_POST['phone'] ?? '';
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number missing']);
        exit;
    }

    $uploadDir = __DIR__ . '/../../uploads/voice/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = 'voice_' . time() . '_' . uniqid() . '.ogg';
    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['audio']['tmp_name'], $targetFile)) {
        $audioUrl = SITE_URL . '/uploads/voice/' . $fileName;

        // Note: WhatsApp Cloud API requires a public URL to fetch media if not using media_id.
        // For local development, this won't work on Meta's side, but the logic is correct for production.
        
        $res = sendWhatsAppMessage($phone, ['link' => $audioUrl], 'audio');

        if ($res['status'] === 'success') {
            $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
                ->execute([$phone, '[AUDIO]:' . $audioUrl]);
            
            echo json_encode(['success' => true, 'message' => 'Voice message sent!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'WhatsApp Error: ' . ($res['error'] ?? 'Unknown')]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save recording on server']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
