<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../includes/whatsapp_functions.php';

requireAdminLogin();
$pageTitle = 'WhatsApp Chatbot & Messages';

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
                header('Location: admin_chatbot.php?success=1&phone=' . urlencode($phone)); exit;
            } catch (Exception $e) { $error = "DB Error: " . $e->getMessage(); }
        } else {
            $error = "WhatsApp Error: " . ($res['error'] ?? 'Unknown Error');
        }
    }
}

$activePhone = $_GET['phone'] ?? '';
$messages = [];
if ($activePhone) {
    $stmt = $pdo->prepare("SELECT * FROM whatsapp_messages WHERE phone = ? ORDER BY created_at ASC");
    $stmt->execute([$activePhone]);
    $messages = $stmt->fetchAll();
}

// Get unique conversations
$convs = $pdo->query("SELECT phone, MAX(created_at) as last_msg, (SELECT message FROM whatsapp_messages WHERE phone = t.phone ORDER BY created_at DESC LIMIT 1) as last_text FROM (SELECT DISTINCT phone, created_at FROM whatsapp_messages) as t GROUP BY phone ORDER BY last_msg DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WhatsApp Chatbot – Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="css/admin.css" rel="stylesheet">
  <style>
    .chat-container { display: grid; grid-template-columns: 320px 1fr; background: var(--glass); border: 1px solid var(--glass-border); border-radius: var(--radius); height: calc(100vh - 180px); overflow: hidden; }
    .chat-sidebar { border-right: 1px solid var(--glass-border); display: flex; flex-direction: column; }
    .chat-list { flex: 1; overflow-y: auto; padding: 0.75rem; }
    .chat-item { padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 0.5rem; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
    .chat-item:hover { background: rgba(255,255,255,0.05); }
    .chat-item.active { background: rgba(255,255,255,0.1); border-color: var(--primary); }
    
    .chat-main { display: flex; flex-direction: column; background: rgba(0,0,0,0.1); }
    .chat-header { padding: 1.25rem; border-bottom: 1px solid var(--glass-border); background: var(--glass); display: flex; align-items: center; justify-content: space-between; }
    .chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .message-bubble { max-width: 70%; padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; position: relative; }
    .message-bubble.incoming { background: var(--glass); align-self: flex-start; border-bottom-left-radius: 2px; }
    .message-bubble.outgoing { background: var(--primary); color: #fff; align-self: flex-end; border-bottom-right-radius: 2px; }
    .message-time { font-size: 0.65rem; opacity: 0.6; margin-top: 0.4rem; text-align: right; }
    
    .chat-input-area { padding: 1.25rem; background: var(--glass); border-top: 1px solid var(--glass-border); }
    .chat-input-box { display: flex; gap: 0.75rem; }
    .chat-input-box textarea { flex: 1; border-radius: var(--radius-sm); border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: var(--text); padding: 0.75rem; min-height: 48px; max-height: 120px; outline: none; transition: 0.2s; resize: none; }
    .chat-input-box textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,99,255,0.15); }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="content-area">
      <?php if(isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
      <?php if(isset($_GET['success'])): ?><div class="alert alert-success">Message sent!</div><?php endif; ?>

      <div class="chat-container">
        <!-- Sidebar: Latest Conversations -->
        <div class="chat-sidebar">
          <div style="padding: 1.5rem; border-bottom: 1px solid var(--glass-border);">
            <h3 style="margin:0; font-weight:800; font-size:1.1rem;">Conversations</h3>
          </div>
          <div class="chat-list">
            <?php foreach($convs as $c): ?>
            <div class="chat-item <?= $activePhone===$c['phone']?'active':'' ?>" onclick="location.href='?phone=<?= $c['phone'] ?>'">
              <div style="font-weight:700; font-size:0.9rem;"><?= $c['phone'] ?></div>
              <div style="font-size:0.75rem; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-top:0.25rem;">
                <?= htmlspecialchars($c['last_text']) ?>
              </div>
              <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.4rem; text-align:right;">
                <?= date('d M, H:i', strtotime($c['last_msg'])) ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($convs)): ?><div style="text-align:center; padding:2rem; color:var(--text-muted);">No messages yet</div><?php endif; ?>
          </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
          <?php if($activePhone): ?>
          <div class="chat-header">
            <div style="font-weight:800;"><?= $activePhone ?></div>
            <a href="admin_orders.php?search=<?= $activePhone ?>" class="btn-primary btn-sm" style="font-size:0.7rem;">View Orders</a>
          </div>
          <div class="chat-messages" id="chatWindow">
            <?php foreach($messages as $m): ?>
            <div class="message-bubble <?= $m['direction'] ?>">
              <?= nl2br(htmlspecialchars($m['message'])) ?>
              <div class="message-time">
                <?= date('H:i', strtotime($m['created_at'])) ?>
                <?php if($m['direction'] === 'outgoing'): ?>
                <i class="bi bi-check2<?= $m['status']==='delivered'?'':'-all' ?>"></i>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="chat-input-area">
            <form method="POST" class="chat-input-box">
              <?= csrfField() ?>
              <input type="hidden" name="phone" value="<?= $activePhone ?>">
              <textarea name="message" placeholder="Type a message..." required></textarea>
              <button type="submit" name="send_reply" class="btn-primary" style="padding: 0 1.5rem;">
                <i class="bi bi-send-fill" style="margin:0;"></i>
              </button>
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
    </div>
  </div>
</div>
<script>
  // Scroll to bottom
  const chatWindow = document.getElementById('chatWindow');
  if(chatWindow) chatWindow.scrollTop = chatWindow.scrollHeight;
</script>
</body>
</html>
