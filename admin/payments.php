<?php
/**
 * admin/payments.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

$statusFilter = $_GET['status'] ?? '';
$methodFilter = $_GET['method'] ?? '';

$conditions = ['1=1'];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'paid', 'failed', 'refunded'], true)) {
    $conditions[] = 'pay.payment_status = :status';
    $params['status'] = $statusFilter;
}
if ($methodFilter !== '' && in_array($methodFilter, ['mpesa', 'airtel_money', 'tigo_pesa', 'halopesa', 'bank_transfer'], true)) {
    $conditions[] = 'pay.payment_method = :method';
    $params['method'] = $methodFilter;
}
$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    "SELECT pay.*, b.event_name, cu.fullname as customer_name, pu.fullname as provider_name
     FROM payments pay
     JOIN bookings b ON b.id = pay.booking_id
     JOIN users cu ON cu.id = b.customer_id
     JOIN providers p ON p.id = b.provider_id
     JOIN users pu ON pu.id = p.user_id
     WHERE {$where}
     ORDER BY pay.created_at DESC"
);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$totalPaid = $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = "paid"')->fetchColumn();

$current_page = 'payments';
$page_title = 'All Payments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold mb-0">All Payments</h3>
                    <div class="stat-card bg-teal py-2 px-3">
                        <p class="mb-0 small opacity-75">Total Revenue</p>
                        <p class="stat-value mb-0" style="font-size:1.3rem;"><?= format_money($totalPaid) ?></p>
                    </div>
                </div>

                <form method="GET" action="" class="row g-2 mb-4">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?>
                                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="method" class="form-select">
                            <option value="">All Methods</option>
                            <?php foreach (['mpesa' => 'M-Pesa', 'airtel_money' => 'Airtel Money', 'tigo_pesa' => 'Tigo Pesa', 'halopesa' => 'HaloPesa', 'bank_transfer' => 'Bank Transfer'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $methodFilter === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>

                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr><th>Event</th><th>Customer</th><th>Provider</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?= e($p['event_name']) ?></td>
                                        <td><?= e($p['customer_name']) ?></td>
                                        <td><?= e($p['provider_name']) ?></td>
                                        <td class="fw-semibold"><?= format_money($p['amount']) ?></td>
                                        <td><?= e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></td>
                                        <td><span class="small text-secondary"><?= e($p['transaction_reference']) ?></span></td>
                                        <td><span class="badge <?= payment_status_badge($p['payment_status']) ?>"><?= ucfirst(e($p['payment_status'])) ?></span></td>
                                        <td><?= format_date($p['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($payments)): ?>
                                    <tr><td colspan="8" class="text-center text-secondary py-4">No payments found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
