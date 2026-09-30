<?php
/**
 * admin/bookings.php
 * Read-only oversight of all bookings across the platform.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$conditions = ['1=1'];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'accepted', 'rejected', 'cancelled', 'ongoing', 'completed'], true)) {
    $conditions[] = 'b.booking_status = :status';
    $params['status'] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = 'b.event_name LIKE :q';
    $params['q'] = '%' . $search . '%';
}
$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    "SELECT b.*, cu.fullname as customer_name, pu.fullname as provider_name
     FROM bookings b
     JOIN users cu ON cu.id = b.customer_id
     JOIN providers p ON p.id = b.provider_id
     JOIN users pu ON pu.id = p.user_id
     WHERE {$where}
     ORDER BY b.created_at DESC"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$current_page = 'bookings';
$page_title = 'All Bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">All Bookings</h3>

                <form method="GET" action="" class="row g-2 mb-4">
                    <div class="col-md-4">
                        <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search event name...">
                    </div>
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending', 'accepted', 'rejected', 'ongoing', 'completed', 'cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
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
                                <tr><th>Event</th><th>Customer</th><th>Provider</th><th>Date</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td><?= e($b['event_name']) ?><br><span class="small text-secondary"><?= e($b['event_type']) ?></span></td>
                                        <td><?= e($b['customer_name']) ?></td>
                                        <td><?= e($b['provider_name']) ?></td>
                                        <td><?= format_date($b['event_date']) ?></td>
                                        <td><span class="badge <?= booking_status_badge($b['booking_status']) ?>"><?= ucfirst(e($b['booking_status'])) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($bookings)): ?>
                                    <tr><td colspan="5" class="text-center text-secondary py-4">No bookings found.</td></tr>
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
