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

$phone   = $_POST['phone']   ?? '';
$type    = $_POST['type']    ?? ''; // image | video | document | audio
$caption = $_POST['caption'] ?? '';

if (!$phone || !$type) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// ─── PRODUCT LINK (no file upload needed) ───────────────────────────────────
if ($type === 'product_link') {
    $link    = $_POST['product_link'] ?? '';
    $prodMsg = $_POST['product_msg']  ?? $link;
    if (!$link) {
        echo json_encode(['success' => false, 'message' => 'Product link missing']);
        exit;
    }
    $res = sendWhatsAppMessage($phone, $prodMsg, 'text');
    if ($res['status'] === 'success') {
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, message_type, direction, status) VALUES (?, ?, 'text', 'outgoing', 'sent')")
            ->execute([$phone, $prodMsg]);
        echo json_encode(['success' => true, 'message' => 'Product link sent!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'WA Error: ' . ($res['error'] ?? 'Unknown')]);
    }
    exit;
}

// ─── FILE UPLOAD ─────────────────────────────────────────────────────────────
if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$allowed = [
    'image'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'video'    => ['video/mp4', 'video/3gp', 'video/avi', 'video/mov', 'video/quicktime'],
    'document' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ],
    'audio'    => ['audio/ogg', 'audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/aac'],
];

$fileMime = $_FILES['media']['type'];
$origName = $_FILES['media']['name'];

if (!isset($allowed[$type]) || !in_array($fileMime, $allowed[$type])) {
    echo json_encode(['success' => false, 'message' => "Invalid file type ($fileMime) for $type"]);
    exit;
}

// Max size: 16 MB for all types
if ($_FILES['media']['size'] > 16 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 16 MB)']);
    exit;
}

$subfolderMap = [
    'image'    => 'images',
    'video'    => 'videos',
    'document' => 'documents',
    'audio'    => 'voice',
];
$subfolder = $subfolderMap[$type] ?? $type;
$uploadDir = __DIR__ . '/../../uploads/whatsapp_media/' . $subfolder . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$safeOrig  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $origName);
$fileName  = time() . '_' . uniqid() . '_' . $safeOrig;
$targetFile = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['media']['tmp_name'], $targetFile)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file on server']);
    exit;
}

$publicUrl = SITE_URL . '/uploads/whatsapp_media/' . $subfolder . '/' . $fileName;

// Bypassing host security (e.g. Infinity Free): Upload to Meta first, then send by ID
$mediaId = uploadWhatsAppMedia($targetFile, $type);

// ─── Build WhatsApp API payload ──────────────────────────────────────────────
if ($type === 'image') {
    $payload = $mediaId ? ['id' => $mediaId, 'caption' => $caption] : ['link' => $publicUrl, 'caption' => $caption];
    $waType  = 'image';
    $msgLog  = "[IMAGE]:{$publicUrl}" . ($caption ? "|CAPTION:{$caption}" : '');
} elseif ($type === 'video') {
    $payload = $mediaId ? ['id' => $mediaId, 'caption' => $caption] : ['link' => $publicUrl, 'caption' => $caption];
    $waType  = 'video';
    $msgLog  = "[VIDEO]:{$publicUrl}" . ($caption ? "|CAPTION:{$caption}" : '');
} elseif ($type === 'document') {
    $payload = $mediaId ? ['id' => $mediaId, 'filename' => $origName] : ['link' => $publicUrl, 'filename' => $origName];
    $waType  = 'document';
    $msgLog  = "[DOCUMENT]:{$publicUrl}|FILENAME:{$origName}";
} elseif ($type === 'audio') {
    $payload = $mediaId ? ['id' => $mediaId] : ['link' => $publicUrl];
    $waType  = 'audio';
    $msgLog  = "[AUDIO]:{$publicUrl}";
}

$res = sendWhatsAppMessage($phone, $payload, $waType);

if ($res['status'] === 'success') {
    try {
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, message_type, direction, status) VALUES (?, ?, ?, 'outgoing', 'sent')")
            ->execute([$phone, $msgLog, $waType]);
    } catch (Exception $e) {
        // Fallback without message_type column
        $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
            ->execute([$phone, $msgLog]);
    }
    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' sent successfully!', 'url' => $publicUrl]);
} else {
    // Still save locally even if WA failed
    echo json_encode(['success' => false, 'message' => 'WhatsApp Error: ' . ($res['error'] ?? 'Unknown error')]);
}
