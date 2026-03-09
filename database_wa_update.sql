-- LuxeStore Database Schema Update (v3)
-- Updated on: 2026-03-09

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- WA CONFIGURATION & BOT SETTINGS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `whatsapp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(50) NOT NULL,
  `config_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Settings
INSERT IGNORE INTO `whatsapp_config` (`config_key`, `config_value`) VALUES
('bot_enabled', '1'),
('bot_welcome_msg', '👋 Hello! Welcome to *LuxeStore*\nHow can I help you today?'),
('bot_fallback_msg', 'Sorry, I didn\'t understand that. Reply MENU to see options.'),
('wa_version', 'v20.0');

-- --------------------------------------------------------
-- CHATBOT FAQS & AUTO-REPLIES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `bot_faqs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `keywords` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- CHAT MACROS (QUICK REPLIES)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `whatsapp_macros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- DELIVERY NOTIFICATION TEMPLATES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `variables` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Templates
INSERT IGNORE INTO `whatsapp_templates` (`name`, `content`) VALUES
('order_delay', 'Hello {customer_name}, your order {order_id} is slightly delayed. We expect to deliver it by {delivery_date}. Track here: {tracking_link}'),
('delivery_avail', 'Hello {customer_name}, our agent is nearby to deliver your order {order_id}. Please confirm if you are available at {address}.'),
('order_shipped', 'Hi {customer_name}, your order {order_id} has been shipped via {courier}. Tracking Link: {tracking_link}');

-- --------------------------------------------------------
-- WHATSAPP MESSAGES (ENHANCED)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `direction` enum('incoming','outgoing') NOT NULL,
  `message_id` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'sent',
  `assigned_to` int(11) DEFAULT NULL,
  `chat_status` enum('open','resolved','blocked') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- BOT SESSIONS (FOR STATEFUL CHAT)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `bot_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `step` varchar(50) DEFAULT 'start',
  `data` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
