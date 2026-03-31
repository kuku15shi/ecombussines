<?php
require_once 'config/db.php';

echo "<h2>WhatsApp System Check</h2>";

// 1. Check Tables
$tables = ['whatsapp_logs', 'whatsapp_messages', 'whatsapp_config'];
foreach ($tables as $table) {
    try {
        $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "✅ Table '$table' exists.<br>";
    } catch (Exception $e) {
        echo "❌ Table '$table' MISSING. Creating it...<br>";
        if ($table === 'whatsapp_logs') {
            $pdo->exec("CREATE TABLE `whatsapp_logs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `type` varchar(50) DEFAULT NULL,
                `api_response` text DEFAULT NULL,
                `status` varchar(20) DEFAULT NULL,
                `error_message` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            echo "✅ Created '$table'.<br>";
        }
    }
}

// 2. Check Directories
$dirs = [
    'uploads/voice/',
    'uploads/whatsapp_media/voice/',
    'uploads/whatsapp_media/images/',
    'uploads/whatsapp_media/videos/',
    'uploads/whatsapp_media/documents/'
];
foreach ($dirs as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, 0777, true)) {
            echo "✅ Created directory: $dir<br>";
        } else {
            echo "❌ Failed to create directory: $dir (Check permissions or create manually via FTP)<br>";
        }
    } else {
        echo "✅ Directory exists: $dir<br>";
        if (!is_writable($fullPath)) {
            echo "❌ Directory NOT WRITABLE: $dir (Run chmod 777 via FTP)<br>";
        }
    }
}

// 3. Debug Last Log
echo "<h3>Recent API Activity (whatsapp_debug.log)</h3>";
$logFile = 'whatsapp_debug.log';
if (file_exists($logFile)) {
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc; max-height:300px; overflow:auto;'>";
    echo htmlspecialchars(shell_exec('tail -n 20 ' . escapeshellarg($logFile)) ?: file_get_contents($logFile));
    echo "</pre>";
} else {
    echo "No debug log found yet. Try sending a message first.";
}

// 4. WhatsApp Config Test
echo "<h3>WhatsApp Config Check</h3>";
$wa = getWhatsAppConfig();
echo "<b>Version:</b> " . ($wa['version'] ?? 'MISSING') . "<br>";
echo "<b>Phone ID:</b> " . ($wa['phone_id'] ?? 'MISSING') . "<br>";
echo "<b>Token length:</b> " . strlen($wa['token'] ?? '') . " chars<br>";

if (empty($wa['token']) || empty($wa['phone_id'])) {
    echo "<p style='color:red;'><b>CRITICAL:</b> WhatsApp configuration is incomplete. Go to Admin -> WhatsApp Manager -> API Settings and enter your Token and Phone ID.</p>";
}

echo "<hr><p>Run this script on your server and tell me the results!</p>";
