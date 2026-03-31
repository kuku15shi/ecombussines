<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../includes/whatsapp_functions.php';

requireAdminLogin();
$pageTitle = 'WhatsApp Manager';

// Handle Macro Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_macro'])) {
    validateCsrf();
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    if ($title && $content) {
        $pdo->prepare("INSERT INTO whatsapp_macros (title, content) VALUES (?, ?)")->execute([$title, $content]);
        $successMsg = "Macro saved!";
    }
}

// Handle Template Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    validateCsrf();
    $id = (int)$_POST['id'];
    $content = trim($_POST['content']);
    $pdo->prepare("UPDATE whatsapp_templates SET content = ? WHERE id = ?")->execute([$content, $id]);
    $successMsg = "Template updated!";
}

// Handle FAQ Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_faq'])) {
    validateCsrf();
    $q = trim($_POST['question']);
    $a = trim($_POST['answer']);
    $k = trim($_POST['keywords'] ?: '');
    if ($q && $a) {
        $pdo->prepare("INSERT INTO bot_faqs (question, answer, keywords) VALUES (?, ?, ?)")->execute([$q, $a, $k]);
        $successMsg = "FAQ added!";
    }
}

// Handle Bot Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_wa_config'])) {
    validateCsrf();
    $configs = [
        'wa_access_token' => trim($_POST['wa_access_token'] ?? ''),
        'wa_phone_number_id' => trim($_POST['wa_phone_number_id'] ?? ''),
        'wa_version' => trim($_POST['wa_version'] ?? 'v20.0'),
        'wa_webhook_token' => trim($_POST['wa_webhook_token'] ?? ''),
        'bot_enabled' => $_POST['bot_enabled'] ?? '1',
        'bot_welcome_msg' => trim($_POST['bot_welcome_msg'] ?? "👋 Hello! Welcome to *" . SITE_NAME . "*\nHow can I help you today?"),
        'bot_fallback_msg' => trim($_POST['bot_fallback_msg'] ?? "Sorry, I didn't understand that. Reply MENU to see options.")
    ];
    $stmt = $pdo->prepare("INSERT INTO whatsapp_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
    foreach ($configs as $key => $val) { $stmt->execute([$key, $val]); }
    $successMsg = "Settings updated!";
}

// Handle Chat Assignment
if (isset($_GET['assign_phone']) && isset($_GET['agent_id'])) {
    $phone = $_GET['assign_phone'];
    $agentId = (int)$_GET['agent_id'];
    $pdo->prepare("UPDATE whatsapp_messages SET assigned_to = ? WHERE phone = ?")->execute([$agentId, $phone]);
    header("Location: wa_messages.php?phone=" . urlencode($phone) . "&success=Assigned"); exit;
}

// Handle Manual Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    try {
        validateCsrf();
        $phone = $_POST['phone'] ?? '';
        $message = $_POST['message'] ?? '';
        if ($phone && $message) {
            $res = sendWhatsAppMessage($phone, $message, 'text');
            if ($res['status'] === 'success') {
                $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
                    ->execute([$phone, $message]);
                header("Location: wa_messages.php?tab=chat&phone=" . urlencode($phone) . "&success=Sent"); exit;
            } else {
                $error = "WhatsApp Error: " . ($res['error'] ?? 'Unknown');
            }
        }
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
}

// Handle Broadcast with Image Support
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    try {
        validateCsrf();
        $message = $_POST['broadcast_message'] ?? '';
        $target = $_POST['broadcast_target'] ?? 'customers'; // customers or all
        $alsoSite = isset($_POST['also_site_notif']);
        $mediaId = null;
        $localImgPath = '';

        // Upload media if present
        if (!empty($_FILES['broadcast_image']['tmp_name'])) {
            $tmpFile = $_FILES['broadcast_image']['tmp_name'];
            $fileExt = pathinfo($_FILES['broadcast_image']['name'], PATHINFO_EXTENSION);
            $localName = 'notif_' . time() . '.' . $fileExt;
            $uploadDir = __DIR__ . '/../uploads/notifications/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            if (move_uploaded_file($tmpFile, $uploadDir . $localName)) {
                $localImgPath = SITE_URL . '/uploads/notifications/' . $localName;
                $mediaId = uploadWhatsAppMedia($uploadDir . $localName, 'image');
            } else {
                throw new Exception("Local file upload failed.");
            }
            if (!$mediaId) throw new Exception("Media upload to WhatsApp failed.");
        }

        if ($alsoSite) {
            $notifTitle = $_POST['broadcast_title'] ?? 'New Offer! ⚡️';
            $stmtSite = $pdo->prepare("INSERT INTO site_notifications (title, message, image_url, target_url) VALUES (?, ?, ?, ?)");
            $stmtSite->execute([$notifTitle, $message, $localImgPath, SITE_URL . '/products.php']);
        }

        if ($message || $mediaId) {
            // Get recipients
            if ($target === 'all') {
                $stmt = $pdo->query("SELECT DISTINCT phone FROM users WHERE phone IS NOT NULL AND phone != ''");
            } else {
                $stmt = $pdo->query("SELECT DISTINCT phone FROM orders WHERE phone IS NOT NULL AND phone != ''");
            }
            $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $count = 0;
            $failCount = 0;
            
            foreach ($customers as $phone) {
                // If it's a 10 digit number, prepend 91 (default to India)
                $toPhone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($toPhone) === 10) $toPhone = '91' . $toPhone;

                if ($mediaId) {
                    $payload = ["id" => $mediaId, "caption" => $message];
                    $res = sendWhatsAppMessage($toPhone, $payload, 'image');
                } else {
                    $res = sendWhatsAppMessage($toPhone, $message, 'text');
                }

                if ($res['status'] === 'success') {
                    $logMsg = $mediaId ? "[BROADCAST IMAGE] " . $message : "[BROADCAST] " . $message;
                    $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
                        ->execute([$toPhone, $logMsg]);
                    $count++;
                } else {
                    $failCount++;
                }
            }
            $successMsg = "Broadcast completed! Sent to $count recipients. " . ($failCount > 0 ? "Failed: $failCount." : "");
        }
    } catch (Exception $e) { 
        $error = "Broadcast Error: " . $e->getMessage(); 
    }
}

