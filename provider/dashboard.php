<?php
/**
 * provider/dashboard.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


require_role('provider');

$profileStmt = $pdo->prepare('SELECT p.*, c.category_name FROM providers p JOIN categories c ON c.id = p.category_id WHERE p.user_id = :uid LIMIT 1');
$profileStmt->execute(['uid' => current_user_id()]);
$providerProfile = $profileStmt->fetch();

$stats = ['pending' => 0, 'upcoming' => 0, 'completed' => 0, 'reviews' => 0];
$earnings = 0;
$recentRequests = [];

if ($providerProfile) {
    $countsStmt = $pdo->prepare('SELECT booking_status, COUNT(*) as total FROM bookings WHERE provider_id = :pid GROUP BY booking_status');
    $countsStmt->execute(['pid' => $providerProfile['id']]);
    $byStatus = [];
    foreach ($countsStmt->fetchAll() as $row) {
        $byStatus[$row['booking_status']] = (int) $row['total'];
    }
    $stats['pending'] = $byStatus['pending'] ?? 0;
    $stats['upcoming'] = ($byStatus['accepted'] ?? 0) + ($byStatus['ongoing'] ?? 0);
    $stats['completed'] = $byStatus['completed'] ?? 0;

    $reviewCountStmt = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE provider_id = :pid');
    $reviewCountStmt->execute(['pid' => $providerProfile['id']]);
    $stats['reviews'] = (int) $reviewCountStmt->fetchColumn();

    $earningsStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay
         JOIN bookings b ON b.id = pay.booking_id
         WHERE b.provider_id = :pid AND pay.payment_status = "paid"'
    );
    $earningsStmt->execute(['pid' => $providerProfile['id']]);
    $earnings = (float) $earningsStmt->fetchColumn();

    $recentStmt = $pdo->prepare(
        'SELECT b.*, u.fullname as customer_name
         FROM bookings b
         JOIN users u ON u.id = b.customer_id
         WHERE b.provider_id = :pid AND b.booking_status = "pending"
         ORDER BY b.created_at DESC LIMIT 5'
    );
    $recentStmt->execute(['pid' => $providerProfile['id']]);
    $recentRequests = $recentStmt->fetchAll();
}

$current_page = 'dashboard';
$page_title = 'My Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-1">Welcome back, <?= e(current_user_name()) ?> 👋</h3>

                <?php if (!$providerProfile): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> You haven't set up your provider profile yet.
                        <a href="<?= e(APP_URL) ?>/provider/profile.php" class="fw-semibold">Complete it now</a> to start receiving bookings.
                    </div>
                <?php elseif ($providerProfile['approval_status'] === 'pending'): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-hourglass-split"></i> Your profile is pending admin approval. You'll be notified once approved and visible to customers.
                    </div>
                <?php elseif ($providerProfile['approval_status'] === 'rejected'): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="bi bi-x-circle"></i> Your profile was not approved. Please review and update your details, or contact support.
                    </div>
                <?php elseif ($providerProfile['approval_status'] === 'suspended'): ?>
                    <div class="alert alert-danger mt-3">
                        <i class="bi bi-slash-circle"></i> Your account has been suspended. Contact support for assistance.
                    </div>
                <?php else: ?>
                    <p class="text-secondary mb-4"><?= e($providerProfile['category_name']) ?> &middot; <?= e($providerProfile['location'] ?: 'No location set') ?></p>
                <?php endif; ?>

                <?php if ($providerProfile): ?>
                    <div class="row g-3 mb-4 mt-1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card bg-orange">
                                <i class="bi bi-hourglass-split stat-icon"></i>
                                <p class="stat-value mb-0"><?= (int) $stats['pending'] ?></p>
                                <p class="mb-0 small opacity-75">Pending Requests</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card bg-purple">
                                <i class="bi bi-calendar3 stat-icon"></i>
                                <p class="stat-value mb-0"><?= (int) $stats['upcoming'] ?></p>
                                <p class="mb-0 small opacity-75">Upcoming Events</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card bg-teal">
                                <i class="bi bi-cash-stack stat-icon"></i>
                                <p class="stat-value mb-0"><?= format_money($earnings) ?></p>
                                <p class="mb-0 small opacity-75">Total Earnings</p>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="stat-card bg-rose">
                                <i class="bi bi-star stat-icon"></i>
                                <p class="stat-value mb-0"><?= (int) $stats['reviews'] ?></p>
                                <p class="mb-0 small opacity-75">Reviews (<?= number_format((float) $providerProfile['average_rating'], 1) ?>★)</p>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Pending Booking Requests</h5>
                            <a href="<?= e(APP_URL) ?>/provider/bookings.php" class="small">View all</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentRequests)): ?>
                                <p class="text-secondary p-4 mb-0">No pending booking requests right now.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr><th>Event</th><th>Customer</th><th>Date</th><th></th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentRequests as $r): ?>
                                                <tr>
                                                    <td><?= e($r['event_name']) ?></td>
                                                    <td><?= e($r['customer_name']) ?></td>
                                                    <td><?= format_date($r['event_date']) ?></td>
                                                    <td><a href="<?= e(APP_URL) ?>/provider/booking_view.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-primary">Respond</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
