<?php
/**
 * provider/booking_view.php
 * Shows booking details and lets the provider accept/reject (if pending)
 * or mark as completed (if ongoing, i.e. paid).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$bookingId = (int) ($_GET['id'] ?? $_POST['booking_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT b.*, 
            u.fullname as customer_name, 
            u.phone as customer_phone, 
            u.email as customer_email
     FROM bookings b
     JOIN users u ON u.id = b.customer_id
     WHERE b.id = :id 
       AND b.provider_id = :pid
     LIMIT 1'
);

$stmt->execute([
    'id'  => $bookingId,
    'pid' => $providerProfile['id']
]);

$booking = $stmt->fetch();

if (!$booking) {
    set_flash('error', 'Booking not found.');
    redirect('provider/bookings.php');
}


/*
|--------------------------------------------------------------------------
| PROVIDER ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    verify_csrf();

    $action = $_POST['action'];


    /*
    |--------------------------------------------------------------------------
    | ACCEPT BOOKING
    |--------------------------------------------------------------------------
    */
    if (
        $action === 'accept' &&
        $booking['booking_status'] === 'pending'
    ) {

        $update = $pdo->prepare(
            'UPDATE bookings
             SET booking_status = "accepted"
             WHERE id = :id
               AND provider_id = :pid
               AND booking_status = "pending"'
        );

        $update->execute([
            'id'  => $bookingId,
            'pid' => $providerProfile['id']
        ]);

        if ($update->rowCount() > 0) {

            /*
             * Audit log
             * This records the real provider action.
             */
            audit_log(
                $pdo,
                'Accepted Booking',
                'Accepted booking "' . $booking['event_name'] . '" from ' . $booking['customer_name'] . '.',
                'booking',
                $bookingId
            );

            push_notification(
                $pdo,
                (int) $booking['customer_id'],
                'Booking Accepted',
                current_user_name() .
                ' accepted your booking request for "' .
                $booking['event_name'] .
                '". You can now proceed with payment.',
                'provider'
            );

            set_flash('success', 'Booking accepted.');

        } else {

            set_flash(
                'error',
                'The booking could not be accepted. It may have already been updated.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | REJECT BOOKING
    |--------------------------------------------------------------------------
    */
    elseif (
        $action === 'reject' &&
        $booking['booking_status'] === 'pending'
    ) {

        $update = $pdo->prepare(
            'UPDATE bookings
             SET booking_status = "rejected"
             WHERE id = :id
               AND provider_id = :pid
               AND booking_status = "pending"'
        );

        $update->execute([
            'id'  => $bookingId,
            'pid' => $providerProfile['id']
        ]);

        if ($update->rowCount() > 0) {

            /*
             * Audit log
             */
            audit_log(
                $pdo,
                'Rejected Booking',
                'Rejected booking "' . $booking['event_name'] . '" from ' . $booking['customer_name'] . '.',
                'booking',
                $bookingId
            );

            push_notification(
                $pdo,
                (int) $booking['customer_id'],
                'Booking Rejected',
                'Your booking request has been rejected.',
                'provider'
            );

            set_flash('success', 'Booking rejected.');

        } else {

            set_flash(
                'error',
                'The booking could not be rejected. It may have already been updated.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMPLETE BOOKING / SERVICE
    |--------------------------------------------------------------------------
    */
    elseif (
        $action === 'complete' &&
        $booking['booking_status'] === 'ongoing'
    ) {

        $update = $pdo->prepare(
            'UPDATE bookings
             SET provider_completed = 1
             WHERE id = :id
               AND provider_id = :pid
               AND booking_status = "ongoing"
               AND provider_completed = 0'
        );

        $update->execute([
            'id'  => $bookingId,
            'pid' => $providerProfile['id']
        ]);

        if ($update->rowCount() > 0) {

            /*
             * Audit log
             */
            audit_log(
                $pdo,
                'Completed Service',
                'Marked booking "' . $booking['event_name'] . '" as completed for ' . $booking['customer_name'] . '.',
                'booking',
                $bookingId
            );

            push_notification(
                $pdo,
                (int) $booking['customer_id'],
                'Completion Confirmation Required',
                'The provider has marked this booking as completed. Please confirm completion.',
                'provider'
            );

            set_flash(
                'success',
                'Waiting for customer confirmation.'
            );

        } else {

            set_flash(
                'error',
                'The booking could not be marked as completed. It may already be completed.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INVALID ACTION
    |--------------------------------------------------------------------------
    */
    else {

        set_flash(
            'error',
            'This action is not available for the current booking status.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */
    redirect(
        'provider/booking_view.php?id=' . $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/
$paymentStmt = $pdo->prepare(
    'SELECT *
     FROM payments
     WHERE booking_id = :id
     LIMIT 1'
);

$paymentStmt->execute([
    'id' => $bookingId
]);

$payment = $paymentStmt->fetch();


$current_page = 'bookings';
$page_title = 'Booking: ' . $booking['event_name'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid" style="max-width: 800px;">

                <a href="<?= e(APP_URL) ?>/provider/bookings.php"
                   class="small text-decoration-none">

                    <i class="bi bi-arrow-left"></i>
                    Back to bookings

                </a>


                <div class="card border-0 shadow-sm mt-3">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-start mb-3">

                            <div>

                                <h4 class="fw-bold mb-1">
                                    <?= e($booking['event_name']) ?>
                                </h4>

                                <p class="text-secondary mb-0">
                                    <?= e($booking['event_type']) ?>
                                </p>

                            </div>


                            <span class="badge <?= booking_status_badge($booking['booking_status']) ?> fs-6">
                                <?= ucfirst(e($booking['booking_status'])) ?>
                            </span>

                        </div>


                        <hr>


                        <div class="row g-3 mb-3">

                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Customer
                                </p>

                                <p class="fw-semibold">
                                    <?= e($booking['customer_name']) ?>
                                </p>

                            </div>


                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Customer Phone
                                </p>

                                <p class="fw-semibold">
                                    <?= e($booking['customer_phone'] ?: 'Not provided') ?>
                                </p>

                            </div>


                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Event Date
                                </p>

                                <p class="fw-semibold">
                                    <?= format_date($booking['event_date']) ?>
                                </p>

                            </div>


                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Location
                                </p>

                                <p class="fw-semibold">
                                    <?= e($booking['event_location']) ?>
                                </p>

                            </div>

                        </div>


                        <?php if ($booking['event_description']): ?>

                            <p class="small text-secondary mb-0">
                                Description
                            </p>

                            <p>
                                <?= nl2br(e($booking['event_description'])) ?>
                            </p>

                        <?php endif; ?>


                        <?php if ($payment): ?>

                            <hr>

                            <p class="small text-secondary mb-0">
                                Payment Status
                            </p>

                            <span class="badge <?= payment_status_badge($payment['payment_status']) ?>">
                                <?= ucfirst(e($payment['payment_status'])) ?>
                            </span>

                            <span class="ms-2 fw-semibold">
                                <?= format_money($payment['amount']) ?>
                            </span>

                        <?php endif; ?>


                        <a href="<?= e(APP_URL) ?>/provider/chat.php?booking_id=<?= (int) $bookingId ?>"
                           class="btn btn-outline-secondary w-100 mb-3">
                            <i class="bi bi-chat-dots"></i>
                            Chat with <?= e($booking['customer_name']) ?>
                        </a>


                        <?php if ($booking['booking_status'] === 'pending'): ?>

                            <div class="d-flex gap-2 mt-4">

                                <!-- ACCEPT -->
                                <form method="POST"
                                      action=""
                                      class="flex-fill"
                                      data-confirm="Accept this booking request?">

                                    <?= csrf_field() ?>

                                    <input type="hidden"
                                           name="action"
                                           value="accept">

                                    <input type="hidden"
                                           name="booking_id"
                                           value="<?= (int) $bookingId ?>">

                                    <button type="submit"
                                            class="btn btn-success w-100">

                                        <i class="bi bi-check-lg"></i>
                                        Accept

                                    </button>

                                </form>


                                <!-- REJECT -->
                                <form method="POST"
                                      action=""
                                      class="flex-fill"
                                      data-confirm="Reject this booking request?">

                                    <?= csrf_field() ?>

                                    <input type="hidden"
                                           name="action"
                                           value="reject">

                                    <input type="hidden"
                                           name="booking_id"
                                           value="<?= (int) $bookingId ?>">

                                    <button type="submit"
                                            class="btn btn-outline-danger w-100">

                                        <i class="bi bi-x-lg"></i>
                                        Reject

                                    </button>

                                </form>

                            </div>


                        <?php elseif ($booking['booking_status'] === 'ongoing'): ?>

                            <a href="<?= e(APP_URL) ?>/provider/track_location.php?id=<?= (int) $bookingId ?>"
                               class="btn btn-outline-primary w-100 mb-2">

                                <i class="bi bi-geo-alt"></i>
                                Track Customer Location

                            </a>

                            <form method="POST"
                                  action=""
                                  class="mt-2"
                                  data-confirm="Mark this event as completed?">

                                <?= csrf_field() ?>

                                <input type="hidden"
                                       name="action"
                                       value="complete">

                                <input type="hidden"
                                       name="booking_id"
                                       value="<?= (int) $bookingId ?>">

                                <button type="submit"
                                        class="btn btn-primary w-100">

                                    <i class="bi bi-check-circle"></i>
                                    Mark Event as Completed

                                </button>

                            </form>


                        <?php elseif (
                            $booking['booking_status'] === 'accepted' &&
                            !$payment
                        ): ?>

                            <div class="alert alert-info mt-4 mb-0 small">

                                <i class="bi bi-info-circle"></i>

                                Waiting for the customer to complete payment.

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
