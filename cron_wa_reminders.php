<?php
// cron_wa_reminders.php - Run this via cron every hour or 30 mins
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/whatsapp_functions.php';

echo "Checking for abandoned carts...\n";

// Find sessions older than 2 hours that aren't finished/start
$stmt = $pdo->query("SELECT * FROM bot_sessions 
                    WHERE step NOT IN ('start', 'order_confirm') 
                    AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR) 
                    AND updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");

while ($session = $stmt->fetch()) {
    $data = json_decode($session['data'], true);
    $phone = $session['phone'];
    
    // Check if we already reminded
    if (isset($data['reminded'])) continue;

    $pName = $data['pName'] ?? 'items in your cart';
    $msg = "👋 Hello! You forgot something in your cart.\n\n" .
           "🛍️ " . $pName . "\n\n" .
           "Complete your order here:\n" . SITE_URL . "/cart\n\n" .
           "Or reply MENU to continue browsing.";

    echo "Sending reminder to $phone...\n";
    sendWhatsAppMessage($phone, $msg, 'text');

    // Mark as reminded
    $data['reminded'] = true;
    $pdo->prepare("UPDATE bot_sessions SET data = ? WHERE id = ?")->execute([json_encode($data), $session['id']]);
}

echo "Done.\n";
