<?php
require_once __DIR__ . '/config/db.php';

echo "<h2>Applying Database Updates for Google Login...</h2>";

$queries = [
    "ALTER TABLE affiliates ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL AFTER phone",
    "ALTER TABLE affiliates ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL AFTER google_id"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>SUCCESS: $sql</p>";
    } else {
        echo "<p style='color:red;'>FAILED: " . $conn->error . " | Query: $sql</p>";
    }
}

echo "<p><b>Database update complete. You can now try logging in with Google again.</b></p>";
echo "<a href='affiliate/login.php'>Go back to Login</a>";
?>
