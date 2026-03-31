<?php
/**
 * PaymentSecurity.php
 * Demonstrates server-side validation of payment data to prevent tampering via frontend.
 */

class PaymentSecurity {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Re-calculate cart total completely server-side.
     * NEVER trust prices sent over POST/AJAX from frontend.
     */
    public function calculateSecureOrderTotal($userId) {
        $stmt = $this->pdo->prepare("SELECT c.quantity, p.price, p.discount FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        foreach ($items as $item) {
            $finalPrice = $item['price'] - ($item['price'] * $item['discount'] / 100);
            $total += $finalPrice * $item['quantity'];
        }

        // Add server-defined shipping, gst, etc.
        if ($total < FREE_SHIPPING_ABOVE) {
            $total += SHIPPING_CHARGE;
        }

        return $total;
    }

    /**
     * Verify payment status using Gateway API (e.g., Razorpay/Stripe)
     * Do NOT trust frontend success callbacks directly!
     * @param string $orderId Server-generated order tracking ID
     * @param string $paymentId Gateway-provided payment transaction ID
     * @param string $signature Gateway-provided hash/signature
     */
    public function verifyPaymentSecurely($orderId, $paymentId, $signature, $userId) {
        // 1. Calculate actual server-side price expectation
        $expectedAmount = $this->calculateSecureOrderTotal($userId);

        // 2. Mock Razorpay logic (replace with actual api client)
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
        
        // Use hash_equals to prevent timing attacks
        if (hash_equals($expectedSignature, $signature)) {
            // Further verification via cURL or SDK to gateway API
            $ch = curl_init("https://api.razorpay.com/v1/payments/" . $paymentId);
            curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            // Validate the amount matches what we expect!
            // Gateway amounts often in smallest currency unit (paise/cents)
            if ($data['status'] === 'captured' && ($data['amount'] / 100) == $expectedAmount) {
                return true;
            }
        }
        
        return false;
    }
}
?>
