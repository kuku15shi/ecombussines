<?php
// Secure PDO Database Connection for Affiliate Program
require_once __DIR__ . '/db.php'; // Keep for SITE_URL and other constants

// Database config (using same constants where possible)
$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// --- Session Security ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID to prevent fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// --- CSRF Protection ---
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
    return true;
}

// --- Affiliate Constants ---
if(!defined('AFFILIATE_COMMISSION_PERCENT')) define('AFFILIATE_COMMISSION_PERCENT', 10.00);
if(!defined('AFFILIATE_COOKIE_DAYS')) define('AFFILIATE_COOKIE_DAYS', 30);
if(!defined('AFFILIATE_MIN_WITHDRAWAL')) define('AFFILIATE_MIN_WITHDRAWAL', 500);
