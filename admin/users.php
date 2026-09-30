<?php
/**
 * admin/users.php
 * Manage all users (admin/customer/provider). Activate/Deactivate/Suspend.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

// Handle status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['action'])) {
    verify_csrf();

    $targetUserId = (int) $_POST['user_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $targetUserId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        set_flash('error', 'User not found.');
    } elseif ($targetUser['role'] === 'admin') {
        set_flash('error', 'Admin accounts cannot be modified here.');
    } else {
        $newStatus = match ($action) {
            'activate' => 'active',
            'deactivate' => 'inactive',
            'suspend' => 'suspended',
            default => null,
        };

        if ($newStatus) {
            $update = $pdo->prepare('UPDATE users SET status = :status WHERE id = :id');
            $update->execute(['status' => $newStatus, 'id' => $targetUserId]);
            log_activity($pdo, current_user_id(), "Set user #{$targetUserId} status to {$newStatus}.");
            set_flash('success', 'User status updated to "' . $newStatus . '".');
        }
    }
    redirect('admin/users.php');
}

$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$conditions = ['1=1'];
$params = [];

if ($roleFilter !== '' && in_array($roleFilter, ['admin', 'customer', 'provider'], true)) {
    $conditions[] = 'role = :role';
    $params['role'] = $roleFilter;
}
if ($statusFilter !== '' && in_array($statusFilter, ['active', 'inactive', 'suspended'], true)) {
    $conditions[] = 'status = :status';
    $params['status'] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = '(fullname LIKE :q OR email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare("SELECT * FROM users WHERE {$where} ORDER BY created_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

$current_page = 'users';
$page_title = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Manage Users</h3>

                <form method="GET" action="" class="row g-2 mb-4">
                    <div class="col-md-4">
                        <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search name or email...">
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customer</option>
                            <option value="provider" <?= $roleFilter === 'provider' ? 'selected' : '' ?>>Provider</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
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
                                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= e($u['fullname']) ?></td>
                                        <td><?= e($u['email']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= ucfirst(e($u['role'])) ?></span></td>
                                        <td>
                                            <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : ($u['status'] === 'suspended' ? 'bg-danger' : 'bg-secondary') ?>">
                                                <?= ucfirst(e($u['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= format_date($u['created_at']) ?></td>
                                        <td class="text-end">
                                            <?php if ($u['role'] !== 'admin'): ?>
                                                <div class="btn-group">
                                                    <?php if ($u['status'] !== 'active'): ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                            <input type="hidden" name="action" value="activate">
                                                            <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($u['status'] !== 'inactive'): ?>
                                                        <form method="POST" action="" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                            <input type="hidden" name="action" value="deactivate">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Deactivate</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($u['status'] !== 'suspended'): ?>
                                                        <form method="POST" action="" class="d-inline" data-confirm="Suspend this user?">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                                            <input type="hidden" name="action" value="suspend">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Suspend</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="small text-secondary">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                    <tr><td colspan="6" class="text-center text-secondary py-4">No users found.</td></tr>
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
