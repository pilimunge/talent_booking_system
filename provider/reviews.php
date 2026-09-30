<?php
/**
 * provider/reviews.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$stmt = $pdo->prepare(
    'SELECT r.*, u.fullname as customer_name, u.profile_image as customer_image, b.event_name
     FROM reviews r
     JOIN users u ON u.id = r.customer_id
     JOIN bookings b ON b.id = r.booking_id
     WHERE r.provider_id = :pid
     ORDER BY r.created_at DESC'
);
$stmt->execute(['pid' => $providerProfile['id']]);
$reviews = $stmt->fetchAll();

$current_page = 'reviews';
$page_title = 'My Reviews';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold mb-0">My Reviews</h3>
                    <div class="text-end">
                        <div><?= render_stars((float) $providerProfile['average_rating']) ?></div>
                        <p class="small text-secondary mb-0"><?= number_format((float) $providerProfile['average_rating'], 1) ?> average (<?= count($reviews) ?> reviews)</p>
                    </div>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="alert alert-light border text-center py-5">
                        <p class="mb-0">No reviews yet.</p>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="list-group list-group-flush">
                            <?php foreach ($reviews as $r): ?>
                                <div class="list-group-item p-4">
                                    <div class="d-flex gap-3">
                                        <img src="<?= $r['customer_image'] ? e(UPLOAD_PROFILE_URL . $r['customer_image']) : 'https://placehold.co/45x45?text=' . urlencode(substr($r['customer_name'], 0, 1)) ?>" class="avatar-sm" style="width:45px;height:45px;" alt="">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <p class="fw-semibold mb-0"><?= e($r['customer_name']) ?></p>
                                                <small class="text-secondary"><?= format_date($r['created_at']) ?></small>
                                            </div>
                                            <p class="small text-secondary mb-1">for "<?= e($r['event_name']) ?>"</p>
                                            <div class="mb-2"><?= render_stars((float) $r['rating']) ?></div>
                                            <?php if ($r['review']): ?>
                                                <p class="mb-0"><?= nl2br(e($r['review'])) ?></p>
                                            <?php endif; ?>
                                        </div>
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
