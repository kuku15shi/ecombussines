<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../includes/whatsapp_functions.php';

requireAdminLogin();
$pageTitle = 'WhatsApp Dashboard';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_wa_config'])) {
    validateCsrf();
    $configs = [
        'wa_access_token' => trim($_POST['wa_access_token'] ?? ''),
        'wa_phone_number_id' => trim($_POST['wa_phone_number_id'] ?? ''),
        'wa_version' => trim($_POST['wa_version'] ?? 'v20.0'),
        'wa_webhook_token' => trim($_POST['wa_webhook_token'] ?? ''),
        'bot_enabled' => $_POST['bot_enabled'] ?? '1'
    ];

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(50) UNIQUE NOT NULL,
            config_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        $stmt = $pdo->prepare("INSERT INTO whatsapp_config (config_key, config_value) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
        foreach ($configs as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        $successMsg = "Settings updated successfully!";
    } catch (Exception $e) { $error = "Config Error: " . $e->getMessage(); }
}

// Handle Broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    validateCsrf();
    $message = $_POST['broadcast_message'] ?? '';
    if($message) {
        $stmt = $pdo->query("SELECT DISTINCT phone FROM orders WHERE phone IS NOT NULL AND phone != ''");
        $customers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach($customers as $phone) {
            $res = sendWhatsAppMessage($phone, $message, 'text');
            if($res['status'] === 'success') $count++;
        }
        $successMsg = "Broadcast sent successfully to $count customers!";
    }
}

// Test Connection handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    validateCsrf();
    $testPhone = $_POST['test_phone'] ?? '';
    if($testPhone) {
        $res = sendWhatsAppMessage($testPhone, "Hello! This is a test message from " . SITE_NAME . ". Connection is working! ✅", 'text');
        if($res['status'] === 'success') {
            $successMsg = "Test message sent successfully to $testPhone!";
        } else {
            $error = "Test Failed: " . ($res['error'] ?? 'Unknown API Error');
        }
    }
}

// Manual Reply handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    validateCsrf();
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if ($phone && $message) {
        $res = sendWhatsAppMessage($phone, $message, 'text');
        if ($res['status'] === 'success') {
            try {
                $pdo->prepare("INSERT INTO whatsapp_messages (phone, message, direction, status) VALUES (?, ?, 'outgoing', 'sent')")
                    ->execute([$phone, $message]);
                header('Location: wa_messages.php?success=1&phone=' . urlencode($phone)); exit;
            } catch (Exception $e) { $error = "DB Error: " . $e->getMessage(); }
        } else {
            $error = "WhatsApp Error: " . ($res['error'] ?? 'Unknown Error');
        }
    }
}

$activeTab = $_GET['tab'] ?? 'chat';
$activePhone = $_GET['phone'] ?? '';
$messages = [];
if ($activePhone) {
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_messages WHERE phone = ? ORDER BY created_at ASC");
    $stmt->execute([$activePhone]);
    $messages = $stmt->fetchAll();
    $activeTab = 'chat';
}

