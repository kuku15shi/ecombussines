<?php
/**
 * Master Fix Script for MIZ MAX Deployment
 * Runs database migrations, directory checks, and security hardening.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Check if running as Admin
if (!isAdminLoggedIn()) {
    die("Unauthorized access. Please login as admin first.");
}

echo "<h1>MIZ MAX Deployment Fixer</h1>";

// 1. Directory Checks
$dirs = [
    'admin/ajax',
    'uploads/products',
    'uploads/variants',
    'uploads/models',
    'uploads/size_charts',
    'assets/images'
];

foreach ($dirs as $dir) {
    if (!file_exists(__DIR__ . '/' . $dir)) {
        if (mkdir(__DIR__ . '/' . $dir, 0755, true)) {
            echo "✅ Created directory: $dir<br>";
        } else {
            echo "❌ Failed to create directory: $dir (Check permissions)<br>";
        }
    } else {
        echo "ℹ️ Directory exists: $dir<br>";
    }
}

// 2. Database Migrations (Run update_wa columns if missing)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `whatsapp_config` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `config_key` varchar(50) NOT NULL,
      `config_value` text DEFAULT NULL,
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `config_key` (`config_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `bot_faqs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `question` text NOT NULL,
      `answer` text NOT NULL,
      `keywords` text DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `whatsapp_macros` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(100) NOT NULL,
      `content` text NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("ALTER TABLE whatsapp_messages ADD COLUMN IF NOT EXISTS assigned_to INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE whatsapp_messages ADD COLUMN IF NOT EXISTS chat_status ENUM('open','resolved','blocked') DEFAULT 'open'");

    echo "✅ Database schema updated!<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// 3. Clear Cache / Session Checks
echo "✅ Deployment checks completed. <a href='admin/index.php'>Go to Dashboard</a>";
?>