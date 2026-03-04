<?php
require_once 'config/db.php';
$res = $conn->query("SELECT id, name, video_url FROM products WHERE video_url IS NOT NULL AND video_url != ''");
if($res->num_rows == 0) {
    echo "No products found with a video URL.";
} else {
    while($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Video URL: " . $row['video_url'] . "\n";
    }
}