// Fetch Configs
$dbConfig = [];
try {
    $res = $pdo->query("SELECT config_key, config_value FROM whatsapp_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $dbConfig = $res;
} catch (Exception $e) {}

// Get unique conversations
$convs = [];
try {
    $convs = $pdo->query("SELECT phone, MAX(created_at) as last_msg, 
           (SELECT message FROM whatsapp_messages WHERE phone = t.phone ORDER BY created_at DESC LIMIT 1) as last_text 
           FROM (SELECT DISTINCT phone, created_at FROM whatsapp_messages) as t 
           GROUP BY phone ORDER BY last_msg DESC LIMIT 50")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WhatsApp & Chatbot – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <style>
    .wa-tabs { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border); }
    .wa-tab-btn { padding: 0.75rem 1.5rem; cursor: pointer; color: var(--text-muted); font-weight: 600; border-bottom: 2px solid transparent; transition: 0.2s; }
    .wa-tab-btn:hover { color: var(--primary); }
    .wa-tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); background: rgba(108,99,255,0.05); }

    .chat-container {
      display: grid;
      grid-template-columns: 320px 1fr;
      background: var(--glass);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius);
      height: calc(100vh - 240px);
      overflow: hidden;
    }
    .chat-sidebar { border-right: 1px solid var(--glass-border); display: flex; flex-direction: column; overflow: hidden; }
    .chat-list { flex: 1; overflow-y: auto; padding: 0.75rem; }
    .chat-item { padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 0.5rem; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
    .chat-item:hover { background: rgba(255,255,255,0.05); }
    .chat-item.active { background: rgba(255,255,255,0.1); border-color: var(--primary); }
    .chat-main { display: flex; flex-direction: column; background: rgba(0,0,0,0.1); overflow: hidden; }
    .chat-header { padding: 1.25rem; border-bottom: 1px solid var(--glass-border); background: var(--glass); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .message-bubble { max-width: 70%; padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; position: relative; }
    .message-bubble.incoming { background: var(--glass); align-self: flex-start; border-bottom-left-radius: 2px; }
    .message-bubble.outgoing { background: var(--primary); color: #fff; align-self: flex-end; border-bottom-right-radius: 2px; }
    .message-time { font-size: 0.65rem; opacity: 0.6; margin-top: 0.4rem; text-align: right; }
    .chat-input-area { padding: 1.25rem; background: var(--glass); border-top: 1px solid var(--glass-border); flex-shrink: 0; }
    .chat-input-box { display: flex; gap: 0.75rem; }
    .chat-input-box textarea { flex: 1; border-radius: var(--radius-sm); border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: var(--text-primary); padding: 0.75rem; min-height: 48px; max-height: 120px; outline: none; transition: 0.2s; resize: none; font-family: var(--font); font-size: 0.875rem; }
    .chat-back-btn { display: none; margin-right: 0.75rem; }

    @media (max-width: 767px) {
      .chat-container { grid-template-columns: 1fr; height: calc(100vh - 200px); }
      .chat-container.chat-open .chat-sidebar { display: none; }
      .chat-container:not(.chat-open) .chat-main { display: none; }
      .chat-back-btn { display: inline-flex !important; }
    }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">
      <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
      <?php if(isset($successMsg)): ?><div class="alert alert-success"><?= $successMsg ?></div><?php endif; ?>
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success">Message sent!</div><?php endif; ?>

      <div class="wa-tabs">
        <div class="wa-tab-btn <?= $activeTab==='chat'?'active':'' ?>" onclick="location.href='wa_messages.php?tab=chat'">Chat Messages</div>
        <div class="wa-tab-btn <?= $activeTab==='broadcast'?'active':'' ?>" onclick="location.href='wa_messages.php?tab=broadcast'">Broadcast Messages</div>
        <div class="wa-tab-btn <?= $activeTab==='settings'?'active':'' ?>" onclick="location.href='wa_messages.php?tab=settings'">Bot Settings</div>
      </div>

      <?php if($activeTab === 'broadcast'): ?>
      <div class="data-table-card" style="padding: 2rem; max-width: 800px;">
        <h3 style="margin-bottom: 1.5rem;">Marketing Broadcast</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 2rem;">Send a marketing message to all customers who have previously ordered. (Requires pre-approved Marketing Template in Meta for scale).</p>
        
        <form method="POST">
          <?= csrfField() ?>
          <div class="form-group mb-4">
            <label class="form-label">Message Content</label>
            <textarea name="broadcast_message" class="filter-input" style="height: 120px;" placeholder="🔥 Special Offer Today! Flat 20% OFF..."></textarea>
          </div>
          <button type="submit" name="send_broadcast" class="btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">
            <i class="bi bi-megaphone" style="margin-right: 0.5rem;"></i> Send to All Customers
          </button>
        </form>
      </div>

      <?php elseif($activeTab === 'chat'): ?>
      <div class="chat-container<?= $activePhone ? ' chat-open' : '' ?>" id="chatContainer">
        <div class="chat-sidebar">
          <div style="padding: 1.5rem; border-bottom: 1px solid var(--glass-border);">
            <h3 style="margin:0; font-weight:800; font-size:1.1rem;">Conversations</h3>
          </div>
          <div class="chat-list">
            <?php foreach($convs as $c): ?>
            <div class="chat-item <?= $activePhone===$c['phone']?'active':'' ?>" onclick="location.href='?phone=<?= $c['phone'] ?>'">
              <div style="font-weight:700; font-size:0.9rem;"><?= $c['phone'] ?></div>
              <div style="font-size:0.75rem; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:0.25rem;">
                <?= htmlspecialchars($c['last_text'] ?? '...') ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($convs)): ?><div style="text-align:center; padding:2rem; color:var(--text-muted);">No messages yet</div><?php endif; ?>
          </div>
        </div>

        <div class="chat-main">
          <?php if($activePhone): ?>
          <div class="chat-header">
            <div style="display:flex; align-items:center; gap:0.75rem;">
              <button type="button" class="chat-back-btn btn-icon" onclick="location.href='wa_messages.php'"><i class="bi bi-arrow-left"></i></button>
              <div style="font-weight:800;"><?= $activePhone ?></div>
            </div>
            <a href="orders.php?search=<?= $activePhone ?>" class="btn-primary btn-sm" style="font-size:0.7rem;">View Orders</a>
          </div>
          <div class="chat-messages" id="chatWindow">
            <?php foreach($messages as $m): ?>
            <div class="message-bubble <?= $m['direction'] ?>">
              <?= nl2br(htmlspecialchars($m['message'])) ?>
              <div class="message-time"><?= date('H:i', strtotime($m['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="chat-input-area">
            <form method="POST" class="chat-input-box">
              <?= csrfField() ?>
              <input type="hidden" name="phone" value="<?= $activePhone ?>">
              <textarea name="message" placeholder="Type a message..." required></textarea>
              <button type="submit" name="send_reply" class="btn-primary"><i class="bi bi-send"></i></button>
            </form>
          </div>
          <?php else: ?>
          <div style="display:flex; align-items:center; justify-content:center; flex:1; flex-direction:column; color:var(--text-muted);">
            <i class="bi bi-chat-dots" style="font-size:4rem; margin-bottom:1rem; opacity:0.3;"></i>
            <p>Select a conversation to start chatting</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php else: ?>
      <div class="data-table-card" style="padding: 2rem; max-width: 800px;">
        <h3 style="margin-bottom: 1.5rem;">WhatsApp Configuration</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 2rem;">Setup your WhatsApp Business Cloud API credentials here. These will override the values in your config files if set.</p>
        
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="update_wa_config" value="1">
          
          <div class="form-group mb-4">
             <label class="form-label">Bot Status</label>
             <select name="bot_enabled" class="filter-input">
                <option value="1" <?= ($dbConfig['bot_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>✅ Bot Enabled (Auto-Replies ON)</option>
                <option value="0" <?= ($dbConfig['bot_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>❌ Bot Disabled (Manual Only)</option>
             </select>
          </div>

          <div class="form-group mb-4">
            <label class="form-label">WhatsApp Access Token (Permanent)</label>
            <textarea name="wa_access_token" class="filter-input" style="height: 100px;" placeholder="EAAS..."><?= htmlspecialchars($dbConfig['wa_access_token'] ?? WA_ACCESS_TOKEN) ?></textarea>
            <small class="text-muted">Generate this from Meta for Developers portal.</small>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group mb-4">
              <label class="form-label">Phone Number ID</label>
              <input type="text" name="wa_phone_number_id" class="filter-input" value="<?= htmlspecialchars($dbConfig['wa_phone_number_id'] ?? WA_PHONE_NUMBER_ID) ?>">
            </div>
            <div class="form-group mb-4">
              <label class="form-label">API Version</label>
              <input type="text" name="wa_version" class="filter-input" value="<?= htmlspecialchars($dbConfig['wa_version'] ?? WA_VERSION) ?>">
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label">Webhook Verify Token</label>
            <input type="text" name="wa_webhook_token" class="filter-input" value="<?= htmlspecialchars($dbConfig['wa_webhook_token'] ?? WA_WEBHOOK_VERIFY_TOKEN) ?>">
            <small class="text-muted">Use this same token in Meta Webhook config.</small>
          </div>

          <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 1rem;">Save Configuration</button>
        </form>

        <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border);">
          <h4 style="margin-bottom: 1rem;">Test Connection</h4>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.5rem;">Enter a phone number (with country code, e.g., 919876543210) to send a test message.</p>
          <form method="POST" style="display: flex; gap: 1rem;">
            <?= csrfField() ?>
            <input type="text" name="test_phone" class="filter-input" placeholder="916238828993" style="flex: 1;">
            <button type="submit" name="test_connection" class="btn-primary" style="white-space: nowrap;">Send Test Message</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<script>
  const chatWindow = document.getElementById('chatWindow');
  if(chatWindow) chatWindow.scrollTop = chatWindow.scrollHeight;
</script>
</body>
</html>
