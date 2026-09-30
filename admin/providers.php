<?php
/**
 * admin/providers.php
 * Manage provider approval workflow: approve, reject, suspend, reactivate.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provider_id'], $_POST['action'])) {
    verify_csrf();

    $providerId = (int) $_POST['provider_id'];
    $action = $_POST['action'];

    $newStatus = match ($action) {
        'approve'    => 'approved',
        'reject'     => 'rejected',
        'suspend'    => 'suspended',
        'reactivate' => 'approved',
        default      => null,
    };

    $stmt = $pdo->prepare('SELECT p.*, u.fullname FROM providers p JOIN users u ON u.id = p.user_id WHERE p.id = :id');
    $stmt->execute(['id' => $providerId]);
    $provider = $stmt->fetch();

    if (!$provider) {
        set_flash('error', 'Provider not found.');
    } else {
    if ($action === 'delete') {

    $pdo->beginTransaction();

    try {

        $userId = (int)$provider['user_id'];

        $deleteProvider = $pdo->prepare(
            "DELETE FROM providers WHERE id = :id"
        );
        $deleteProvider->execute([
            'id' => $providerId
        ]);

        $deleteUser = $pdo->prepare(
            "DELETE FROM users WHERE id = :id"
        );
        $deleteUser->execute([
            'id' => $userId
        ]);

        audit_log(
            $pdo,
            'PROVIDER_DELETE',
            "Deleted provider #{$providerId} ({$provider['fullname']}).",
            'provider',
            $providerId
        );

        $pdo->commit();

        set_flash('success', 'Provider deleted successfully.');

    } catch (Exception $e) {

        $pdo->rollBack();

        set_flash('error', $e->getMessage());
    }

    redirect('admin/providers.php');
}

        if ($newStatus) {
            $update = $pdo->prepare('UPDATE providers SET approval_status = :status WHERE id = :id');
            $update->execute(['status' => $newStatus, 'id' => $providerId]);

$messages = [
    'approved' => 'Congratulations! Your provider profile has been approved and is now visible to customers.',
    'rejected' => 'Your provider profile was not approved. Please review your details or contact support.',
    'suspended' => 'Your provider account has been suspended. Contact support for assistance.',
];

/* pata user_id wa provider */
$userId = (int)$provider['user_id'];

$title = match ($newStatus) {
    'approved'  => 'Provider Approved',
    'rejected'  => 'Provider Rejected',
    'suspended' => 'Provider Suspended',
    default     => 'Provider Update',
};

$message = $messages[$newStatus] ?? 'Your provider account status has been updated.';

push_notification(
    $pdo,
    $userId,
    $title,
    $message,
    'admin'
);

            log_activity($pdo, current_user_id(), "Set provider #{$providerId} ({$provider['fullname']}) status to {$newStatus}.");
            audit_log(
                $pdo,
                'PROVIDER_STATUS_CHANGE',
                "Set provider #{$providerId} ({$provider['fullname']}) status to {$newStatus}.",
                'provider',
                $providerId
            );
            set_flash('success', $provider['fullname'] . '\'s status is now "' . $newStatus . '".');
        }
    }
    redirect('admin/providers.php');
}

$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$conditions = ['1=1'];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'suspended'], true)) {
    $conditions[] = 'p.approval_status = :status';
    $params['status'] = $statusFilter;
}
if ($categoryFilter !== '') {
    $conditions[] = 'p.category_id = :cat';
    $params['cat'] = (int) $categoryFilter;
}
$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare(
    "SELECT p.*, u.fullname, u.email, u.status as user_status, c.category_name
     FROM providers p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE {$where}
     ORDER BY p.created_at DESC"
);
$stmt->execute($params);
$providers = $stmt->fetchAll();

$categories = $pdo->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();

$current_page = 'providers';
$page_title = 'Manage Providers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold mb-0">Manage Providers</h3>
                    <a href="<?= e(APP_URL) ?>/admin/provider_add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Provider
                    </a>
                </div>

                <form method="GET" action="" class="row g-2 mb-4">
                    <div class="col-md-4">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending', 'approved', 'rejected', 'suspended'] as $s): ?>
                                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= (string) $categoryFilter === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
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
                                <tr><th>Provider</th><th>Category</th><th>Location</th><th>Rating</th><th>Status</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($providers as $p): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(APP_URL) ?>/admin/provider_view.php?id=<?= (int) $p['id'] ?>" class="fw-semibold text-decoration-none"><?= e($p['fullname']) ?></a><br>
                                            <span class="small text-secondary"><?= e($p['email']) ?></span>
                                        </td>
                                        <td><?= e($p['category_name']) ?></td>
                                        <td><?= e($p['location'] ?: '-') ?></td>
                                        <td><?= render_stars((float) $p['average_rating']) ?></td>
                                        <td><span class="badge <?= approval_status_badge($p['approval_status']) ?>"><?= ucfirst(e($p['approval_status'])) ?></span></td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <?php if ($p['approval_status'] === 'pending'): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                    </form>
                                                    <form method="POST" action="" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                                    </form>
                                                <?php elseif ($p['approval_status'] === 'approved'): ?>
                                                    <form method="POST" action="" class="d-inline" data-confirm="Suspend this provider?">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                                        <input type="hidden" name="action" value="suspend">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Suspend</button>
                                                    </form>
                                                <?php elseif (in_array($p['approval_status'], ['rejected', 'suspended'], true)): ?>
                                                    <form method="POST" action="" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                                                        <input type="hidden" name="action" value="reactivate">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="<?= e(APP_URL) ?>/admin/provider_view.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                                <form method="POST"
      action=""
      class="d-inline"
      onsubmit="return confirm('Delete this provider permanently?');">

    <?= csrf_field() ?>

    <input type="hidden"
           name="provider_id"
           value="<?= (int)$p['id'] ?>">

    <input type="hidden"
           name="action"
           value="delete">

    <button type="submit"
            class="btn btn-sm btn-danger">
        Delete
    </button>

</form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($providers)): ?>
                                    <tr><td colspan="6" class="text-center text-secondary py-4">No providers found.</td></tr>
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
