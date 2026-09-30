<?php
/**
 * provider/bookings.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$statusFilter = $_GET['status'] ?? '';
$conditions = ['b.provider_id = :pid'];
$params = ['pid' => $providerProfile['id']];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'accepted', 'rejected', 'cancelled', 'ongoing', 'completed'], true)) {
    $conditions[] = 'b.booking_status = :status';
    $params['status'] = $statusFilter;
}
$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    "SELECT b.*, u.fullname as customer_name, u.phone as customer_phone
     FROM bookings b
     JOIN users u ON u.id = b.customer_id
     WHERE {$where}
     ORDER BY b.created_at DESC"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$current_page = 'bookings';
$page_title = 'Booking Requests';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Booking Requests</h3>

                <div class="d-flex gap-2 mb-4 flex-wrap">
                    <a href="?status=" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
                    <?php foreach (['pending', 'accepted', 'rejected', 'ongoing', 'completed', 'cancelled'] as $s): ?>
                        <a href="?status=<?= e($s) ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= ucfirst($s) ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="alert alert-light border text-center py-5">
                        <p class="mb-0">No booking requests found.</p>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b): ?>
                                        <tr>
                                            <td><?= e($b['event_name']) ?><br><span class="small text-secondary"><?= e($b['event_type']) ?></span></td>
                                            <td><?= e($b['customer_name']) ?></td>
                                            <td><?= format_date($b['event_date']) ?></td>
                                            <td><span class="badge <?= booking_status_badge($b['booking_status']) ?>"><?= ucfirst(e($b['booking_status'])) ?></span></td>
                                            <td><a href="<?= e(APP_URL) ?>/provider/booking_view.php?id=<?= (int) $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
