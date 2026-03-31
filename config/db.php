<?php
require_once __DIR__ . '/config.php'; // Load secrets via config.php

define('DB_HOST', DB_HOST_VAL);
define('DB_USER', DB_USER_VAL);
define('DB_PASS', DB_PASSWORD_VAL);
define('DB_NAME', DB_NAME_VAL);

define('SITE_NAME', 'MIZ MAX');

// Dynamic SITE_URL Detection
$protocol = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
) ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Calculate the base path relative to the currently executing script
// This works whether we are in index.php or admin/login.php
$projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$execFile = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
$relPath = str_replace($projectRoot, '', $execFile);
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$basePath = substr($scriptName, 0, strlen($scriptName) - strlen($relPath));
$basePath = rtrim($basePath, '/');

define('SITE_URL', "$protocol://$host$basePath");

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('GST_PERCENT', 0);
define('FREE_SHIPPING_ABOVE', 999);
define('SHIPPING_CHARGE', 79);
define('COD_CHARGE', 40);
define('CURRENCY', '₹');

// Use secrets from config.php
define('RAZORPAY_KEY_ID', RAZORPAY_KEY_ID_VAL);
define('RAZORPAY_KEY_SECRET', RAZORPAY_KEY_SECRET_VAL);

// --- WhatsApp Business API ---
define('WA_ACCESS_TOKEN', WA_ACCESS_TOKEN_VAL);
define('ADMIN_PHONE', ADMIN_PHONE_VAL);
define('WA_PHONE_NUMBER_ID', '983614081506207');
define('WA_VERSION', 'v20.0');
define('WA_WEBHOOK_VERIFY_TOKEN', 'MIZ MAXWebHook2026');


// Load Security Middleware (Session, CSRF, PDO)
require_once __DIR__ . '/security.php';

// Legacy MySQLi connection (Keep for now to prevent breaking, but transition to $pdo)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("A technical error occurred. Please try again later.");
}
$conn->set_charset('utf8mb4');

// Enhanced Sanitize for XSS & SQLi (Legacy)
function sanitize($conn, $str)
{
    return $conn->real_escape_string(htmlspecialchars(strip_tags(trim($str))));
}

function generateSlug($name)
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    return $slug;
}

function generateOrderNumber()
{
    return 'LUXE' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

function formatPrice($price)
{
    return CURRENCY . number_format($price, 2);
}

function getDiscountedPrice($price, $discount)
{
    return $price - ($price * $discount / 100);
}


// --- Affiliate Program Constants ---
define('AFFILIATE_COMMISSION_PERCENT', 10.00);
define('AFFILIATE_COOKIE_DAYS', 30);
define('AFFILIATE_MIN_WITHDRAWAL', 500);

// --- Affiliate Referral Tracking (Production Grade) ---
if (isset($_GET['ref'])) {
    require_once __DIR__ . '/AffiliateClass.php';
    $affSystem = new AffiliateSystem($pdo);

    $refCode = sanitize($conn, $_GET['ref']);
    $stmt = $pdo->prepare("SELECT id FROM affiliates WHERE referral_code = ? AND status = 'approved'");
    $stmt->execute([$refCode]);
    $aff = $stmt->fetch();

    if ($aff) {
        $affId = $aff['id'];
        $currentAffId = $_SESSION['affiliate_id'] ?? 0;

        if ($affId != $currentAffId) {
            setcookie('luxe_affiliate_id', $affId, time() + (86400 * AFFILIATE_COOKIE_DAYS), "/");
            $affSystem->logClick($affId);
        }
    }
}