$activeTab = $_GET['tab'] ?? 'chat';
$activePhone = $_GET['phone'] ?? '';

// Fetch Data with Robust Error Handling
$macros = []; $templates = []; $faqs = []; $configs = []; $admins = []; $convs = []; $messages = [];

try {
    $macros = $pdo->query("SELECT * FROM whatsapp_macros ORDER BY title ASC")->fetchAll();
    $templates = $pdo->query("SELECT * FROM whatsapp_templates ORDER BY name ASC")->fetchAll();
    $faqs = $pdo->query("SELECT * FROM bot_faqs ORDER BY id DESC")->fetchAll();
    $configs = $pdo->query("SELECT config_key, config_value FROM whatsapp_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $admins = $pdo->query("SELECT id, name FROM admin ORDER BY name ASC")->fetchAll();

    // Conversations grouped by phone
    $convsSql = "SELECT m1.phone, m1.message as last_msg, m1.created_at, m1.assigned_to, m1.chat_status,
                (SELECT COUNT(*) FROM whatsapp_messages WHERE phone = m1.phone AND direction = 'incoming' AND status = 'received') as unread 
                FROM whatsapp_messages m1 
                INNER JOIN (SELECT phone, MAX(id) as max_id FROM whatsapp_messages GROUP BY phone) m2 ON m1.id = m2.max_id 
                ORDER BY m1.created_at DESC LIMIT 100";
    $convs = $pdo->query($convsSql)->fetchAll();

    // Active Chat Messages
    if ($activePhone) {
        $stmt = $pdo->prepare("SELECT * FROM whatsapp_messages WHERE phone = ? ORDER BY created_at ASC");
        $stmt->execute([$activePhone]);
        $messages = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $dbError = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp & Chat Manager – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .wa-layout { 
            display: grid; 
            grid-template-columns: 320px 1fr; 
            height: 75vh;
            min-height: 500px;
            max-height: 900px;
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: var(--radius); 
            overflow: hidden;
            margin-bottom: 2rem;
            position: relative;
        }
        @media (max-width: 768px) {
            .wa-layout { 
                grid-template-columns: 1fr; 
                height: calc(100vh - 120px); 
                max-height: unset; 
                margin: -1.5rem -1.25rem 0; 
                width: calc(100% + 2.5rem); 
                border: none; 
                border-radius: 0; 
                z-index: 100;
            }
            .wa-sidebar { width: 100% !important; display: <?= $activePhone ? 'none' : 'flex' ?> !important; }
            .chat-view { width: 100% !important; display: <?= $activePhone ? 'flex' : 'none' ?> !important; }
            .admin-layout .main-content { padding: 0 !important; }
            .content-area { padding: 0 !important; }
            .msg { max-width: 88% !important; }
        }
        .wa-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; background: var(--bg-card); }
        .wa-search { padding: 1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .wa-chat-list { flex: 1; overflow-y: auto; padding: 0.5rem; min-height: 0; }
        .wa-chat-item { padding: 1.25rem 1rem; border-radius: var(--radius-sm); margin-bottom: 0.25rem; cursor: pointer; transition: 0.2s; position: relative; border: 1px solid transparent; }
        .wa-chat-item:hover { background: rgba(108,99,255,0.03); }
        .wa-chat-item.active { background: rgba(108,99,255,0.1); border-color: rgba(108,99,255,0.2); }
        .unread-dot { width: 10px; height: 10px; background: var(--primary); border-radius: 50%; position: absolute; right: 1.25rem; top: 1.5rem; box-shadow: 0 0 10px rgba(108,99,255,0.5); }
        
        /* ABSOLUTE POSITIONING APPROACH - BULLETPROOF */
        .chat-view { 
            position: relative;
            height: 100%;
            overflow: hidden;
            background: rgba(0,0,0,0.02); 
        }
        .chat-header { 
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 65px;
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--border); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: var(--card-bg); 
            z-index: 10;
        }
        .chat-footer { 
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 1rem 1.25rem; 
            border-top: 1px solid var(--border); 
            background: var(--card-bg); 
            z-index: 10;
        }
        .chat-body { 
            position: absolute;
            top: 65px;
            bottom: 110px; /* footer height */
            left: 0; right: 0;
            overflow-y: auto; 
            padding: 1rem 1rem; 
            display: flex; 
            flex-direction: column; 
            gap: 1rem;
            background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); /* subtle WA wallpaper */
            background-blend-mode: overlay;
        }
        .msg { max-width: 75%; padding: 0.75rem 1rem; border-radius: 14px; font-size: 0.9rem; line-height: 1.5; position: relative; }
        .msg.incoming { align-self: flex-start; background: var(--glass); border-bottom-left-radius: 2px; }
        .msg.outgoing { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 2px; }
        .msg-time { font-size: 0.65rem; opacity: 0.6; margin-top: 4px; text-align: right; }
        
        .reply-box { display: flex; gap: 0.75rem; align-items: flex-end; }
        .reply-box textarea { flex: 1; min-height: 44px; max-height: 100px; background: var(--glass); border: 1px solid var(--glass-border); border-radius: var(--radius-sm); padding: 0.6rem 0.75rem; color: var(--text-primary); outline: none; resize: none; font-size: 0.9rem; }
        
        .quick-replies { display: flex; gap: 0.5rem; margin-bottom: 0.6rem; overflow-x: auto; padding-bottom: 0.4rem; }
        .qr-btn { padding: 0.35rem 0.75rem; background: var(--glass); border: 1px solid var(--glass-border); border-radius: 20px; font-size: 0.72rem; cursor: pointer; white-space: nowrap; color: var(--text-secondary); }
        .qr-btn:hover { background: var(--primary); color: #fff; }
        
        /* Voice Recording UI Styles */
        .voice-rec-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: var(--card-bg);
            z-index: 20;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
            border-top: 1px solid var(--border);
        }
        .voice-rec-overlay.active { display: flex; }
        .recording-indicator { display: flex; align-items: center; gap: 0.75rem; color: var(--danger); font-weight: 600; }
        .recording-dot { width: 10px; height: 10px; background: var(--danger); border-radius: 50%; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
        .voice-controls { display: flex; gap: 1rem; }
        .voice-btn { border: none; background: none; font-size: 1.25rem; cursor: pointer; transition: 0.2s; }
        .voice-btn.cancel { color: var(--text-muted); }
        .voice-btn.stop { color: var(--primary); }
        .voice-btn:hover { transform: scale(1.15); }
        .btn-mic { background: var(--glass); border: 1px solid var(--glass-border); color: var(--primary); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; transition: 0.3s; cursor: pointer; }
        .btn-mic:hover { background: var(--primary); color: #fff; }
        .btn-mic.recording { background: var(--danger); color: #fff; border-color: transparent; }

        .tabs-nav { display: flex; gap: 2rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); overflow-x: auto; }
        .tab-link { padding: 0.75rem 0.25rem; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: 0.2s; white-space: nowrap; }
        .tab-link:hover { color: var(--primary); }
        .tab-link.active { color: var(--primary); border-bottom-color: var(--primary); }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        <div class="content-area">
            <?php if(isset($successMsg)): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
            <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if(isset($dbError)): ?>
                <div class="alert alert-warning">
                    <b>System Setup Required:</b> Some database tables or columns are missing. 
                    <a href="check_deployment.php" class="btn-primary btn-sm">Fix Database Now</a>
                    <br><small><?= $dbError ?></small>
                </div>
            <?php endif; ?>

            <div class="tabs-nav">
                <div class="tab-link <?= $activeTab==='chat'?'active':'' ?>" onclick="location.href='?tab=chat'">Chat Manager</div>
                <div class="tab-link <?= $activeTab==='delivery'?'active':'' ?>" onclick="location.href='?tab=delivery'">Delivery Notifications</div>
                <div class="tab-link <?= $activeTab==='bot'?'active':'' ?>" onclick="location.href='?tab=bot'">Bot Automation</div>
                <div class="tab-link <?= $activeTab==='broadcast'?'active':'' ?>" onclick="location.href='?tab=broadcast'">Broadcast</div>
                <div class="tab-link <?= $activeTab==='settings'?'active':'' ?>" onclick="location.href='?tab=settings'">API Settings</div>
            </div>

            <?php if($activeTab === 'chat'): ?>
            <div class="wa-layout">
                <div class="wa-sidebar">
                    <div class="wa-search">
                        <input type="text" class="filter-input w-100" placeholder="🔍 Search phone or message...">
                    </div>
                    <div class="wa-chat-list">
                        <?php foreach($convs as $c): ?>
                        <div class="wa-chat-item <?= $activePhone===$c['phone']?'active':'' ?>" onclick="location.href='?tab=chat&phone=<?= $c['phone'] ?>'">
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                                <span style="font-weight:700; font-size:0.9rem;"><?= $c['phone'] ?></span>
                                <span style="font-size:0.65rem; color:var(--text-muted);"><?= date('H:i', strtotime($c['created_at'])) ?></span>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-secondary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:90%;">
                                <?= htmlspecialchars($c['last_msg']) ?>
                            </div>
                            <?php if($c['unread'] > 0): ?><div class="unread-dot"></div><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="chat-view">
                    <?php if($activePhone): ?>
                    <div class="chat-header">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <a href="?tab=chat" class="btn-icon d-md-none" style="width:36px; height:36px;"><i class="bi bi-chevron-left"></i></a>
                            <div>
                                <div style="font-weight:800; font-size:1.1rem; line-height:1;"><?= $activePhone ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">
                                    Assigned: 
                                    <select style="background:none; border:none; padding:0; font-size:inherit; color:var(--primary); font-weight:600;" onchange="location.href='?tab=chat&phone=<?= $activePhone ?>&assign_phone=<?= $activePhone ?>&agent_id='+this.value">
                                        <option value="">Unassigned</option>
                                        <?php foreach($admins as $adm): ?>
                                        <option value="<?= $adm['id'] ?>" <?= ($messages[0]['assigned_to'] ?? '') == $adm['id'] ? 'selected' : '' ?>><?= $adm['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <a href="orders.php?search=<?= $activePhone ?>" class="btn-primary btn-sm d-none d-sm-flex">Orders</a>
                            <button class="btn-icon" title="Resolve Chat"><i class="bi bi-check2-circle"></i></button>
                        </div>
                    </div>
                    <div class="chat-body" id="chatWindow">
                        <?php foreach($messages as $m): ?>
                        <div class="msg <?= $m['direction'] ?>">
                            <?php
                            $msgText = $m['message'];
                            if (preg_match('/^\[AUDIO\]:(.+)$/s', $msgText, $mt)) {
                                $audioUrl = $mt[1];
                                echo '<div style="display:flex;flex-direction:column;gap:5px;"><span style="font-size:0.75rem;opacity:0.7;">🎙 Voice Message</span>';
                                echo '<audio controls style="max-width:220px;height:40px;"><source src="'.htmlspecialchars($audioUrl).'"></audio>';
                                echo '<a href="'.htmlspecialchars($audioUrl).'" download style="font-size:0.65rem;opacity:0.7;text-decoration:underline;">Download</a></div>';
                            } elseif (preg_match('/^\[Voice Message\]:(.+)$/s', $msgText, $mt)) {
                                $audioUrl = $mt[1];
                                echo '<div style="display:flex;flex-direction:column;gap:5px;"><span style="font-size:0.75rem;opacity:0.7;">🎙 Voice Message</span>';
                                echo '<audio controls style="max-width:220px;height:40px;"><source src="'.htmlspecialchars($audioUrl).'"></audio>';
                                echo '<a href="'.htmlspecialchars($audioUrl).'" download style="font-size:0.65rem;opacity:0.7;text-decoration:underline;">Download</a></div>';
                            } elseif (preg_match('/^\[IMAGE\]:([^|]+)(?:\|CAPTION:(.*))?$/s', $msgText, $mt)) {
                                $imgUrl = $mt[1]; $cap = $mt[2] ?? '';
                                echo '<div style="display:flex;flex-direction:column;gap:4px;">';
                                echo '<a href="'.htmlspecialchars($imgUrl).'" target="_blank"><img src="'.htmlspecialchars($imgUrl).'" style="max-width:220px;border-radius:8px;display:block;"></a>';
                                if ($cap) echo '<span style="font-size:0.8rem;">'.htmlspecialchars($cap).'</span>';
                                echo '</div>';
                            } elseif (preg_match('/^\[VIDEO\]:([^|]+)(?:\|CAPTION:(.*))?$/s', $msgText, $mt)) {
                                $vidUrl = $mt[1]; $cap = $mt[2] ?? '';
                                echo '<div style="display:flex;flex-direction:column;gap:4px;">';
                                echo '<video controls style="max-width:220px;border-radius:8px;"><source src="'.htmlspecialchars($vidUrl).'"></video>';
                                if ($cap) echo '<span style="font-size:0.8rem;">'.htmlspecialchars($cap).'</span>';
                                echo '</div>';
                            } elseif (preg_match('/^\[DOCUMENT\]:([^|]+)\|FILENAME:(.+)$/s', $msgText, $mt)) {
                                $docUrl = $mt[1]; $fname = $mt[2];
                                echo '<div style="display:flex;align-items:center;gap:0.6rem;background:rgba(0,0,0,0.08);padding:0.6rem 0.8rem;border-radius:8px;">';
                                echo '<i class="bi bi-file-earmark-pdf" style="font-size:1.8rem;"></i>';
                                echo '<div><div style="font-size:0.8rem;font-weight:700;">'.htmlspecialchars($fname).'</div>';
                                echo '<a href="'.htmlspecialchars($docUrl).'" download style="font-size:0.7rem;text-decoration:underline;">Download</a></div></div>';
                            } elseif (preg_match('/^\[STICKER\]:(.+)$/s', $msgText, $mt)) {
                                echo '<img src="'.htmlspecialchars($mt[1]).'" style="max-width:150px;">';
                            } elseif (preg_match('/^\[LOCATION\]:lat=([^|]+)\|lng=([^|]+)\|name=([^|]*)\|addr=(.*)$/s', $msgText, $mt)) {
                                $lat=$mt[1]; $lng=$mt[2]; $lname=$mt[3]; $laddr=$mt[4];
                                echo '<div style="display:flex;flex-direction:column;gap:4px;">';
                                echo '<span>📍 <b>'.htmlspecialchars($lname ?: 'Location').'</b></span>';
                                if ($laddr) echo '<span style="font-size:0.75rem;">'.htmlspecialchars($laddr).'</span>';
                                echo '<a href="https://maps.google.com/?q='.$lat.','.$lng.'" target="_blank" style="font-size:0.75rem;text-decoration:underline;">Open in Maps</a></div>';
                            } else {
                                echo nl2br(htmlspecialchars($msgText));
                            }
                            ?>
                            <div class="msg-time"><?= date('h:i A', strtotime($m['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chat-footer">
                        <div class="quick-replies">
                            <button class="qr-btn" onclick="toggleMacroModal()">+ Macro</button>
                            <button class="qr-btn" onclick="openMediaModal('image')">🖼 Image</button>
                            <button class="qr-btn" onclick="openMediaModal('video')">🎬 Video</button>
                            <button class="qr-btn" onclick="openMediaModal('document')">📄 Document</button>
                            <button class="qr-btn" onclick="openMediaModal('audio')">🎙 Voice File</button>
                            <button class="qr-btn" onclick="openMediaModal('product_link')">🔗 Product Link</button>
                            <?php foreach($macros as $mac): ?>
                            <button class="qr-btn" onclick="applyMacro('<?= addslashes($mac['content']) ?>')"><?= htmlspecialchars($mac['title']) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" action="wa_messages.php?phone=<?= $activePhone ?>&tab=chat" id="textReplyForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="phone" value="<?= $activePhone ?>">
                            <input type="hidden" name="send_reply" value="1">
                            <div class="reply-box">
                                <button type="button" class="btn-mic" id="startVoiceBtn" onclick="startVoiceRecording()" title="Record voice"><i class="bi bi-mic-fill"></i></button>
                                <textarea name="message" id="messageInput" placeholder="Type your message..." required></textarea>
                                <button type="submit" class="btn-primary" style="padding: 0.8rem;"><i class="bi bi-send-fill"></i></button>
                            </div>
                        </form>
                        <!-- Voice Recording UI -->
                        <div class="voice-rec-overlay" id="voiceOverlay">
                            <div class="recording-indicator">
                                <div class="recording-dot"></div>
                                <span id="recordingTimer">00:00</span>
                            </div>
                            <div class="voice-controls">
                                <button class="voice-btn cancel" onclick="cancelVoiceRecording()" title="Cancel"><i class="bi bi-trash3-fill"></i></button>
                                <button class="voice-btn stop" onclick="stopVoiceRecording()" title="Send"><i class="bi bi-send-fill"></i></button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-muted);">
                        <i class="bi bi-whatsapp" style="font-size:4rem; margin-bottom:1rem; opacity:0.1;"></i>
                        <p>Select a customer to start chatting</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif($activeTab === 'delivery'): ?>
            <div class="grid-2-1 admin-grid-2-1">
                <div class="data-table-card">
                    <div class="data-table-header">
                        <div class="data-table-title">Notification Templates</div>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead><tr><th>Name</th><th>Content</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach($templates as $t): ?>
                                <tr>
                                    <td style="font-weight:700;"><?= strtoupper(str_replace('_',' ',$t['name'])) ?></td>
                                    <td>
                                        <form method="POST" id="form-t-<?= $t['id'] ?>">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="update_template" value="1">
                                            <textarea name="content" class="form-control" style="font-size:0.8rem; min-height:80px;"><?= htmlspecialchars($t['content']) ?></textarea>
                                        </form>
                                    </td>
                                    <td><button type="submit" form="form-t-<?= $t['id'] ?>" class="btn-primary btn-sm">Save</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="form-card">
                    <h3 style="margin-bottom:1.5rem;">Send Delivery Update</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Order ID / Order Number</label>
                            <input type="text" name="order_identifier" class="form-control" placeholder="e.g. ORD12345">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Update Type</label>
                            <select name="notif_type" class="form-control">
                                <option value="order_delay">Order Delay Update</option>
                                <option value="delivery_avail">Delivery Availability</option>
                                <option value="order_shipped">Shipping Status</option>
                            </select>
                        </div>
                        <button type="button" class="btn-primary" style="width:100%; justify-content:center;" onclick="previewNotif()">Fetch Order & Preview</button>
                    </form>

                    <div id="notifPreviewArea" style="margin-top:2rem; display:none; padding-top:2rem; border-top:1px solid var(--border);">
                        <h4 style="margin-bottom:1rem; font-size:0.9rem;">Message Preview</h4>
                        <input type="hidden" id="notifPhone">
                        <input type="hidden" id="notifOrderId">
                        <textarea id="notifPreviewText" class="form-control" style="min-height:120px; font-family:var(--font); font-size:0.85rem; border-color:var(--primary);"></textarea>
                        <p style="font-size:0.7rem; color:var(--text-muted); margin:0.5rem 0 1.25rem;">You can edit the message before sending.</p>
                        <button type="button" class="btn-primary" style="width:100%; justify-content:center;" onclick="sendNotif()">
                            <i class="bi bi-send-fill"></i> Send Notification Now
                        </button>
                    </div>
                </div>
            </div>

            <?php elseif($activeTab === 'bot'): ?>
            <div class="grid-2-1 admin-grid-2-1">
                <div class="data-table-card">
                    <div class="data-table-header">
                        <div class="data-table-title">FAQ Auto-Responses</div>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead><tr><th>Question</th><th>Keywords</th><th style="width:70px;">Status</th></tr></thead>
                            <tbody>
                                <?php foreach($faqs as $f): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($f['question']) ?></div>
                                        <div style="font-size:0.75rem; margin-top:0.25rem;">Ans: <?= htmlspecialchars($f['answer']) ?></div>
                                    </td>
                                    <td><code><?= htmlspecialchars($f['keywords']) ?></code></td>
                                    <td><span class="badge badge-active">Active</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="form-card">
                    <h3 style="margin-bottom:1.5rem;">Add FAQ</h3>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="save_faq" value="1">
                        <div class="form-group">
                            <label class="form-label">Customer Question</label>
                            <input type="text" name="question" class="form-control" required placeholder="What is return policy?">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Auto Reply</label>
                            <textarea name="answer" class="form-control" required placeholder="Our return policy is..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keywords (Comma separated)</label>
                            <input type="text" name="keywords" class="form-control" placeholder="return, refund, policy">
                        </div>
                        <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">Save FAQ</button>
                    </form>
                </div>
            </div>

            <?php elseif($activeTab === 'broadcast'): ?>
            <div class="form-card" style="max-width: 800px; margin: 0 auto; padding: 2.5rem;">
                <div style="text-align:center; margin-bottom:2.5rem;">
                    <div style="width:70px; height:70px; border-radius:50%; background:rgba(108,99,255,0.1); display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem;">
                        <i class="bi bi-megaphone" style="font-size:2.2rem; color:var(--primary);"></i>
                    </div>
                    <h2 style="font-weight:800; font-size:1.8rem; margin-bottom:0.5rem;">Marketing Broadcast</h2>
                    <p class="text-muted" style="max-width:500px; margin:0 auto;">Send direct WhatsApp promotions or announcements to your audience.</p>
                </div>

                <div class="alert alert-info" style="margin-bottom:2rem; background:rgba(108,99,255,0.05); border:1px solid rgba(108,99,255,0.1); color:var(--text-primary);">
                    <div style="font-weight:700; margin-bottom:0.5rem;"><i class="bi bi-info-circle-fill"></i> Marketing Tips:</div>
                    <ul style="font-size:0.85rem; padding-left:1.25rem; margin:0;">
                        <li>Use an eye-catching **Offer Banner** (image) for better conversion.</li>
                        <li>Meta limits broadcast speed and volume based on your account quality.</li>
                        <li>Avoid spam; customers may report unsolicited messages.</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="send_broadcast" value="1">
                    
                    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
                        <div class="form-group">
                            <label class="form-label">Broadcast Target</label>
                            <select name="broadcast_target" class="form-control" style="background:var(--bg-lighter);">
                                <option value="customers">Customers (Only those who ordered)</option>
                                <option value="all">All Registered Users (Full database)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Offer Banner (Image)</label>
                            <input type="file" name="broadcast_image" class="form-control" accept="image/*">
                            <small class="text-muted" style="font-size:0.7rem;">Recommended: 1200x628px (Landscape)</small>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:1.5rem;">
                        <label class="form-label">Broadcast/Notification Title</label>
                        <input type="text" name="broadcast_title" class="form-control" placeholder="e.g. Flash Sale Live! ⚡️" value="New Offer! ⚡️">
                    </div>

                    <div class="form-group" style="margin-bottom:2.5rem;">
                        <label class="form-label" style="display:flex; justify-content:space-between;">
                            <span>Broadcast Content (Caption)</span>
                            <span style="font-weight:normal; font-size:0.75rem; color:var(--text-muted);">Max Recommended: 1000 chars</span>
                        </label>
                        <textarea name="broadcast_message" class="form-control" style="min-height:220px; padding:1.25rem; font-size:1rem; line-height:1.6;" placeholder="Hey there! ⚡️ Big Sale is LIVE! Use coupon: LUXE50 ..."></textarea>
                    </div>

                    <div style="background:var(--bg-lighter); padding:1.5rem; border-radius:var(--radius-sm); margin-bottom:2.5rem; display:flex; flex-direction:column; gap:1rem;">
                        <div class="form-check" style="display:flex; gap:0.75rem; align-items:flex-start;">
                            <input type="checkbox" name="also_site_notif" id="siteNotifCheck" checked style="margin-top:0.3rem;">
                            <label for="siteNotifCheck" style="font-size:0.9rem; font-weight:700; cursor:pointer;">Also show as pop-up notification on Website 🌐</label>
                        </div>
                        <div class="form-check" style="display:flex; gap:0.75rem; align-items:flex-start;">
                            <input type="checkbox" id="broadcastConfirm" required style="margin-top:0.3rem;">
                            <label for="broadcastConfirm" style="font-size:0.85rem; cursor:pointer;">I understand that sending spam may lead to my WhatsApp number being blocked by Meta. I will only send relevant offers to my users.</label>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:1.25rem; font-size:1.1rem; border-radius:var(--radius); box-shadow:0 10px 30px rgba(108,99,255,0.25);">
                        <i class="bi bi-send-check-fill"></i> Launch Broadcast Campaign
                    </button>
                </form>
            </div>

            <?php else: ?>
            <!-- API Settings -->
            <div class="form-card" style="max-width: 800px; margin: 0 auto;">
                <h3 style="margin-bottom:1.5rem;">WhatsApp Integration Settings</h3>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="update_wa_config" value="1">
                    
                    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                        <div class="form-group">
                            <label class="form-label">Bot Status</label>
                            <select name="bot_enabled" class="form-control">
                                <option value="1" <?= ($configs['bot_enabled'] ?? '1') == '1' ? 'selected' : '' ?>>✅ Bot Enabled</option>
                                <option value="0" <?= ($configs['bot_enabled'] ?? '1') == '0' ? 'selected' : '' ?>>❌ Bot Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">API Version</label>
                            <input type="text" name="wa_version" class="form-control" value="<?= htmlspecialchars($configs['wa_version'] ?? WA_VERSION) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Permanent Access Token</label>
                        <textarea name="wa_access_token" class="form-control" style="font-size:0.75rem;"><?= htmlspecialchars($configs['wa_access_token'] ?? WA_ACCESS_TOKEN) ?></textarea>
                    </div>

                    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
                         <div class="form-group">
                            <label class="form-label">Phone Number ID</label>
                            <input type="text" name="wa_phone_number_id" class="form-control" value="<?= htmlspecialchars($configs['wa_phone_number_id'] ?? WA_PHONE_NUMBER_ID) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Webhook Verify Token</label>
                            <input type="text" name="wa_webhook_token" class="form-control" value="<?= htmlspecialchars($configs['wa_webhook_token'] ?? WA_WEBHOOK_VERIFY_TOKEN) ?>">
                        </div>
                    </div>

                    <hr style="opacity:0.1; margin:2rem 0;">
                    <h4 style="margin-bottom:1.25rem;">Bot Core Messages</h4>
                    
                    <div class="form-group">
                        <label class="form-label">Welcome Message (Menu)</label>
                        <textarea name="bot_welcome_msg" class="form-control" style="min-height:120px;"><?= htmlspecialchars($configs['bot_welcome_msg'] ?? "👋 Hello! Welcome to *" . SITE_NAME . "*\nHow can I help you today?") ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fallback Message (Unknown query)</label>
                        <textarea name="bot_fallback_msg" class="form-control"><?= htmlspecialchars($configs['bot_fallback_msg'] ?? "Sorry, I didn't understand that. Reply MENU to see options.") ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%; justify-content:center; padding:1rem; margin-top:1rem;">Save All Settings</button>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Media Send Modal -->
<div id="mediaModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:480px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h3 id="mediaModalTitle">Send Media</h3>
            <button class="btn-icon" onclick="closeMediaModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="mediaModalBody">
            <!-- Injected by JS -->
        </div>
        <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.5rem;">
            <button type="button" class="btn-icon" onclick="closeMediaModal()">Cancel</button>
            <button type="button" class="btn-primary" id="mediaModalSendBtn" onclick="sendMedia()"><i class="bi bi-send-fill"></i> Send</button>
        </div>
    </div>
</div>

<!-- Macro Modal -->
<div id="macroModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <h3 style="margin-bottom:1.5rem;">Add Quick Reply Macro</h3>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="save_macro" value="1">
            <div class="form-group">
                <label class="form-label">Macro Title (Short)</label>
                <input type="text" name="title" class="form-control" placeholder="Refund Info" required>
            </div>
            <div class="form-group">
                <label class="form-label">Full Content</label>
                <textarea name="content" class="form-control" required placeholder="Hi! Your refund has been processed..."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                <button type="button" class="btn-icon" onclick="toggleMacroModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Macro</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ── Media Modal ──────────────────────────────────────────────────
    let currentMediaType = '';
    const ACTIVE_PHONE = '<?= addslashes($activePhone) ?>';

    const mediaConfigs = {
        image: {
            title: '🖼 Send Image',
            accept: 'image/jpeg,image/png,image/gif,image/webp',
            label: 'Choose Image (JPG, PNG, GIF, WebP – max 16 MB)',
            hasCaption: true
        },
        video: {
            title: '🎬 Send Video',
            accept: 'video/mp4,video/3gp',
            label: 'Choose Video (MP4, 3GP – max 16 MB)',
            hasCaption: true
        },
        document: {
            title: '📄 Send Document',
            accept: '.pdf,.doc,.docx,.xls,.xlsx,.txt',
            label: 'Choose Document (PDF, Word, Excel – max 16 MB)',
            hasCaption: false
        },
        audio: {
            title: '🎙 Send Audio File',
            accept: 'audio/mpeg,audio/ogg,audio/wav',
            label: 'Choose Audio File (MP3, OGG, WAV – max 16 MB)',
            hasCaption: false
        },
        product_link: {
            title: '🔗 Send Product Link',
            accept: '',
            label: '',
            hasCaption: false
        }
    };

    function openMediaModal(type) {
        currentMediaType = type;
        const cfg = mediaConfigs[type];
        document.getElementById('mediaModalTitle').textContent = cfg.title;
        let html = '';
        if (type === 'product_link') {
            html = `<div class="form-group">
                <label class="form-label">Product URL</label>
                <input type="url" id="productLinkUrl" class="form-control" placeholder="https://yoursite.com/product.php?id=5" required>
            </div>
            <div class="form-group" style="margin-top:1rem;">
                <label class="form-label">Message Caption (optional)</label>
                <textarea id="productLinkMsg" class="form-control" rows="3" placeholder="Check out this product! ...\nhttps://yoursite.com/product.php?id=5"></textarea>
            </div>`;
        } else {
            html = `<div class="form-group">
                <label class="form-label">${cfg.label}</label>
                <input type="file" id="mediaFile" class="form-control" accept="${cfg.accept}" required>
            </div>`;
            if (cfg.hasCaption) {
                html += `<div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Caption (optional)</label>
                    <input type="text" id="mediaCaption" class="form-control" placeholder="Enter caption...">
                </div>`;
            }
        }
        document.getElementById('mediaModalBody').innerHTML = html;
        document.getElementById('mediaModal').style.display = 'flex';
    }

    function closeMediaModal() {
        document.getElementById('mediaModal').style.display = 'none';
        currentMediaType = '';
    }

    async function sendMedia() {
        if (!ACTIVE_PHONE) { alert('No active chat selected'); return; }
        const btn = document.getElementById('mediaModalSendBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';

        const fd = new FormData();
        fd.append('phone', ACTIVE_PHONE);
        fd.append('type', currentMediaType);

        if (currentMediaType === 'product_link') {
            const url = document.getElementById('productLinkUrl')?.value.trim();
            const msg = document.getElementById('productLinkMsg')?.value.trim() || url;
            if (!url) { alert('Please enter a product URL'); btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Send'; return; }
            fd.append('product_link', url);
            fd.append('product_msg', msg);
        } else {
            const fileInp = document.getElementById('mediaFile');
            if (!fileInp || !fileInp.files[0]) { alert('Please select a file'); btn.disabled=false; btn.innerHTML='<i class="bi bi-send-fill"></i> Send'; return; }
            fd.append('media', fileInp.files[0]);
            const cap = document.getElementById('mediaCaption')?.value || '';
            if (cap) fd.append('caption', cap);
        }

        try {
            const resp = await fetch('ajax/upload_media.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                closeMediaModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch(e) {
            alert('Network error. Please try again.');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Send';
    }

    // ── Macro Modal ──────────────────────────────────────────────────
    function toggleMacroModal() {
        const m = document.getElementById('macroModal');
        m.style.display = m.style.display === 'none' ? 'flex' : 'none';
    }
    function applyMacro(content) {
        document.getElementById('messageInput').value = content;
        document.getElementById('messageInput').focus();
    }
    
    // Delivery Notification Preview Logic
    async function previewNotif() {
        const orderRef = document.querySelector('[name="order_identifier"]').value;
        const template = document.querySelector('[name="notif_type"]').value;
        if(!orderRef) return alert('Enter Order ID');
        
        const btn = event.target;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Fetching...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'preview');
        formData.append('order_ref', orderRef);
        formData.append('template', template);

        try {
            const resp = await fetch('ajax/wa_notifications.php', { method:'POST', body:formData });
            const data = await resp.json();
            if(data.success) {
                document.getElementById('notifPreviewArea').style.display = 'block';
                document.getElementById('notifPreviewText').value = data.preview;
                document.getElementById('notifPhone').value = data.phone;
                document.getElementById('notifOrderId').value = data.order_id;
            } else {
                alert(data.message);
            }
        } catch(e) { console.error(e); }
        btn.innerHTML = 'Fetch Order & Preview';
        btn.disabled = false;
    }

    async function sendNotif() {
        const btn = event.target;
        btn.innerHTML = 'Sending...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('phone', document.getElementById('notifPhone').value);
        formData.append('message', document.getElementById('notifPreviewText').value);
        formData.append('order_id', document.getElementById('notifOrderId').value);

        try {
            const resp = await fetch('ajax/wa_notifications.php', { method:'POST', body:formData });
            const data = await resp.json();
            alert(data.message);
            if(data.success) document.getElementById('notifPreviewArea').style.display = 'none';
        } catch(e) { console.error(e); }
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Send Notification Now';
        btn.disabled = false;
    }

    const win = document.getElementById('chatWindow');
    if(win) win.scrollTop = win.scrollHeight;

    // VOICE RECORDING LOGIC
    let mediaRecorder;
    let audioChunks = [];
    let recordingStartTime;
    let timerInterval;

    async function startVoiceRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return alert("Voice recording is not supported in this browser.");
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];

            mediaRecorder.ondataavailable = event => {
                audioChunks.push(event.data);
            };

            mediaRecorder.onstop = async () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/ogg; codecs=opus' });
                if (audioChunks.length > 0) {
                    await uploadVoiceMessage(audioBlob);
                }
                stream.getTracks().forEach(track => track.stop()); // Release mic
            };

            mediaRecorder.start();
            
            // Show UI
            document.getElementById('voiceOverlay').classList.add('active');
            recordingStartTime = Date.now();
            updateTimer();
            timerInterval = setInterval(updateTimer, 1000);
            
        } catch (err) {
            console.error("Error accessing mic:", err);
            alert("Could not access microphone.");
        }
    }

    function updateTimer() {
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const mins = String(Math.floor(elapsed / 60)).padStart(2, '0');
        const secs = String(elapsed % 60).padStart(2, '0');
        document.getElementById('recordingTimer').textContent = `${mins}:${secs}`;
    }

    function stopVoiceRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            hideVoiceOverlay();
        }
    }

    function cancelVoiceRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            audioChunks = []; // Clear chunks so it doesn't upload on stop
            mediaRecorder.stop();
            hideVoiceOverlay();
        }
    }

    function hideVoiceOverlay() {
        clearInterval(timerInterval);
        document.getElementById('voiceOverlay').classList.remove('active');
    }

    async function uploadVoiceMessage(blob) {
        const formData = new FormData();
        formData.append('audio', blob, 'recording.ogg');
        formData.append('phone', '<?= $activePhone ?>');

        try {
            const resp = await fetch('ajax/upload_voice.php', {
                method: 'POST',
                body: formData
            });
            const data = await resp.json();
            if (data.success) {
                location.reload(); // Reload to show the new message
            } else {
                alert(data.message);
            }
        } catch (err) {
            console.error("Upload failed:", err);
            alert("Failed to send voice message.");
        }
    }
</script>
</body>
</html>
