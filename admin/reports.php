<?php
/**
 * admin/reports.php
 * Generates: Daily/Weekly/Monthly/Annual bookings & revenue, Most Booked Talent,
 * Top Rated Talent, Customer Statistics.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

// ----- Period-based bookings & revenue -----
$periods = [
    'daily'   => ['label' => 'Today', 'interval' => 'CURRENT_DATE()'],
    'weekly'  => ['label' => 'This Week', 'interval' => 'DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)'],
    'monthly' => ['label' => 'This Month', 'interval' => 'DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)'],
    'annual'  => ['label' => 'This Year', 'interval' => 'DATE_SUB(CURRENT_DATE(), INTERVAL 365 DAY)'],
];

$periodStats = [];
foreach ($periods as $key => $meta) {
    $bookingCount = $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at >= {$meta['interval']}")->fetchColumn();
    $revenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status = 'paid' AND created_at >= {$meta['interval']}")->fetchColumn();
    $periodStats[$key] = ['label' => $meta['label'], 'bookings' => (int) $bookingCount, 'revenue' => (float) $revenue];
}

// ----- Most booked talent -----
$mostBooked = $pdo->query(
    'SELECT u.fullname, c.category_name, COUNT(b.id) as booking_count
     FROM bookings b
     JOIN providers p ON p.id = b.provider_id
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     GROUP BY p.id
     ORDER BY booking_count DESC
     LIMIT 10'
)->fetchAll();

// ----- Top rated talent -----
$topRated = $pdo->query(
    'SELECT u.fullname, c.category_name, p.average_rating,
            (SELECT COUNT(*) FROM reviews WHERE provider_id = p.id) as review_count
     FROM providers p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.approval_status = "approved"
     ORDER BY p.average_rating DESC, review_count DESC
     LIMIT 10'
)->fetchAll();

// ----- Customer statistics -----
$customerStats = [
    'total' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer"')->fetchColumn(),
    'active' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "customer" AND status = "active"')->fetchColumn(),
    'with_bookings' => (int) $pdo->query('SELECT COUNT(DISTINCT customer_id) FROM bookings')->fetchColumn(),
    'repeat' => (int) $pdo->query('SELECT COUNT(*) FROM (SELECT customer_id FROM bookings GROUP BY customer_id HAVING COUNT(*) > 1) as t')->fetchColumn(),
];

$current_page = 'reports';
$page_title = 'Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Reports</h3>

                <h6 class="fw-bold mb-3">Bookings & Revenue by Period</h6>
                <div class="row g-3 mb-4">
                    <?php foreach ($periodStats as $stat): ?>
                        <div class="col-sm-6 col-lg-3">
                            <div class="card border-0 shadow-sm p-3">
                                <p class="small text-secondary mb-1"><?= e($stat['label']) ?></p>
                                <h5 class="fw-bold mb-0"><?= (int) $stat['bookings'] ?> bookings</h5>
                                <p class="text-primary fw-semibold mb-0"><?= format_money($stat['revenue']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h6 class="fw-bold mb-3">Customer Statistics</h6>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h5 class="fw-bold mb-0"><?= (int) $customerStats['total'] ?></h5>
                            <p class="small text-secondary mb-0">Total Customers</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h5 class="fw-bold mb-0"><?= (int) $customerStats['active'] ?></h5>
                            <p class="small text-secondary mb-0">Active Customers</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h5 class="fw-bold mb-0"><?= (int) $customerStats['with_bookings'] ?></h5>
                            <p class="small text-secondary mb-0">Customers Who Booked</p>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card border-0 shadow-sm p-3 text-center">
                            <h5 class="fw-bold mb-0"><?= (int) $customerStats['repeat'] ?></h5>
                            <p class="small text-secondary mb-0">Repeat Customers</p>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Most Booked Talent</h6></div>
                            <div class="card-body p-0">
                                <?php if (empty($mostBooked)): ?>
                                    <p class="text-secondary p-3 mb-0">No data yet.</p>
                                <?php else: ?>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($mostBooked as $mb): ?>
                                                <tr>
                                                    <td class="ps-3"><?= e($mb['fullname']) ?><br><span class="small text-secondary"><?= e($mb['category_name']) ?></span></td>
                                                    <td class="text-end pe-3 fw-semibold"><?= (int) $mb['booking_count'] ?> bookings</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Top Rated Talent</h6></div>
                            <div class="card-body p-0">
                                <?php if (empty($topRated)): ?>
                                    <p class="text-secondary p-3 mb-0">No data yet.</p>
                                <?php else: ?>
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($topRated as $tr): ?>
                                                <tr>
                                                    <td class="ps-3"><?= e($tr['fullname']) ?><br><span class="small text-secondary"><?= e($tr['category_name']) ?></span></td>
                                                    <td class="text-end pe-3"><?= render_stars((float) $tr['average_rating']) ?><br><span class="small text-secondary"><?= (int) $tr['review_count'] ?> reviews</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
