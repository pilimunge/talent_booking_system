<?php
/**
 * provider/notifications.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


require_role('provider');

if (isset($_GET['read'])) {
    $notifId = (int) $_GET['read'];

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET status = 'read'
        WHERE id = :id
        AND user_id = :uid
    ");

    $stmt->execute([
        'id' => $notifId,
        'uid' => current_user_id()
    ]);

    redirect('provider/notifications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {

    verify_csrf();

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET status = 'read'
        WHERE user_id = :uid
        AND status = 'unread'
    ");

    $stmt->execute([
        'uid' => current_user_id()
    ]);

    redirect('provider/notifications.php');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT 50
");

$stmt->execute([
    'uid' => current_user_id()
]);

$notifications = $stmt->fetchAll();

$current_page = 'notifications';
$page_title = 'Notifications';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid" style="max-width:900px;">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold">Notifications</h2>

                    <?php if (!empty($notifications)): ?>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    name="mark_all_read"
                                    value="1"
                                    class="btn btn-outline-secondary btn-sm">
                                Mark all as read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($notifications)): ?>

                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-bell-slash fs-1 text-secondary"></i>
                            <h5 class="mt-3">No Notifications</h5>
                            <p class="text-muted mb-0">
                                You don't have any notifications yet.
                            </p>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="card shadow-sm border-0">
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

<?php if (!empty($n['sender_role'])): ?>
    <span class="badge <?= $badgeClass ?>">
        <?= ucfirst($n['sender_role']) ?>
    </span>
<?php endif; ?>

<h6 class="fw-bold mt-2 mb-1">
    <?= e($n['title']) ?>
</h6>

<p class="mb-0 text-secondary">
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