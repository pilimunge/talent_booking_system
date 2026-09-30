<?php
/**
 * customer/view_provider.php
 * Public provider profile page. Anyone can view; only logged-in
 * customers see the "Book Now" button (guests are prompted to log in).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


attempt_remember_login($pdo);

$providerId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, u.fullname, u.profile_image, u.email, c.category_name
     FROM providers p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.id = :id AND p.approval_status = "approved"
     LIMIT 1'
);
$stmt->execute(['id' => $providerId]);
$provider = $stmt->fetch();

if (!$provider) {
    http_response_code(404);
    $page_title = 'Provider Not Found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="container py-5 text-center"><h3>Provider not found or not yet approved.</h3>
          <a href="' . e(APP_URL) . '/customer/search.php" class="btn btn-primary mt-3">Back to Search</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$galleryStmt = $pdo->prepare('SELECT * FROM gallery WHERE provider_id = :id ORDER BY created_at DESC');
$galleryStmt->execute(['id' => $providerId]);
$gallery = $galleryStmt->fetchAll();

$reviewsStmt = $pdo->prepare(
    'SELECT r.*, u.fullname as customer_name, u.profile_image as customer_image
     FROM reviews r
     JOIN users u ON u.id = r.customer_id
     WHERE r.provider_id = :id
     ORDER BY r.created_at DESC'
);
$reviewsStmt->execute(['id' => $providerId]);
$reviews = $reviewsStmt->fetchAll();

$videoLinks = array_filter(array_map('trim', explode(',', $provider['video_links'] ?? '')));

$page_title = $provider['fullname'];
require_once __DIR__ . '/../includes/header.php';
?>

<img src="<?= $provider['cover_image'] ? e(UPLOAD_COVER_URL . $provider['cover_image']) : 'https://placehold.co/1200x300?text=' . urlencode($provider['fullname']) ?>" class="cover-banner" alt="Cover">

<div class="container">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex align-items-center gap-3 mt-n5 mb-3 position-relative">
                <img src="<?= $provider['profile_image'] ? e(UPLOAD_PROFILE_URL . $provider['profile_image']) : 'https://placehold.co/120x120?text=' . urlencode(substr($provider['fullname'], 0, 1)) ?>" class="avatar-lg" alt="<?= e($provider['fullname']) ?>">
                <div class="pt-4">
                    <h3 class="fw-bold mb-0"><?= e($provider['fullname']) ?></h3>
                    <span class="badge bg-primary-subtle text-primary"><?= e($provider['category_name']) ?></span>
                </div>
            </div>

            <div class="d-flex gap-4 flex-wrap mb-4 small text-secondary">
                <span><i class="bi bi-geo-alt"></i> <?= e($provider['location'] ?: 'Not specified') ?></span>
                <span><i class="bi bi-briefcase"></i> <?= (int) $provider['experience_years'] ?> years experience</span>
                <span><?= render_stars((float) $provider['average_rating']) ?> (<?= number_format((float) $provider['average_rating'], 1) ?>)</span>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="fw-bold">About</h5>
                    <p class="text-secondary"><?= nl2br(e($provider['bio'] ?: 'No bio provided yet.')) ?></p>

                    <?php if ($provider['skills']): ?>
                        <h6 class="fw-bold mt-3">Skills</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (array_filter(array_map('trim', explode(',', $provider['skills']))) as $skill): ?>
                                <span class="badge bg-light text-dark border"><?= e($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($provider['service_description']): ?>
                        <h6 class="fw-bold mt-3">Service Description</h6>
                        <p class="text-secondary mb-0"><?= nl2br(e($provider['service_description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($gallery)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Gallery</h5>
                        <div class="row g-2">
                            <?php foreach ($gallery as $img): ?>
                                <div class="col-4 col-md-3">
                                    <img src="<?= e(UPLOAD_GALLERY_URL . $img['image_path']) ?>" class="gallery-thumb" alt="Gallery image">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($videoLinks)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Videos</h5>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($videoLinks as $link): ?>
                                <li class="mb-1"><i class="bi bi-play-circle text-primary"></i> <a href="<?= e($link) ?>" target="_blank" rel="noopener">Watch video <i class="bi bi-box-arrow-up-right small"></i></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Reviews (<?= count($reviews) ?>)</h5>
                    <?php if (empty($reviews)): ?>
                        <p class="text-secondary small">No reviews yet.</p>
                    <?php endif; ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                            <img src="<?= $review['customer_image'] ? e(UPLOAD_PROFILE_URL . $review['customer_image']) : 'https://placehold.co/40x40?text=' . urlencode(substr($review['customer_name'], 0, 1)) ?>" class="avatar-sm" alt="">
                            <div>
                                <p class="fw-semibold mb-0"><?= e($review['customer_name']) ?></p>
                                <div class="small mb-1"><?= render_stars((float) $review['rating']) ?></div>
                                <p class="text-secondary mb-0 small"><?= nl2br(e($review['review'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 80px;">
                <div class="card-body text-center p-4">
                    <p class="text-secondary mb-1">Starting from</p>
                    <h3 class="fw-bold text-primary"><?= format_money($provider['price']) ?></h3>

                    <?php if (is_logged_in() && current_user_role() === 'customer'): ?>
                        <a href="<?= e(APP_URL) ?>/customer/book_provider.php?id=<?= (int) $provider['id'] ?>" class="btn btn-primary w-100 py-2 fw-semibold mt-3">
                            <i class="bi bi-calendar-check"></i> Book Now
                        </a>
                    <?php elseif (!is_logged_in()): ?>
                        <a href="<?= e(APP_URL) ?>/auth/login.php" class="btn btn-primary w-100 py-2 fw-semibold mt-3">
                            Log In to Book
                        </a>
                    <?php else: ?>
                        <p class="small text-secondary mt-3 mb-0">Only customer accounts can make bookings.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="mb-5"></div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
