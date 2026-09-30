<?php
/**
 * customer/review.php
 * Only customers with a completed booking (and no existing review) may submit.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT b.*, u.fullname as provider_name
     FROM bookings b
     JOIN providers p ON p.id = b.provider_id
     JOIN users u ON u.id = p.user_id
     WHERE b.id = :id AND b.customer_id = :cid
     LIMIT 1'
);
$stmt->execute(['id' => $bookingId, 'cid' => current_user_id()]);
$booking = $stmt->fetch();

if (!$booking || $booking['booking_status'] !== 'completed') {
    set_flash('error', 'You can only review completed bookings.');
    redirect('customer/bookings.php');
}

$reviewCheck = $pdo->prepare('SELECT id FROM reviews WHERE booking_id = :id');
$reviewCheck->execute(['id' => $bookingId]);
if ($reviewCheck->fetch()) {
    set_flash('error', 'You have already reviewed this booking.');
    redirect('customer/booking_view.php?id=' . $bookingId);
}

$errors = [];
$old = ['rating' => '5', 'review' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['rating'] = $_POST['rating'] ?? '';
    $old['review'] = trim($_POST['review'] ?? '');

    if (!in_array($old['rating'], ['1', '2', '3', '4', '5'], true)) {
        $errors[] = 'Please select a rating between 1 and 5 stars.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT INTO reviews (booking_id, customer_id, provider_id, rating, review)
                 VALUES (:booking_id, :customer_id, :provider_id, :rating, :review)'
            );
            $insert->execute([
                'booking_id' => $bookingId,
                'customer_id' => current_user_id(),
                'provider_id' => $booking['provider_id'],
                'rating' => $old['rating'],
                'review' => $old['review'] ?: null,
            ]);

            $reviewId = (int) $pdo->lastInsertId();

            // Recalculate the provider's average rating
            $avgStmt = $pdo->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE provider_id = :pid');
            $avgStmt->execute(['pid' => $booking['provider_id']]);
            $avgRating = (float) $avgStmt->fetchColumn();

            $updateProvider = $pdo->prepare('UPDATE providers SET average_rating = :avg WHERE id = :pid');
            $updateProvider->execute(['avg' => round($avgRating, 2), 'pid' => $booking['provider_id']]);

            $pdo->commit();

            $providerUserStmt = $pdo->prepare('SELECT user_id FROM providers WHERE id = :id');
            $providerUserStmt->execute(['id' => $booking['provider_id']]);
            $providerUserId = $providerUserStmt->fetchColumn();

            if ($providerUserId) {
                push_notification(
                    $pdo,
                    (int) $providerUserId,
                    'New Review Received',
                    current_user_name() . ' left you a ' . $old['rating'] . '-star review for "' . $booking['event_name'] . '".'
                );
            }

            set_flash('success', 'Thank you for your review!');
            audit_log(
    $pdo,
    'Submitted Review',
    'Left a ' . $old['rating'] . '-star review for booking "' . $booking['event_name'] . '".',
    'review',
    $reviewId
);
            redirect('customer/booking_view.php?id=' . $bookingId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong submitting your review. Please try again.';
        }
    }
}

$current_page = 'bookings';
$page_title = 'Leave a Review';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <a href="<?= e(APP_URL) ?>/customer/booking_view.php?id=<?= (int) $bookingId ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to booking</a>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">Rate Your Experience</h5>
                    <p class="text-secondary small mb-4">How was your event with <?= e($booking['provider_name']) ?>?</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="booking_id" value="<?= (int) $bookingId ?>">

                        <div class="mb-4 text-center">
                            <label class="form-label d-block">Your Rating</label>
                            <div class="rating-input" data-input="#ratingValue">
                                <i class="bi bi-star-fill" data-value="1"></i>
                                <i class="bi bi-star-fill" data-value="2"></i>
                                <i class="bi bi-star-fill" data-value="3"></i>
                                <i class="bi bi-star-fill" data-value="4"></i>
                                <i class="bi bi-star-fill" data-value="5"></i>
                            </div>
                            <input type="hidden" id="ratingValue" name="rating" value="<?= e($old['rating']) ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Your Review <span class="text-secondary">(optional)</span></label>
                            <textarea name="review" class="form-control" rows="4" placeholder="Share details about your experience..."><?= e($old['review']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-check-circle"></i> Submit Review
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
