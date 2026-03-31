<?php
/**
 * CoreSecurity.php
 * A comprehensive, production-ready security class for handling authentication, 
 * session hijacking prevention, file uploads, rate limiting, and generic protections.
 */

class CoreSecurity {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Rate Limiting & Blocking Suspicious IPs
     * Blocks repeated attacks by checking login_attempts and blocked_ips tables.
     */
    public function enforceRateLimit($ip) {
        // 1. Check if IP is permanently blocked
        $stmt = $this->pdo->prepare("SELECT id FROM blocked_ips WHERE ip_address = ? LIMIT 1");
        $stmt->execute([$ip]);
        if ($stmt->fetch()) {
            http_response_code(403);
            die("Access Denied: Your IP is blocked.");
        }

        // 2. Check temporary blocks in login_attempts
        $stmt = $this->pdo->prepare("SELECT attempts, blocked_until FROM login_attempts WHERE ip_address = ? AND is_blocked = 1");
        $stmt->execute([$ip]);
        $attempt = $stmt->fetch();

        if ($attempt) {
            if (strtotime($attempt['blocked_until']) > time()) {
                $wait = ceil((strtotime($attempt['blocked_until']) - time()) / 60);
                http_response_code(429);
                die("Too many failed attempts. Please wait $wait minutes.");
            } else {
                // Unblock if time expired
                $this->pdo->prepare("UPDATE login_attempts SET is_blocked = 0, attempts = 0 WHERE ip_address = ?")->execute([$ip]);
            }
        }
    }

    /**
     * Log failed attempt, CAPTCHA after 3, Block after 5
     * Returns true if CAPTCHA is required
     */
    public function logFailedAttempt($ip, $identifier) {
        $stmt = $this->pdo->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();

        if ($row) {
            $newAttempts = $row['attempts'] + 1;
            if ($newAttempts >= 5) {
                // Block for 15 minutes
                $blockTime = date('Y-m-d H:i:s', time() + 900);
                $this->pdo->prepare("UPDATE login_attempts SET attempts = ?, is_blocked = 1, blocked_until = ? WHERE ip_address = ?")->execute([$newAttempts, $blockTime, $ip]);
            } else {
                $this->pdo->prepare("UPDATE login_attempts SET attempts = ? WHERE ip_address = ?")->execute([$newAttempts, $ip]);
            }
            return $newAttempts >= 3; // return true to show CAPTCHA
        } else {
            $this->pdo->prepare("INSERT INTO login_attempts (ip_address, email_or_phone) VALUES (?, ?)")->execute([$ip, $identifier]);
            return false;
        }
    }

    public function clearLoginAttempts($ip) {
        $this->pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
    }

    /**
     * Session Security: Prevent Hijacking and Auto Logout
     */
    public function secureSession($timeoutMinutes = 30) {
        // Prevent Session Hijacking
        $currentIP = $_SERVER['REMOTE_ADDR'];
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        if (!isset($_SESSION['client_ip'])) {
            $_SESSION['client_ip'] = $currentIP;
            $_SESSION['client_ua'] = $currentUserAgent;
        } else {
            if ($_SESSION['client_ip'] !== $currentIP || $_SESSION['client_ua'] !== $currentUserAgent) {
                // Possible Hijack Detected
                session_unset();
                session_destroy();
                http_response_code(403);
                die("Security Alert: Session Invalidated.");
            }
        }

        // Auto Logout after inactivity
        $timeoutSeconds = $timeoutMinutes * 60;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
            session_unset();
            session_destroy();
            header("Location: login.php?msg=Session expired. Please login again.");
            exit;
        }
        $_SESSION['last_activity'] = time();

        // Secure Regeneration (handled in security.php already, but good to encapsulate if needed)
    }

    /**
     * Password Rules Validation (Min 8 chars, 1 uppercase, 1 number, 1 symbol)
     */
    public function validateStrongPassword($password) {
        if (strlen($password) < 8) return "Password must be at least 8 characters.";
        if (!preg_match('/[A-Z]/', $password)) return "Password must contain at least one uppercase letter.";
        if (!preg_match('/[0-9]/', $password)) return "Password must contain at least one number.";
        if (!preg_match('/[\W]/', $password)) return "Password must contain at least one special character.";
        return true;
    }

    /**
     * OTP Generation
     */
    public function generateOTP($identifier, $userId = null) {
        $otp = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiry

        // Invalidate old OTPs for this identifier
        $this->pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE identifier = ?")->execute([$identifier]);

        $stmt = $this->pdo->prepare("INSERT INTO otp_verifications (user_id, identifier, otp_code, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $identifier, $otp, $expiry]);

        return $otp; // In real app, send via Email/SMS API here
    }

    /**
     * OTP Verification
     */
    public function verifyOTP($identifier, $otpCode) {
        $stmt = $this->pdo->prepare("SELECT * FROM otp_verifications WHERE identifier = ? AND otp_code = ? AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$identifier, $otpCode]);
        $row = $stmt->fetch();

        if ($row && strtotime($row['expires_at']) > time()) {
            $this->pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE id = ?")->execute([$row['id']]);
            return true;
        }
        return false;
    }

    /**
     * File Upload Security: Image Only, Rename, Validate MIME type
     */
    public function secureImageUpload($file, $uploadDir) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ["status" => false, "msg" => "Upload failed."];
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes)) {
            return ["status" => false, "msg" => "Invalid file type. Only JPG, PNG, WEBP allowed."];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array(strtolower($extension), $allowedExts)) {
            return ["status" => false, "msg" => "Invalid extension."];
        }

        // Rename file securely
        $newName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destPath = rtrim($uploadDir, '/') . '/' . $newName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            return ["status" => true, "filename" => $newName];
        }
        return ["status" => false, "msg" => "Failed to move uploaded file."];
    }

    /**
     * Admin Log Activity
     */
    public function logAdminActivity($userId, $action, $ip) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmt = $this->pdo->prepare("INSERT INTO security_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $ip, $ua]);
    }
}
?>
