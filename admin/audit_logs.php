<?php
/**
 * admin/audit_logs.php
 * View audit history for customers and providers.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

/*
 * Get audit logs, with optional search/filter by user, role, action and
 * a date range. All filters are optional and combine with AND.
 */
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$conditions = ['1=1'];
$params = [];

if ($roleFilter !== '' && in_array($roleFilter, ['admin', 'customer', 'provider'], true)) {
    $conditions[] = 'a.user_role = :role';
    $params['role'] = $roleFilter;
}
if ($actionFilter !== '') {
    $conditions[] = 'a.action = :action';
    $params['action'] = $actionFilter;
}
if ($search !== '') {
    $conditions[] = '(u.fullname LIKE :search OR u.email LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($dateFrom !== '') {
    $conditions[] = 'a.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $conditions[] = 'a.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}
$where = implode(' AND ', $conditions);

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.user_id,
        a.user_role,
        a.action,
        a.description,
        a.ip_address,
        a.created_at,
        u.fullname,
        u.email
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE {$where}
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Distinct actions recorded so far, for the filter dropdown.
$actionOptions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$current_page = 'audit_logs';
$page_title = 'Audit Logs';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid">

                <!-- Page title -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-1">
                            <i class="bi bi-shield-check"></i>
                            Audit Logs
                        </h3>

                        <p class="text-secondary mb-0">
                            Track important activities performed by customers and providers.
                        </p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small text-secondary mb-1">User (name or email)</label>
                                <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search user...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-secondary mb-1">Role</label>
                                <select name="role" class="form-select">
                                    <option value="">All Roles</option>
                                    <?php foreach (['admin', 'provider', 'customer'] as $r): ?>
                                        <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-secondary mb-1">Action</label>
                                <select name="action" class="form-select">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actionOptions as $a): ?>
                                        <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-secondary mb-1">From</label>
                                <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-secondary mb-1">To</label>
                                <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
                            </div>
                            <div class="col-md-1 d-flex gap-1">
                                <button type="submit" class="btn btn-primary flex-grow-1" title="Filter">
                                    <i class="bi bi-funnel"></i>
                                </button>
                            </div>
                            <?php if ($search !== '' || $roleFilter !== '' || $actionFilter !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                                <div class="col-12">
                                    <a href="<?= e(APP_URL) ?>/admin/audit_logs.php" class="small text-secondary">
                                        <i class="bi bi-x-circle"></i> Clear filters
                                    </a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Audit card -->
                <div class="card border-0 shadow-sm">

                    <div class="card-header bg-white py-3">
                        <h5 class="mb-1">
                            <i class="bi bi-clock-history"></i>
                            System Activity History
                        </h5>

                        <small class="text-secondary">
                            <?= count($logs) ?> record<?= count($logs) === 1 ? '' : 's' ?> — each shows the user, role, action, time and IP address.
                        </small>
                    </div>

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (!empty($logs)): ?>

                                <?php foreach ($logs as $log): ?>

                                    <tr>

                                        <!-- Date & Time -->
                                        <td>
                                            <small>
                                                <?= e($log['created_at']) ?>
                                            </small>
                                        </td>

                                        <!-- User -->
                                        <td>
                                            <strong>
                                                <?= e($log['fullname'] ?? 'Unknown User') ?>
                                            </strong>

                                            <br>

                                            <small class="text-secondary">
                                                <?= e($log['email'] ?? '-') ?>
                                            </small>
                                        </td>

                                        <!-- Role -->
                                        <td>

                                            <?php
                                            $roleClass = match ($log['user_role']) {
                                                'provider' => 'bg-primary',
                                                'customer' => 'bg-success',
                                                default => 'bg-secondary'
                                            };
                                            ?>

                                            <span class="badge <?= $roleClass ?>">
                                                <?= e(ucfirst($log['user_role'])) ?>
                                            </span>

                                        </td>

                                        <!-- Action -->
                                        <td>
                                            <span class="badge bg-dark">
                                                <?= e($log['action']) ?>
                                            </span>
                                        </td>

                                        <!-- Details -->
                                        <td>
                                            <small>
                                                <?= e($log['description'] ?: '-') ?>
                                            </small>
                                        </td>

                                        <!-- IP Address -->
                                        <td>
                                            <small>
                                                <?= e($log['ip_address'] ?? '-') ?>
                                            </small>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="6" class="text-center text-secondary py-5">

                                        <i class="bi bi-info-circle fs-3 d-block mb-2"></i>

                                        No audit logs found.

                                    </td>
                                </tr>

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