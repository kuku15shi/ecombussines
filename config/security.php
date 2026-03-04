<?php
/**
 * Security Middleware & Robust Configuration
 * This file handles session security, CSRF, Rate Limiting, and PDO Initialization.
 */

// 1. Debug Error Handling (Show errors for now)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

// 2. Secure Session Headers
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0); // Only 1 if HTTPS is on
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 3600);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Prevent Session Fixation
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// 4. PDO Database Connection (Centralized)
if (!isset($pdo)) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        die("A security error occurred. Please try again later.");
    }
}

// 5. CSRF Protection System
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . getCsrfToken() . '">';
}

function validateCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            error_log("CSRF validation failed for user_id: " . ($_SESSION['user_id'] ?? 'Guest'));
            die("Security Alert: Invalid form submission (CSRF).");
        }
    }
}

// 6. XSS Prevention Helper
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// 7. Login Rate Limiting (Prevent Brute Force)
function checkLoginAttempts($type = 'user') {
    $key = "login_attempts_" . $type;
    $lock_key = "login_lock_" . $type;
    
    if (isset($_SESSION[$lock_key]) && $_SESSION[$lock_key] > time()) {
        $wait = $_SESSION[$lock_key] - time();
        die("Too many failed attempts. Please wait " . ceil($wait / 60) . " minutes.");
    }
}

function registerFailedAttempt($type = 'user') {
    $key = "login_attempts_" . $type;
    $lock_key = "login_lock_" . $type;
    
    $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
    
    if ($_SESSION[$key] >= 5) {
        $_SESSION[$lock_key] = time() + 900; // 15 mins lock
        $_SESSION[$key] = 0;
    }
}

function clearLoginAttempts($type = 'user') {
    unset($_SESSION["login_attempts_" . $type]);
    unset($_SESSION["login_lock_" . $type]);
}

// 8. Payment Verification Security (Server-side check template)
function verifyPaymentStatus($orderId, $paymentId) {
    // In production, call Razorpay/Stripe API here to verify the payment_id against order amount
    // Do NOT rely on the status relayed by the frontend JavaScript
    return true; // Placeholder for actual API verification
}
