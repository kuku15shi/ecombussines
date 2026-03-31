<?php
/**
 * MIZ MAX FTP Deployment Script (Robust Version)
 * Run this from your terminal: php ftp_deploy.php
 */

set_time_limit(0); // Prevents script timeout

$ftp_server = "ftpupload.net";
$ftp_user = "if0_41303152";
$ftp_pass = "Gs11B4YLnt3YTi";
$remote_root = "/htdocs"; // Absolute path on InfinityFree

$conn_id = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");

echo "Attempting to login...\n";
if (@ftp_login($conn_id, $ftp_user, $ftp_pass)) {
    echo "Connected as $ftp_user@$ftp_server\n";
} else {
    die("Could not connect as $ftp_user\n");
}

// Enable passive mode
ftp_pasv($conn_id, true);

// Files/Directories to ignore
$ignore = [
    '.git',
    '.gemini',
    '.agent',
    '.vscode',
    '.idea',
    'admin.zip',
    'fi.zip',
    'product.zip',
    'wha (2).zip',
    'node_modules',
    'vendor',
    'ftp_deploy.php',
    'ignore'
];

/**
 * Ensures a directory exists and we are inside it
 */
function ftp_ensure_dir_absolute($conn_id, $path)
{
    global $remote_root;
    $parts = explode('/', trim($path, '/'));
    ftp_chdir($conn_id, "/"); // Start from root

    foreach ($parts as $part) {
        if (empty($part))
            continue;
        if (!@ftp_chdir($conn_id, $part)) {
            echo "Creating directory: " . ftp_pwd($conn_id) . "/$part\n";
            if (@ftp_mkdir($conn_id, $part)) {
                ftp_chdir($conn_id, $part);
            } else {
                echo "Failed to create/enter $part\n";
                return false;
            }
        }
    }
    return true;
}

function upload_recursive($conn_id, $local_path, $base_local)
{
    global $ignore, $remote_root;

    $relative_path = str_replace($base_local, '', $local_path);
    $relative_path = ltrim($relative_path, DIRECTORY_SEPARATOR);
    $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);

    if (in_array(basename($local_path), $ignore))
        return;

    if (is_dir($local_path)) {
        $files = scandir($local_path);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                upload_recursive($conn_id, $local_path . DIRECTORY_SEPARATOR . $file, $base_local);
            }
        }
    } else {
        $remote_file = $remote_root . '/' . $relative_path;
        $remote_dir = dirname($remote_file);

        echo "Uploading: $relative_path ... ";

        // Ensure parent directory exists
        if (ftp_ensure_dir_absolute($conn_id, $remote_dir)) {
            if (@ftp_put($conn_id, basename($remote_file), $local_path, FTP_BINARY)) {
                echo "OK\n";
            } else {
                echo "FAILED\n";
            }
        } else {
            echo "FAILED (Dir Error)\n";
        }
    }
}

$local_root = __DIR__;
upload_recursive($conn_id, $local_root, $local_root);

ftp_close($conn_id);
echo "\nDeployment Finished!\n";

