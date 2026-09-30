<?php
/**
 * customer/dashboard.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';



require_role('customer');

$userId = current_user_id();

$stats = [
    'total' => 0, 'pending' => 0, 'accepted' => 0, 'completed' => 0,
];
$statsStmt = $pdo->prepare(
    'SELECT booking_status, COUNT(*) as total FROM bookings WHERE customer_id = :id GROUP BY booking_status'
);
$statsStmt->execute(['id' => $userId]);
foreach ($statsStmt->fetchAll() as $row) {
    $stats['total'] += (int) $row['total'];
    if (isset($stats[$row['booking_status']])) {
        $stats[$row['booking_status']] = (int) $row['total'];
    }
}

$recentStmt = $pdo->prepare(
    'SELECT b.*, u.fullname as provider_name, p.id as provider_profile_id
     FROM bookings b
     JOIN providers p ON p.id = b.provider_id
     JOIN users u ON u.id = p.user_id
     WHERE b.customer_id = :id
     ORDER BY b.created_at DESC
     LIMIT 5'
);
$recentStmt->execute(['id' => $userId]);
$recentBookings = $recentStmt->fetchAll();

$current_page = 'dashboard';
$page_title = 'My Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Welcome back, <?= e(current_user_name()) ?> 👋</h3>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-purple">
                            <i class="bi bi-calendar3 stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['total'] ?></p>
                            <p class="mb-0 small opacity-75">Total Bookings</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-orange">
                            <i class="bi bi-hourglass-split stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['pending'] ?></p>
                            <p class="mb-0 small opacity-75">Pending</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-teal">
                            <i class="bi bi-check-circle stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['accepted'] ?></p>
                            <p class="mb-0 small opacity-75">Accepted</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="stat-card bg-rose">
                            <i class="bi bi-trophy stat-icon"></i>
                            <p class="stat-value mb-0"><?= (int) $stats['completed'] ?></p>
                            <p class="mb-0 small opacity-75">Completed</p>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Bookings</h5>
                        <a href="<?= e(APP_URL) ?>/customer/bookings.php" class="small">View all</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentBookings)): ?>
                            <p class="text-secondary p-4 mb-0">You haven't made any bookings yet. <a href="<?= e(APP_URL) ?>/customer/search.php">Find talent to book</a>.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Event</th>
                                            <th>Provider</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBookings as $b): ?>
                                            <tr>
                                                <td><?= e($b['event_name']) ?></td>
                                                <td><?= e($b['provider_name']) ?></td>
                                                <td><?= format_date($b['event_date']) ?></td>
                                                <td><span class="badge <?= booking_status_badge($b['booking_status']) ?>"><?= ucfirst(e($b['booking_status'])) ?></span></td>
                                                <td><a href="<?= e(APP_URL) ?>/customer/booking_view.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
