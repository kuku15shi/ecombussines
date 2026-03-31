<?php
$ftp_server = "ftpupload.net";
$ftp_user = "if0_41303152";
$ftp_pass = "Gs11B4YLnt3YTi";

$conn_id = ftp_connect($ftp_server) or die("Could not connect");
ftp_login($conn_id, $ftp_user, $ftp_pass) or die("Could not login");
ftp_pasv($conn_id, true);

echo "Current Dir: " . ftp_pwd($conn_id) . "\n";
echo "Listing:\n";
print_r(ftp_nlist($conn_id, "."));

if (ftp_chdir($conn_id, "htdocs")) {
    echo "Successfully changed to htdocs\n";
    echo "Current Dir: " . ftp_pwd($conn_id) . "\n";
} else {
    echo "Failed to change to htdocs\n";
}

ftp_close($conn_id);
