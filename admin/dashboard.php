<?php
/**
 * admin/dashboard.php
 * Uses Chart.js (CDN) for booking trend and status breakdown charts.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

$stats = [
    'total_customers' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn(),
    'total_providers' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "provider"')->fetchColumn(),
    'total_bookings' => (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
    'total_revenue' => (float) $pdo->query('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = "paid"')->fetchColumn(),
    'pending_approvals' => (int) $pdo->query('SELECT COUNT(*) FROM providers WHERE approval_status = "pending"')->fetchColumn(),
    'active_providers' => (int) $pdo->query('SELECT COUNT(*) FROM providers WHERE approval_status = "approved"')->fetchColumn(),
    'completed_events' => (int) $pdo->query('SELECT COUNT(*) FROM bookings WHERE booking_status = "completed"')->fetchColumn(),
];

// Recent bookings
$recentBookings = $pdo->query(
    'SELECT b.*, cu.fullname as customer_name, pu.fullname as provider_name
     FROM bookings b
     JOIN users cu ON cu.id = b.customer_id
     JOIN providers p ON p.id = b.provider_id
     JOIN users pu ON pu.id = p.user_id
     ORDER BY b.created_at DESC LIMIT 8'
)->fetchAll();

// Recent payments
$recentPayments = $pdo->query(
    'SELECT pay.*, b.event_name, cu.fullname as customer_name
     FROM payments pay
     JOIN bookings b ON b.id = pay.booking_id
     JOIN users cu ON cu.id = b.customer_id
     ORDER BY pay.created_at DESC LIMIT 8'
)->fetchAll();

// Bookings per month (last 6 months) for line chart
$monthlyStmt = $pdo->query(
    'SELECT DATE_FORMAT(created_at, "%Y-%m") as ym, COUNT(*) as total
     FROM bookings
     WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
     GROUP BY ym ORDER BY ym'
);
$monthlyRaw = $monthlyStmt->fetchAll();
$monthlyLabels = [];
$monthlyData = [];
foreach ($monthlyRaw as $row) {
    $monthlyLabels[] = date('M Y', strtotime($row['ym'] . '-01'));
    $monthlyData[] = (int) $row['total'];
}

// Booking status breakdown for doughnut chart
$statusStmt = $pdo->query('SELECT booking_status, COUNT(*) as total FROM bookings GROUP BY booking_status');
$statusRaw = $statusStmt->fetchAll();
$statusLabels = [];
$statusData = [];
foreach ($statusRaw as $row) {
    $statusLabels[] = ucfirst($row['booking_status']);
    $statusData[] = (int) $row['total'];
}

$current_page = 'dashboard';
$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Admin Dashboard</h3>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-purple">
                            <i class="bi bi-people stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['total_customers'] ?></p>
                            <p class="mb-0 small opacity-75">Total Customers</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-teal">
                            <i class="bi bi-person-badge stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['total_providers'] ?></p>
                            <p class="mb-0 small opacity-75">Total Providers</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-orange">
                            <i class="bi bi-calendar3 stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['total_bookings'] ?></p>
                            <p class="mb-0 small opacity-75">Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-rose">
                            <i class="bi bi-cash-stack stat-icon"></i>
                            <p class="stat-value mb-0"><?= format_money($stats['total_revenue']) ?></p>
                            <p class="mb-0 small opacity-75">Total Revenue</p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h4 class="fw-bold mb-0"><?= (int) $stats['pending_approvals'] ?></h4>
                            <p class="small text-secondary mb-0">Pending Approvals</p>
                            <?php if ($stats['pending_approvals'] > 0): ?>
                                <a href="<?= e(APP_URL) ?>/admin/providers.php?status=pending" class="small mt-1">Review now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h4 class="fw-bold mb-0"><?= (int) $stats['active_providers'] ?></h4>
                            <p class="small text-secondary mb-0">Active Providers</p>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h4 class="fw-bold mb-0"><?= (int) $stats['completed_events'] ?></h4>
                            <p class="small text-secondary mb-0">Completed Events</p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Bookings (Last 6 Months)</h6>
                                <canvas id="bookingsChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Booking Status Breakdown</h6>
                                <canvas id="statusChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Recent Bookings</h6>
                                <a href="<?= e(APP_URL) ?>/admin/bookings.php" class="small">View all</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentBookings)): ?>
                                    <p class="text-secondary p-3 mb-0">No bookings yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0 align-middle">
                                            <tbody>
                                                <?php foreach ($recentBookings as $b): ?>
                                                    <tr>
                                                        <td class="ps-3">
                                                            <span class="fw-semibold"><?= e($b['event_name']) ?></span><br>
                                                            <span class="small text-secondary"><?= e($b['customer_name']) ?> &rarr; <?= e($b['provider_name']) ?></span>
                                                        </td>
                                                        <td class="text-end pe-3"><span class="badge <?= booking_status_badge($b['booking_status']) ?>"><?= ucfirst($b['booking_status']) ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Recent Payments</h6>
                                <a href="<?= e(APP_URL) ?>/admin/payments.php" class="small">View all</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentPayments)): ?>
                                    <p class="text-secondary p-3 mb-0">No payments yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0 align-middle">
                                            <tbody>
                                                <?php foreach ($recentPayments as $p): ?>
                                                    <tr>
                                                        <td class="ps-3">
                                                            <span class="fw-semibold"><?= e($p['event_name']) ?></span><br>
                                                            <span class="small text-secondary"><?= e($p['customer_name']) ?></span>
                                                        </td>
                                                        <td class="text-end pe-3">
                                                            <span class="fw-semibold"><?= format_money($p['amount']) ?></span><br>
                                                            <span class="badge <?= payment_status_badge($p['payment_status']) ?>"><?= ucfirst($p['payment_status']) ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const bookingsCtx = document.getElementById('bookingsChart');
    new Chart(bookingsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode($monthlyData) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.3,
                fill: true,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });

    const statusCtx = document.getElementById('statusChart');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusData) ?>,
                backgroundColor: ['#f59e0b', '#0ea5e9', '#ef4444', '#6b7280', '#6366f1', '#22c55e'],
            }]
        },
        options: { responsive: true }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
