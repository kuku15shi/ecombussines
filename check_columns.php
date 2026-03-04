<?php
require_once 'config/db.php';
$res = $conn->query("SHOW COLUMNS FROM products");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
