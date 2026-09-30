<?php
/**
 * customer/notifications.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

// Mark single notification as read
if (isset($_GET['read'])) {
    $notifId = (int) $_GET['read'];
    $stmt = $pdo->prepare('UPDATE notifications SET status = "read" WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $notifId, 'uid' => current_user_id()]);
    redirect('customer/notifications.php');
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    verify_csrf();
    $stmt = $pdo->prepare('UPDATE notifications SET status = "read" WHERE user_id = :uid AND status = "unread"');
    $stmt->execute(['uid' => current_user_id()]);
    redirect('customer/notifications.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50');
$stmt->execute(['uid' => current_user_id()]);
$notifications = $stmt->fetchAll();

$current_page = 'notifications';
$page_title = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid" style="max-width: 800px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold mb-0">Notifications</h3>
                    <?php if (!empty($notifications)): ?>
                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <button type="submit" name="mark_all_read" value="1" class="btn btn-sm btn-outline-secondary">Mark all as read</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="alert alert-light border text-center py-5">
                        <i class="bi bi-bell-slash fs-1 text-secondary"></i>
                        <p class="mt-3 mb-0">No notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>

<?php
$badgeClass = 'bg-secondary';

switch ($n['sender_role'] ?? '') {
    case 'admin':
        $badgeClass = 'bg-danger';
        break;

    case 'provider':
        $badgeClass = 'bg-success';
        break;

    case 'customer':
        $badgeClass = 'bg-primary';
        break;
}
?>

<a href="?read=<?= (int)$n['id'] ?>"
   class="list-group-item list-group-item-action <?= $n['status'] === 'unread' ? 'bg-primary-subtle' : '' ?>">

    <div class="d-flex justify-content-between">

        <div>

            <?php if (!empty($n['sender_role'])): ?>
                <span class="badge <?= $badgeClass ?>">
                    <?= ucfirst($n['sender_role']) ?>
                </span>
            <?php endif; ?>

            <h6 class="mt-2 mb-1 fw-semibold">
                <?= e($n['title']) ?>
            </h6>

        </div>

        <small class="text-secondary">
            <?= format_date($n['created_at']) ?>
        </small>

    </div>

    <p class="mb-0 small text-secondary">
        <?= e($n['message']) ?>
    </p>

</a>

<?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
