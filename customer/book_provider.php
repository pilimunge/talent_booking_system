<?php
/**
 * customer/book_provider.php
 * Booking request form. Creates a booking with status "pending" and
 * notifies the provider.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';


require_role('customer');

$providerId = (int) ($_GET['id'] ?? $_POST['provider_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, u.fullname FROM providers p JOIN users u ON u.id = p.user_id
     WHERE p.id = :id AND p.approval_status = "approved" LIMIT 1'
);
$stmt->execute(['id' => $providerId]);
$provider = $stmt->fetch();

if (!$provider) {
    set_flash('error', 'This provider is not available for booking.');
    redirect('customer/search.php');
}

$errors = [];
$old = ['event_name' => '', 'event_type' => '', 'event_date' => '', 'event_location' => '', 'event_description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    if ($old['event_name'] === '') $errors[] = 'Event name is required.';
    if ($old['event_type'] === '') $errors[] = 'Event type is required.';
    if ($old['event_location'] === '') $errors[] = 'Event location is required.';

    $eventDate = $old['event_date'];
    if ($eventDate === '' || !strtotime($eventDate)) {
        $errors[] = 'Please select a valid event date.';
    } elseif (strtotime($eventDate) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Event date cannot be in the past.';
    }

    if (empty($errors)) {
        $insert = $pdo->prepare(
            'INSERT INTO bookings (customer_id, provider_id, event_name, event_type, event_date, event_location, event_description, booking_status)
             VALUES (:customer_id, :provider_id, :event_name, :event_type, :event_date, :event_location, :event_description, "pending")'
        );
        $insert->execute([
            'customer_id' => current_user_id(),
            'provider_id' => $providerId,
            'event_name' => $old['event_name'],
            'event_type' => $old['event_type'],
            'event_date' => $eventDate,
            'event_location' => $old['event_location'],
            'event_description' => $old['event_description'] ?: null,
        ]);

        $bookingId = (int) $pdo->lastInsertId();

    push_notification(
    $pdo,
    $providerId,
    'New Booking Request',
    'Customer sent a booking request.',
    'customer'
);

        log_activity($pdo, current_user_id(), 'Sent booking request to provider #' . $providerId);

        set_flash('success', 'Your booking request has been sent! You will be notified once the provider responds.');
        redirect('customer/booking_view.php?id=' . $bookingId);
    }
}

$current_page = 'search';
$page_title = 'Book ' . $provider['fullname'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <a href="<?= e(APP_URL) ?>/customer/view_provider.php?id=<?= (int) $providerId ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to profile</a>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img src="<?= $provider['profile_image'] ?? false ? e(UPLOAD_PROFILE_URL . $provider['profile_image']) : 'https://placehold.co/60x60?text=' . urlencode(substr($provider['fullname'], 0, 1)) ?>" class="avatar-sm" style="width:60px;height:60px;" alt="">
                        <div>
                            <h5 class="mb-0 fw-bold">Book <?= e($provider['fullname']) ?></h5>
                            <p class="mb-0 text-secondary small">Starting from <?= format_money($provider['price']) ?></p>
                        </div>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="provider_id" value="<?= (int) $providerId ?>">

                        <div class="mb-3">
                            <label class="form-label">Event Name</label>
                            <input type="text" name="event_name" class="form-control" value="<?= e($old['event_name']) ?>" required placeholder="e.g. Amina's Wedding Reception">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Event Type</label>
                                <select name="event_type" class="form-select" required>
                                    <option value="">Select type</option>
                                    <?php foreach (['Wedding', 'Birthday', 'Corporate', 'Graduation', 'Anniversary', 'Product Launch', 'Other'] as $type): ?>
                                        <option value="<?= e($type) ?>" <?= $old['event_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Event Date</label>
                                <input type="date" name="event_date" class="form-control" value="<?= e($old['event_date']) ?>" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Event Location</label>
                            <input type="text" name="event_location" class="form-control" value="<?= e($old['event_location']) ?>" required placeholder="Venue / city">
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Event Description <span class="text-secondary">(optional)</span></label>
                            <textarea name="event_description" class="form-control" rows="4" placeholder="Any details the provider should know..."><?= e($old['event_description']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="bi bi-send"></i> Send Booking Request
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
