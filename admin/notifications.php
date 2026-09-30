<?php
/**
 * admin/notifications.php
 * Shows the admin's own notifications, and includes a simple broadcast
 * tool to push a notification to all customers, all providers, or everyone.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

$broadcastSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['broadcast'])) {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $audience = $_POST['audience'] ?? 'all';

    if ($title === '' || $message === '') {
        set_flash('error', 'Title and message are required for a broadcast.');
    } else {
        $sql = 'SELECT id FROM users WHERE status = "active"';
        if ($audience === 'customers') {
            $sql .= ' AND role = "customer"';
        } elseif ($audience === 'providers') {
            $sql .= ' AND role = "provider"';
        } else {
            $sql .= ' AND role != "admin"';
        }

        $recipients = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($recipients as $userId) {
          push_notification(
    $pdo,
    (int)$userId,
    $title,
    $message,
    'admin'
);
        }

        log_activity($pdo, current_user_id(), 'Sent broadcast notification to ' . count($recipients) . ' user(s).');
        set_flash('success', 'Broadcast sent to ' . count($recipients) . ' user(s).');
        redirect('admin/notifications.php');
    }
}

if (isset($_GET['read'])) {
    $notifId = (int) $_GET['read'];
    $stmt = $pdo->prepare('UPDATE notifications SET status = "read" WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $notifId, 'uid' => current_user_id()]);
    redirect('admin/notifications.php');
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
                <h3 class="fw-bold mb-4">Notifications</h3>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Send Broadcast Notification</h6>
                        <form method="POST" action="">
                            <?= csrf_field() ?>
                            <input type="hidden" name="broadcast" value="1">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <select name="audience" class="form-select">
                                        <option value="all">All Users</option>
                                        <option value="customers">Customers Only</option>
                                        <option value="providers">Providers Only</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" name="title" class="form-control" placeholder="Notification title" required>
                                </div>
                                <div class="col-12">
                                    <textarea name="message" class="form-control" rows="2" placeholder="Message..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-megaphone"></i> Send Broadcast</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <h6 class="fw-bold mb-3">My Notifications</h6>
                <?php if (empty($notifications)): ?>
                    <div class="alert alert-light border text-center py-5"><p class="mb-0">No notifications yet.</p></div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <a href="?read=<?= (int) $n['id'] ?>" class="list-group-item list-group-item-action <?= $n['status'] === 'unread' ? 'bg-primary-subtle' : '' ?>">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1 fw-semibold"><?= e($n['title']) ?></h6>
                                        <small class="text-secondary"><?= format_date($n['created_at']) ?></small>
                                    </div>
                                    <p class="mb-0 small text-secondary"><?= e($n['message']) ?></p>
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
