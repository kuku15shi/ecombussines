<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$currentUser = getCurrentUser($pdo);
$userId = $currentUser['id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $fullName = $_POST['full_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $house = $_POST['house'] ?? '';
        $street = $_POST['street'] ?? '';
        $landmark = $_POST['landmark'] ?? '';
        $city = $_POST['city'] ?? '';
        $district = $_POST['district'] ?? '';
        $state = $_POST['state'] ?? '';
        $pincode = $_POST['pincode'] ?? '';
        $country = $_POST['country'] ?? 'India';
        $type = $_POST['address_type'] ?? 'home';
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if (!$fullName || !$phone || !$house || !$street || !$city || !$state || !$pincode || !$district) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        // Validate Phone (10-15 digits)
        if (!preg_match('/^[0-9]{10,15}$/', preg_replace('/[^0-9]/', '', $phone))) {
             echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
             exit;
        }

        // Validate Pincode (6 digits for India)
        if (!preg_match('/^[0-9]{6}$/', trim($pincode))) {
             echo json_encode(['success' => false, 'message' => 'Invalid pincode. Must be 6 digits.']);
             exit;
        }

        if ($isDefault) {
             $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        } else {
             // If first address, make it default
             $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
             $stmtCount->execute([$userId]);
             if ($stmtCount->fetchColumn() == 0) {
                 $isDefault = 1;
             }
        }

        $stmt = $pdo->prepare("INSERT INTO user_addresses (user_id, full_name, phone, email, house, street, landmark, city, district, state, pincode, country, address_type, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $fullName, $phone, $email, $house, $street, $landmark, $city, $district, $state, $pincode, $country, $type, $isDefault]);
        
        $newId = $pdo->lastInsertId();
        
        // Fetch it back
        $stmtGet = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ?");
        $stmtGet->execute([$newId]);
        $address = $stmtGet->fetch();

        echo json_encode(['success' => true, 'message' => 'Address added successfully.', 'address' => $address]);
    } elseif ($action === 'delete') {
        $id = $_POST['address_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true, 'message' => 'Address deleted.']);
    } elseif ($action === 'set_default') {
        $id = $_POST['address_id'] ?? 0;
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $stmt = $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Default address updated.']);
    } elseif ($action === 'get_all') {
        $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$userId]);
        $addresses = $stmt->fetchAll();
        echo json_encode(['success' => true, 'addresses' => $addresses]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
