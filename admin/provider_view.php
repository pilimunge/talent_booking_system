<?php
/**
 * admin/provider_view.php
 * Full detail view of a single provider for admin review.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

$providerId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, u.fullname, u.email, u.phone, u.profile_image, u.status as user_status, c.category_name
     FROM providers p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.id = :id LIMIT 1'
);
$stmt->execute(['id' => $providerId]);
$provider = $stmt->fetch();

if (!$provider) {
    set_flash('error', 'Provider not found.');
    redirect('admin/providers.php');
}

$galleryStmt = $pdo->prepare('SELECT * FROM gallery WHERE provider_id = :id ORDER BY created_at DESC');
$galleryStmt->execute(['id' => $providerId]);
$gallery = $galleryStmt->fetchAll();

$bookingsStmt = $pdo->prepare(
    'SELECT b.*, u.fullname as customer_name FROM bookings b
     JOIN users u ON u.id = b.customer_id
     WHERE b.provider_id = :id ORDER BY b.created_at DESC LIMIT 10'
);
$bookingsStmt->execute(['id' => $providerId]);
$bookings = $bookingsStmt->fetchAll();

$reviewsStmt = $pdo->prepare(
    'SELECT r.*, u.fullname as customer_name FROM reviews r
     JOIN users u ON u.id = r.customer_id
     WHERE r.provider_id = :id ORDER BY r.created_at DESC LIMIT 10'
);
$reviewsStmt->execute(['id' => $providerId]);
$reviews = $reviewsStmt->fetchAll();

$current_page = 'providers';
$page_title = $provider['fullname'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <a href="<?= e(APP_URL) ?>/admin/providers.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to providers</a>

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex gap-3">
                                <img src="<?= $provider['profile_image'] ? e(UPLOAD_PROFILE_URL . $provider['profile_image']) : 'https://placehold.co/80x80?text=' . urlencode(substr($provider['fullname'], 0, 1)) ?>" class="avatar-sm" style="width:80px;height:80px;" alt="">
                                <div>
                                    <h4 class="fw-bold mb-1"><?= e($provider['fullname']) ?></h4>
                                    <p class="mb-1 text-secondary"><?= e($provider['category_name']) ?> &middot; <?= e($provider['location'] ?: 'No location') ?></p>
                                    <div><?= render_stars((float) $provider['average_rating']) ?> <span class="small text-secondary">(<?= number_format((float) $provider['average_rating'], 1) ?>)</span></div>
                                </div>
                            </div>
                            <span class="badge <?= approval_status_badge($provider['approval_status']) ?> fs-6"><?= ucfirst(e($provider['approval_status'])) ?></span>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-4"><p class="small text-secondary mb-0">Email</p><p class="fw-semibold"><?= e($provider['email']) ?></p></div>
                            <div class="col-md-4"><p class="small text-secondary mb-0">Phone</p><p class="fw-semibold"><?= e($provider['phone'] ?: '-') ?></p></div>
                            <div class="col-md-4"><p class="small text-secondary mb-0">Experience</p><p class="fw-semibold"><?= (int) $provider['experience_years'] ?> years</p></div>
                            <div class="col-md-4"><p class="small text-secondary mb-0">Starting Price</p><p class="fw-semibold"><?= format_money($provider['price']) ?></p></div>
                            <div class="col-md-4"><p class="small text-secondary mb-0">Account Status</p><p class="fw-semibold"><?= ucfirst(e($provider['user_status'])) ?></p></div>
                            <div class="col-md-4"><p class="small text-secondary mb-0">Joined</p><p class="fw-semibold"><?= format_date($provider['created_at']) ?></p></div>
                        </div>

                        <?php if ($provider['bio']): ?>
                            <p class="small text-secondary mb-0 mt-2">Bio</p>
                            <p><?= nl2br(e($provider['bio'])) ?></p>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-3">
                            <?php if ($provider['approval_status'] === 'pending'): ?>
                                <form method="POST" action="<?= e(APP_URL) ?>/admin/providers.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success">Approve</button>
                                </form>
                                <form method="POST" action="<?= e(APP_URL) ?>/admin/providers.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-outline-danger">Reject</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($gallery)): ?>
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">Gallery (<?= count($gallery) ?>)</h6>
                            <div class="row g-2">
                                <?php foreach ($gallery as $img): ?>
                                    <div class="col-3 col-md-2"><img src="<?= e(UPLOAD_GALLERY_URL . $img['image_path']) ?>" class="gallery-thumb" alt=""></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mt-1">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Recent Bookings</h6></div>
                            <div class="card-body p-0">
                                <?php if (empty($bookings)): ?>
                                    <p class="text-secondary p-3 mb-0">No bookings yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($bookings as $b): ?>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><?= e($b['event_name']) ?> &middot; <?= e($b['customer_name']) ?></span>
                                                <span class="badge <?= booking_status_badge($b['booking_status']) ?>"><?= ucfirst($b['booking_status']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Recent Reviews</h6></div>
                            <div class="card-body p-0">
                                <?php if (empty($reviews)): ?>
                                    <p class="text-secondary p-3 mb-0">No reviews yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($reviews as $r): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <span class="fw-semibold"><?= e($r['customer_name']) ?></span>
                                                    <?= render_stars((float) $r['rating']) ?>
                                                </div>
                                                <?php if ($r['review']): ?><p class="small text-secondary mb-0"><?= e($r['review']) ?></p><?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
