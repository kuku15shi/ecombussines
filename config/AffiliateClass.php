<?php
// Production-Ready Affiliate Controller Class (PHP 8+, PDO)

class AffiliateSystem {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // --- Authentication ---
    public function register($data) {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $refCode = $this->generateRefCode($data['name']);
        
        $sql = "INSERT INTO affiliates (name, email, password, phone, referral_code, status) 
                VALUES (:name, :email, :password, :phone, :ref_code, 'pending')";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $hashedPassword,
            'phone' => $data['phone'] ?? null,
            'ref_code' => $refCode
        ]);
    }

    public function login($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM affiliates WHERE email = ?");
        $stmt->execute([$email]);
        $affiliate = $stmt->fetch();

        if ($affiliate && password_verify($password, $affiliate['password'])) {
            if ($affiliate['status'] === 'approved') {
                return $affiliate;
            }
            return 'pending_approval';
        }
        return false;
    }

    private function generateRefCode($name) {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $code = substr($base, 0, 5) . rand(100, 999);
        
        // Uniqueness check
        $stmt = $this->pdo->prepare("SELECT id FROM affiliates WHERE referral_code = ?");
        $stmt->execute([$code]);
        if ($stmt->fetch()) {
            return $this->generateRefCode($name);
        }
        return $code;
    }

    // --- Tracking ---
    public function logClick($affiliateId) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        
        $sql = "INSERT INTO affiliate_clicks (affiliate_id, ip_address, user_agent, referrer_url) 
                VALUES (?, ?, ?, ?)";
        $this->pdo->prepare($sql)->execute([$affiliateId, $ip, $ua, $ref]);
    }

    // --- Commissions ---
    public function recordCommission($orderId, $total) {
        if (!isset($_COOKIE['luxe_affiliate_id'])) return false;
        
        $affId = (int)$_COOKIE['luxe_affiliate_id'];
        
        // Get affiliate's default rate first
        $stmt = $this->pdo->prepare("SELECT commission_rate FROM affiliates WHERE id = ? AND status = 'approved'");
        $stmt->execute([$affId]);
        $aff = $stmt->fetch();
        if (!$aff) return false;

        $defaultRate = (float)$aff['commission_rate'];
        if ($defaultRate <= 0) $defaultRate = (float)AFFILIATE_COMMISSION_PERCENT;

        // Fetch order items joined with product commission info
        $stmt = $this->pdo->prepare("
            SELECT oi.total, p.affiliate_commission 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();

        $totalCommission = 0;
        foreach ($items as $item) {
            // Use product's custom commission if set, else fall back to default
            $rate = ($item['affiliate_commission'] !== null) ? (float)$item['affiliate_commission'] : $defaultRate;
            $totalCommission += ($item['total'] * $rate / 100);
        }

        // Check for duplicates
        if ($this->commissionExists($orderId)) return false;

        $sql = "INSERT INTO affiliate_commissions (affiliate_id, order_id, order_amount, commission_amount, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        return $this->pdo->prepare($sql)->execute([$affId, $orderId, $total, $totalCommission]);
    }

    private function commissionExists($orderId) {
        $stmt = $this->pdo->prepare("SELECT id FROM affiliate_commissions WHERE order_id = ?");
        $stmt->execute([$orderId]);
        return (bool)$stmt->fetch();
    }

    // --- Dashboard Stats ---
    public function getStats($affiliateId) {
        $stats = [];
        
        // Clicks
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE affiliate_id = ?");
        $stmt->execute([$affiliateId]);
        $stats['clicks'] = $stmt->fetchColumn();

        // Total Earned (Verified/Paid)
        $stmt = $this->pdo->prepare("SELECT SUM(commission_amount) FROM affiliate_commissions WHERE affiliate_id = ? AND status IN ('verified', 'paid')");
        $stmt->execute([$affiliateId]);
        $stats['total_earned'] = $stmt->fetchColumn() ?: 0;

        // Current Balance
        $stmt = $this->pdo->prepare("SELECT balance FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $stats['balance'] = $stmt->fetchColumn() ?: 0;

        // Pending
        $stmt = $this->pdo->prepare("SELECT SUM(commission_amount) FROM affiliate_commissions WHERE affiliate_id = ? AND status = 'pending'");
        $stmt->execute([$affiliateId]);
        $stats['pending_earnings'] = $stmt->fetchColumn() ?: 0;

        return $stats;
    }

    public function getWeeklyClicks($affiliateId) {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM affiliate_clicks 
                WHERE affiliate_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$affiliateId]);
        return $stmt->fetchAll();
    }
}
