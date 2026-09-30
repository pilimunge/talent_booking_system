<?php
/**
 * admin/reviews.php
 * View all reviews platform-wide; allow removal of inappropriate ones
 * (also recalculates the affected provider's average rating).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review_id'])) {
    verify_csrf();

    $reviewId = (int) $_POST['delete_review_id'];
    $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = :id');
    $stmt->execute(['id' => $reviewId]);
    $review = $stmt->fetch();

    if ($review) {
        $delete = $pdo->prepare('DELETE FROM reviews WHERE id = :id');
        $delete->execute(['id' => $reviewId]);

        // Recalculate provider's average rating
        $avgStmt = $pdo->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE provider_id = :pid');
        $avgStmt->execute(['pid' => $review['provider_id']]);
        $avgRating = (float) ($avgStmt->fetchColumn() ?: 0);

        $updateProvider = $pdo->prepare('UPDATE providers SET average_rating = :avg WHERE id = :pid');
        $updateProvider->execute(['avg' => round($avgRating, 2), 'pid' => $review['provider_id']]);

        log_activity($pdo, current_user_id(), "Deleted review #{$reviewId}.");
        set_flash('success', 'Review removed.');
    }
    redirect('admin/reviews.php');
}

$stmt = $pdo->query(
    'SELECT r.*, cu.fullname as customer_name, pu.fullname as provider_name, b.event_name
     FROM reviews r
     JOIN users cu ON cu.id = r.customer_id
     JOIN providers p ON p.id = r.provider_id
     JOIN users pu ON pu.id = p.user_id
     JOIN bookings b ON b.id = r.booking_id
     ORDER BY r.created_at DESC'
);
$reviews = $stmt->fetchAll();

$current_page = 'reviews';
$page_title = 'Manage Reviews';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Manage Reviews</h3>

                <?php if (empty($reviews)): ?>
                    <div class="alert alert-light border text-center py-5"><p class="mb-0">No reviews yet.</p></div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="list-group list-group-flush">
                            <?php foreach ($reviews as $r): ?>
                                <div class="list-group-item p-4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <p class="fw-semibold mb-0"><?= e($r['customer_name']) ?> &rarr; <?= e($r['provider_name']) ?></p>
                                            <p class="small text-secondary mb-1">for "<?= e($r['event_name']) ?>" &middot; <?= format_date($r['created_at']) ?></p>
                                            <div class="mb-2"><?= render_stars((float) $r['rating']) ?></div>
                                            <?php if ($r['review']): ?><p class="mb-0"><?= nl2br(e($r['review'])) ?></p><?php endif; ?>
                                        </div>
                                        <form method="POST" action="" data-confirm="Delete this review? This will also recalculate the provider's rating.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_review_id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
