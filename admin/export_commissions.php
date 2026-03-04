<?php
require_once __DIR__ . '/../config/pdo_config.php';
require_once __DIR__ . '/../config/auth.php';

requireAdminLogin();

$stmt = $pdo->query("SELECT c.*, a.name as aff_name, o.order_number 
                    FROM affiliate_commissions c 
                    JOIN affiliates a ON c.affiliate_id = a.id 
                    JOIN orders o ON c.order_id = o.id 
                    ORDER BY c.created_at DESC");
$data = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="affiliate_payouts_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Affiliate', 'Order #', 'Order Amount', 'Commission', 'Status', 'Date']);

foreach ($data as $row) {
    fputcsv($output, [
        $row['id'],
        $row['aff_name'],
        '#' . $row['order_number'],
        $row['order_amount'],
        $row['commission_amount'],
        ucfirst($row['status']),
        $row['created_at']
    ]);
}
fclose($output);
exit;
