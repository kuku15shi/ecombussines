<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$video_id = (int)($_REQUEST['video_id'] ?? 0);

if (!$video_id || !in_array($action, ['like', 'unlike', 'comment', 'share', 'get_comments'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Ensure comments table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        user_name VARCHAR(255) DEFAULT 'Guest',
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    if (!isset($_SESSION['liked_videos'])) {
        $_SESSION['liked_videos'] = [];
    }

    if ($action === 'get_comments') {
        $stmt = $pdo->prepare("SELECT * FROM video_comments WHERE video_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$video_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'comments' => $comments]);
        exit;
    }

    if ($action === 'like') {
        if (!in_array($video_id, $_SESSION['liked_videos'])) {
            $pdo->query("UPDATE videos SET likes = likes + 1 WHERE id = $video_id");
            $_SESSION['liked_videos'][] = $video_id;
        }
    } elseif ($action === 'unlike') {
        if (in_array($video_id, $_SESSION['liked_videos'])) {
            $pdo->query("UPDATE videos SET likes = GREATEST(0, likes - 1) WHERE id = $video_id");
            $_SESSION['liked_videos'] = array_diff($_SESSION['liked_videos'], [$video_id]);
        }
    } elseif ($action === 'comment') {
        if (!isUserLoggedIn()) {
             echo json_encode(['success' => false, 'message' => 'Not logged in']);
             exit;
        }
        $comment_text = trim($_POST['comment_text'] ?? '');
        if ($comment_text) {
            $loggedInUser = $_SESSION['user_name'] ?? 'User';
            $stmt = $pdo->prepare("INSERT INTO video_comments (video_id, user_name, comment) VALUES (?, ?, ?)");
            $stmt->execute([$video_id, $loggedInUser, $comment_text]);
            $pdo->query("UPDATE videos SET comments = comments + 1 WHERE id = $video_id");
        }
    } elseif ($action === 'share') {
        $pdo->query("UPDATE videos SET shares = shares + 1 WHERE id = $video_id");
    }
    
    // Get updated counts
    $stmt = $pdo->query("SELECT likes, comments, shares FROM videos WHERE id = $video_id");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'counts' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
