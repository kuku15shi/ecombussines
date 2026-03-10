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

// Handle Broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    try {
        validateCsrf();
        $message = $_POST['broadcast_message'] ?? '';
        if($message) {
            $stmt = $pdo->query("SELECT DISTINCT phone FROM orders WHERE phone IS NOT NULL AND phone != ''");
            $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $count = 0;
            foreach($customers as $phone) {
                $res = sendWhatsAppMessage($phone, $message, 'text');
                if($res['status'] === 'success') {
                    $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
                        ->execute([$phone, $message]);
                    $count++;
                }
            }
            $successMsg = "Broadcast sent successfully to $count customers!";
        }
    } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
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
            height: 70vh;
            min-height: 500px;
            max-height: 800px;
            background: var(--glass); 
            border: 1px solid var(--glass-border); 
            border-radius: var(--radius); 
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .wa-sidebar { border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow: hidden; }
        .wa-search { padding: 1rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .wa-chat-list { flex: 1; overflow-y: auto; padding: 0.5rem; min-height: 0; }
        .wa-chat-item { padding: 0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom: 0.25rem; cursor: pointer; transition: 0.2s; position: relative; }
        .wa-chat-item:hover { background: rgba(255,255,255,0.05); }
        .wa-chat-item.active { background: rgba(108,99,255,0.1); border: 1px solid var(--glass-border); }
        .unread-dot { width: 8px; height: 8px; background: var(--primary); border-radius: 50%; position: absolute; right: 1rem; top: 1.25rem; }
        
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
            bottom: 135px; /* approx footer height */
            left: 0; right: 0;
            overflow-y: auto; 
            padding: 1rem 1.5rem; 
            display: flex; 
            flex-direction: column; 
            gap: 1rem;
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
                        <div>
                            <div style="font-weight:800;"><?= $activePhone ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted);">
                                Assigned: 
                                <select onchange="location.href='?tab=chat&phone=<?= $activePhone ?>&assign_phone=<?= $activePhone ?>&agent_id='+this.value">
                                    <option value="">Unassigned</option>
                                    <?php foreach($admins as $adm): ?>
                                    <option value="<?= $adm['id'] ?>" <?= ($messages[0]['assigned_to'] ?? '') == $adm['id'] ? 'selected' : '' ?>><?= $adm['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <a href="orders.php?search=<?= $activePhone ?>" class="btn-primary btn-sm">Orders</a>
                            <button class="btn-icon" title="Resolve Chat"><i class="bi bi-check2-circle"></i></button>
                        </div>
                    </div>
                    <div class="chat-body" id="chatWindow">
                        <?php foreach($messages as $m): ?>
                        <div class="msg <?= $m['direction'] ?>">
                            <?= nl2br(htmlspecialchars($m['message'])) ?>
                            <div class="msg-time"><?= date('h:i A', strtotime($m['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chat-footer">
                        <div class="quick-replies">
                            <button class="qr-btn" onclick="toggleMacroModal()">+ Add Macro</button>
                            <?php foreach($macros as $mac): ?>
                            <button class="qr-btn" onclick="applyMacro('<?= addslashes($mac['content']) ?>')"><?= htmlspecialchars($mac['title']) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <form method="POST" action="wa_messages.php?phone=<?= $activePhone ?>&tab=chat" id="textReplyForm">
                            <?= csrfField() ?>
                            <input type="hidden" name="phone" value="<?= $activePhone ?>">
                            <input type="hidden" name="send_reply" value="1">
                            <div class="reply-box">
                                <button type="button" class="btn-mic" id="startVoiceBtn" onclick="startVoiceRecording()"><i class="bi bi-mic-fill"></i></button>
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
            <!-- Same as before but with better layout -->
            <div class="form-card" style="max-width: 700px; margin: 0 auto;">
                <h2 style="margin-bottom:1rem;">Marketing Broadcast</h2>
                <p class="text-muted" style="margin-bottom:2rem;">Send a direct WhatsApp message to all customers in your database.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">Message Content</label>
                        <textarea name="broadcast_message" class="form-control" style="min-height:150px;" placeholder="Write your offer here..."></textarea>
                    </div>
                    <button type="submit" name="send_broadcast" class="btn-primary" style="width:100%; justify-content:center; padding:1rem;">
                        <i class="bi bi-megaphone"></i> Send to All Customers
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
