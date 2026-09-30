<?php
/**
 * customer/receipt.php
 * Print-friendly receipt for a paid booking. Uses the browser's native
 * "Print to PDF" (window.print()) rather than a server-side PDF library,
 * keeping the stack dependency-free as required (plain PHP only).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$bookingId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT b.*, u.fullname as provider_name, cu.fullname as customer_name, cu.email as customer_email,
            pay.amount, pay.payment_method, pay.payment_status, pay.transaction_reference, pay.created_at as paid_at
     FROM bookings b
     JOIN providers p ON p.id = b.provider_id
     JOIN users u ON u.id = p.user_id
     JOIN users cu ON cu.id = b.customer_id
     JOIN payments pay ON pay.booking_id = b.id
     WHERE b.id = :id AND b.customer_id = :cid
     LIMIT 1'
);
$stmt->execute(['id' => $bookingId, 'cid' => current_user_id()]);
$data = $stmt->fetch();

if (!$data || $data['payment_status'] !== 'paid') {
    die('Receipt not available for this booking.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= e($data['event_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f4f4; }
        .receipt-box { max-width: 650px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 8px; }
        @media print {
            body { background: #fff; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="receipt-box shadow-sm">
        <div class="text-center mb-4">
            <h3 class="fw-bold">Payment Receipt</h3>
            <p class="text-secondary"><?= e(APP_NAME) ?></p>
        </div>
        <hr>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Receipt Ref:</div>
            <div class="col-6 text-end fw-semibold">#<?= str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Date Paid:</div>
            <div class="col-6 text-end fw-semibold"><?= format_date($data['paid_at']) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Customer:</div>
            <div class="col-6 text-end fw-semibold"><?= e($data['customer_name']) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Provider:</div>
            <div class="col-6 text-end fw-semibold"><?= e($data['provider_name']) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Event:</div>
            <div class="col-6 text-end fw-semibold"><?= e($data['event_name']) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Event Date:</div>
            <div class="col-6 text-end fw-semibold"><?= format_date($data['event_date']) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Payment Method:</div>
            <div class="col-6 text-end fw-semibold"><?= e(ucwords(str_replace('_', ' ', $data['payment_method']))) ?></div>
        </div>
        <div class="row mb-2">
            <div class="col-6 text-secondary">Transaction Ref:</div>
            <div class="col-6 text-end fw-semibold"><?= e($data['transaction_reference']) ?></div>
        </div>
        <hr>
        <div class="row mb-2">
            <div class="col-6 fw-bold fs-5">Amount Paid:</div>
            <div class="col-6 text-end fw-bold fs-5 text-primary"><?= format_money($data['amount']) ?></div>
        </div>
        <hr>
        <p class="text-center small text-secondary mt-4">Thank you for using <?= e(APP_NAME) ?>!</p>

        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary"><i class="bi bi-printer"></i> Print / Save as PDF</button>
        </div>
    </div>
</body>
</html>
