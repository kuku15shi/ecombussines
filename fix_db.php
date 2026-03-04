<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>Database Synchronization</h2>";

// Check if google_id column exists in users
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
if (!mysqli_num_rows($result)) {
    echo "Updating users table...<br>";
    $conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(100) UNIQUE DEFAULT NULL AFTER avatar");
    echo "<span style='color:green;'>✅ Success: 'google_id' added to users!</span><br>";
}

// Check if google_id column exists in admin
$result = $conn->query("SHOW COLUMNS FROM admin LIKE 'google_id'");
if (!mysqli_num_rows($result)) {
    echo "Updating admin table...<br>";
    $conn->query("ALTER TABLE admin ADD COLUMN google_id VARCHAR(100) UNIQUE DEFAULT NULL AFTER avatar");
    echo "<span style='color:green;'>✅ Success: 'google_id' added to admin!</span><br>";
}

if (isset($_POST['add_admin'])) {
    $email = sanitize($conn, $_POST['email']);
    $name = sanitize($conn, $_POST['name']);
    $res = $conn->query("SELECT * FROM admin WHERE email='$email'");
    if (mysqli_num_rows($res) == 0) {
        $conn->query("INSERT INTO admin (name, email, password) VALUES ('$name', '$email', 'GOOGLE_OAUTH_STAFF')");
        echo "<span style='color:green;'>🚀 Admin '$email' added successfully!</span><br>";
    } else {
        echo "<span style='color:orange;'>⚠️ Admin '$email' already exists.</span><br>";
    }
}

echo "<h3>Add Google Email as Admin</h3>
<form method='POST'>
    <input type='text' name='name' placeholder='Admin Name' required>
    <input type='email' name='email' placeholder='Google Email Address' required>
    <button type='submit' name='add_admin'>Add Admin</button>
</form>";

echo "<br><br><a href='index.php'>Go to Homepage</a>";
?>
