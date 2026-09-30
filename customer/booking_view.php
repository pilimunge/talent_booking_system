<?php
/**
 * customer/booking_view.php
 *
 * Shows full booking details plus:
 * - Payment option when booking is accepted and unpaid
 * - Confirm Completion when provider has completed the booking
 * - Automatically creates admin commission when customer confirms completion
 * - Review option after customer confirms completion
 * - Payment details and receipt
 * - Existing review
 * - Audit logging for customer actions
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$bookingId = (int) ($_GET['id'] ?? 0);

if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking ID.');
    redirect('customer/bookings.php');
}

/*
|--------------------------------------------------------------------------
| Get Booking
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT
        b.*,
        u.fullname AS provider_name,
        u.phone AS provider_phone,
        p.id AS provider_profile_id,
        p.price
     FROM bookings b
     JOIN providers p ON p.id = b.provider_id
     JOIN users u ON u.id = p.user_id
     WHERE b.id = :id
       AND b.customer_id = :cid
     LIMIT 1'
);

$stmt->execute([
    'id'  => $bookingId,
    'cid' => current_user_id()
]);

$booking = $stmt->fetch();

if (!$booking) {
    set_flash('error', 'Booking not found.');
    redirect('customer/bookings.php');
}


/*
|--------------------------------------------------------------------------
| Confirm Completion + Create Commission
|--------------------------------------------------------------------------
|
| Provider must first mark the booking as completed.
| Customer then confirms completion.
|
| At the same time:
| 1. Booking becomes completed.
| 2. Paid payment is found.
| 3. Commission rate is read from settings.
| 4. Admin commission is calculated.
| 5. Provider amount is calculated.
| 6. Commission is saved as "earned".
| 7. Customer action is recorded in audit logs.
|
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action'])
) {

    verify_csrf();

    $action = $_POST['action'];

    /*
    |--------------------------------------------------------------------------
    | Customer confirms completion
    |--------------------------------------------------------------------------
    */
    if (
        $action === 'confirm_completion' &&
        (int) $booking['provider_completed'] === 1 &&
        (int) $booking['customer_completed'] === 0
    ) {

        $pdo->beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | 1. Confirm the booking
            |--------------------------------------------------------------------------
            */
            $update = $pdo->prepare(
                "UPDATE bookings
                 SET
                    customer_completed = 1,
                    booking_status = 'completed'
                 WHERE id = :id
                   AND customer_id = :customer_id
                   AND provider_completed = 1
                   AND customer_completed = 0"
            );

            $update->execute([
                'id'          => $bookingId,
                'customer_id' => current_user_id()
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException(
                    'Booking completion could not be confirmed.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | 2. Get the paid payment
            |--------------------------------------------------------------------------
            |
            | We use the actual paid amount as the source for commission.
            |
            */
            $paymentStmt = $pdo->prepare(
                "SELECT id, amount, payment_status
                 FROM payments
                 WHERE booking_id = :booking_id
                   AND payment_status = 'paid'
                 ORDER BY id DESC
                 LIMIT 1"
            );

            $paymentStmt->execute([
                'booking_id' => $bookingId
            ]);

            $paidPayment = $paymentStmt->fetch();

            if (!$paidPayment) {
                throw new RuntimeException(
                    'No paid payment was found for this booking.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | 3. Check whether commission already exists
            |--------------------------------------------------------------------------
            |
            | booking_id is UNIQUE in commissions, but we also check first
            | to prevent duplicate commission records.
            |
            */
            $commissionCheck = $pdo->prepare(
                'SELECT id
                 FROM commissions
                 WHERE booking_id = :booking_id
                 LIMIT 1'
            );

            $commissionCheck->execute([
                'booking_id' => $bookingId
            ]);

            $existingCommission = $commissionCheck->fetch();


            /*
            |--------------------------------------------------------------------------
            | 4. Create commission if it does not already exist
            |--------------------------------------------------------------------------
            */
            if (!$existingCommission) {

                /*
                |--------------------------------------------------------------------------
                | Get commission rate from settings
                |--------------------------------------------------------------------------
                */
                $settingsStmt = $pdo->query(
                    'SELECT commission_rate
                     FROM settings
                     ORDER BY id ASC
                     LIMIT 1'
                );

                $settings = $settingsStmt->fetch();

                /*
                 * If the setting cannot be found, use 10%.
                 */
                $commissionRate = $settings
                    ? (float) $settings['commission_rate']
                    : 10.00;


                /*
                |--------------------------------------------------------------------------
                | Validate commission rate
                |--------------------------------------------------------------------------
                */
                if ($commissionRate < 0 || $commissionRate > 100) {
                    throw new RuntimeException(
                        'Invalid commission rate configured in settings.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Calculate amounts
                |--------------------------------------------------------------------------
                */
                $paymentAmount = (float) $paidPayment['amount'];

                $commissionAmount = round(
                    ($paymentAmount * $commissionRate) / 100,
                    2
                );

                $providerAmount = round(
                    $paymentAmount - $commissionAmount,
                    2
                );


                /*
                |--------------------------------------------------------------------------
                | 5. Insert commission
                |--------------------------------------------------------------------------
                |
                | Status "earned" means the system has officially recorded
                | the commission as earned by the administrator.
                |
                */
                $commissionInsert = $pdo->prepare(
                    "INSERT INTO commissions (
                        booking_id,
                        payment_id,
                        provider_id,
                        payment_amount,
                        commission_rate,
                        commission_amount,
                        provider_amount,
                        commission_status
                    )
                    VALUES (
                        :booking_id,
                        :payment_id,
                        :provider_id,
                        :payment_amount,
                        :commission_rate,
                        :commission_amount,
                        :provider_amount,
                        'earned'
                    )"
                );

                $commissionInsert->execute([
                    'booking_id'        => $bookingId,
                    'payment_id'        => (int) $paidPayment['id'],
                    'provider_id'       => (int) $booking['provider_id'],
                    'payment_amount'    => $paymentAmount,
                    'commission_rate'   => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'provider_amount'   => $providerAmount
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | 6. Notify Provider
            |--------------------------------------------------------------------------
            */
            push_notification(
                $pdo,
                (int) $booking['provider_id'],
                'Booking Completed',
                'The customer has confirmed completion of the booking.',
                'customer'
            );


            /*
            |--------------------------------------------------------------------------
            | 7. Audit Log
            |--------------------------------------------------------------------------
            |
            | Action itself explains what the customer did.
            | There is NO need for a separate description column.
            |--------------------------------------------------------------------------
            */
            audit_log(
                $pdo,
                'Confirmed Booking Completion',
                'Confirmed completion of booking "' . $booking['event_name'] . '"; commission recorded.',
                'booking',
                $bookingId
            );


            /*
            |--------------------------------------------------------------------------
            | 8. Existing Activity Log
            |--------------------------------------------------------------------------
            */
            log_activity(
                $pdo,
                current_user_id(),
                'Confirmed completion of booking #' . $bookingId
            );


            /*
            |--------------------------------------------------------------------------
            | 9. Commit everything
            |--------------------------------------------------------------------------
            */
            $pdo->commit();


            set_flash(
                'success',
                'Booking completed successfully. Commission has been recorded. You can now leave a review.'
            );

            redirect(
                'customer/booking_view.php?id=' . $bookingId
            );

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback if something failed
            |--------------------------------------------------------------------------
            */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
            |--------------------------------------------------------------------------
            | Log technical error
            |--------------------------------------------------------------------------
            */
            error_log(
                'Completion/commission error for booking #' .
                $bookingId .
                ': ' .
                $e->getMessage()
            );

            set_flash(
                'error',
                'Something went wrong while completing this booking. Please try again.'
            );

            redirect(
                'customer/booking_view.php?id=' . $bookingId
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Get Payment
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


/*
|--------------------------------------------------------------------------
| Get Review
|--------------------------------------------------------------------------
*/
$reviewStmt = $pdo->prepare(
    'SELECT *
     FROM reviews
     WHERE booking_id = :id
     LIMIT 1'
);

$reviewStmt->execute([
    'id' => $bookingId
]);

$review = $reviewStmt->fetch();


/*
|--------------------------------------------------------------------------
| Page Logic
|--------------------------------------------------------------------------
*/

/*
 * Customer can pay when:
 * - booking is accepted
 * - no payment exists yet
 */
$canPay =
    $booking['booking_status'] === 'accepted'
    && !$payment;


/*
 * Customer can confirm completion when:
 * - provider has marked completed
 * - customer has NOT confirmed yet
 */
$canConfirmCompletion =
    (int) $booking['provider_completed'] === 1
    && (int) $booking['customer_completed'] === 0;


/*
 * Customer can review when:
 * - booking is completed
 * - customer has not reviewed yet
 */
$canReview =
    $booking['booking_status'] === 'completed'
    && !$review;


/*
 * Customer can share live location when the booking is ongoing.
 */
$canShareLocation = $booking['booking_status'] === 'ongoing';


$current_page = 'bookings';
$page_title = 'Booking: ' . $booking['event_name'];

require_once __DIR__ . '/../includes/header.php';
?>


<div class="container-fluid">

    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>


        <div class="col-lg-9 col-xl-10 py-4">

            <div
                class="container-fluid"
                style="max-width: 800px;"
            >

                <!-- Back Button -->
                <a
                    href="<?= e(APP_URL) ?>/customer/bookings.php"
                    class="small text-decoration-none"
                >
                    <i class="bi bi-arrow-left"></i>
                    Back to bookings
                </a>


                <!--
                |--------------------------------------------------------------------------
                | BOOKING DETAILS
                |--------------------------------------------------------------------------
                -->
                <div class="card border-0 shadow-sm mt-3">

                    <div class="card-body p-4">

                        <div
                            class="d-flex justify-content-between align-items-start mb-3"
                        >

                            <div>

                                <h4 class="fw-bold mb-1">
                                    <?= e($booking['event_name']) ?>
                                </h4>

                                <p class="text-secondary mb-0">
                                    <?= e($booking['event_type']) ?>
                                </p>

                            </div>


                            <span
                                class="badge <?= booking_status_badge($booking['booking_status']) ?> fs-6"
                            >
                                <?= ucfirst(e($booking['booking_status'])) ?>
                            </span>

                        </div>


                        <hr>


                        <!-- Booking Information -->
                        <div class="row g-3 mb-3">

                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Provider
                                </p>

                                <p class="fw-semibold">
                                    <?= e($booking['provider_name']) ?>
                                </p>

                            </div>


                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Provider Phone
                                </p>

                                <p class="fw-semibold">
                                    <?= e($booking['provider_phone'] ?? 'Not provided') ?>
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


                            <div class="col-md-6">

                                <p class="small text-secondary mb-0">
                                    Reference Price
                                </p>

                                <p class="fw-semibold">
                                    <?= format_money($booking['price']) ?>
                                </p>

                            </div>

                        </div>


                        <!-- Event Description -->
                        <?php if (!empty($booking['event_description'])): ?>

                            <p class="small text-secondary mb-0">
                                Description
                            </p>

                            <p>
                                <?= nl2br(e($booking['event_description'])) ?>
                            </p>

                        <?php endif; ?>


                        <!--
                        |--------------------------------------------------------------------------
                        | REJECTED BOOKING
                        |--------------------------------------------------------------------------
                        -->
                        <?php if ($booking['booking_status'] === 'rejected'): ?>

                            <div class="alert alert-danger mt-3 mb-0">

                                <i class="bi bi-x-circle"></i>

                                This booking request was declined
                                by the provider.

                            </div>

                        <?php endif; ?>


                        <!--
                        |--------------------------------------------------------------------------
                        | PROVIDER COMPLETED NOTICE
                        |--------------------------------------------------------------------------
                        -->
                        <?php if ($canConfirmCompletion): ?>

                            <div class="alert alert-info mt-3 mb-0">

                                <div class="d-flex align-items-start">

                                    <i
                                        class="bi bi-info-circle-fill fs-5 me-2"
                                    ></i>

                                    <div>

                                        <strong>
                                            Provider marked this booking
                                            as completed.
                                        </strong>

                                        <div class="small mt-1">
                                            Please confirm completion below
                                            after verifying that the service
                                            has been completed.
                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php endif; ?>


                        <!--
                        |--------------------------------------------------------------------------
                        | CUSTOMER COMPLETED
                        |--------------------------------------------------------------------------
                        -->
                        <?php if (
                            (int) $booking['customer_completed'] === 1
                        ): ?>

                            <div class="alert alert-success mt-3 mb-0">

                                <i class="bi bi-check-circle-fill"></i>

                                You have confirmed completion of this booking.

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | PAYMENT INFORMATION
                |--------------------------------------------------------------------------
                -->
                <?php if ($payment): ?>

                    <div class="card border-0 shadow-sm mt-3">

                        <div class="card-body p-4">

                            <div
                                class="d-flex justify-content-between align-items-center"
                            >

                                <h5 class="fw-bold mb-0">
                                    Payment
                                </h5>

                                <span
                                    class="badge <?= payment_status_badge($payment['payment_status']) ?>"
                                >
                                    <?= ucfirst(e($payment['payment_status'])) ?>
                                </span>

                            </div>


                            <hr>


                            <div class="row g-3">

                                <div class="col-md-4">

                                    <p class="small text-secondary mb-0">
                                        Amount
                                    </p>

                                    <p class="fw-semibold">
                                        <?= format_money($payment['amount']) ?>
                                    </p>

                                </div>


                                <div class="col-md-4">

                                    <p class="small text-secondary mb-0">
                                        Method
                                    </p>

                                    <p class="fw-semibold">

                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $payment['payment_method']
                                                )
                                            )
                                        ) ?>

                                    </p>

                                </div>


                                <div class="col-md-4">

                                    <p class="small text-secondary mb-0">
                                        Reference
                                    </p>

                                    <p class="fw-semibold">

                                        <?= e(
                                            $payment['transaction_reference']
                                        ) ?>

                                    </p>

                                </div>

                            </div>


                            <!-- Receipt -->
                            <?php if ($payment['payment_status'] === 'paid'): ?>

                                <a
                                    href="<?= e(APP_URL) ?>/customer/receipt.php?id=<?= (int) $booking['id'] ?>"
                                    class="btn btn-outline-primary mt-2"
                                    target="_blank"
                                >

                                    <i class="bi bi-download"></i>

                                    Download Receipt

                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>


                <!--
                |--------------------------------------------------------------------------
                | EXISTING REVIEW
                |--------------------------------------------------------------------------
                -->
                <?php if ($review): ?>

                    <div class="card border-0 shadow-sm mt-3">

                        <div class="card-body p-4">

                            <h5 class="fw-bold mb-2">
                                Your Review
                            </h5>


                            <div class="mb-2">

                                <?= render_stars(
                                    (float) $review['rating']
                                ) ?>

                            </div>


                            <p class="text-secondary mb-0">

                                <?= nl2br(
                                    e($review['review'])
                                ) ?>

                            </p>

                        </div>

                    </div>

                <?php endif; ?>


                <!--
                |--------------------------------------------------------------------------
                | ACTION BUTTONS
                |--------------------------------------------------------------------------
                -->
                <div class="d-flex gap-2 mt-3 flex-wrap">


                    <!-- PAYMENT BUTTON -->
                    <?php if ($canPay): ?>

                        <a
                            href="<?= e(APP_URL) ?>/customer/payment.php?booking_id=<?= (int) $booking['id'] ?>"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-credit-card"></i>

                            Proceed to Payment

                        </a>

                    <?php endif; ?>


                    <!-- CHAT BUTTON -->
                    <a
                        href="<?= e(APP_URL) ?>/customer/chat.php?booking_id=<?= (int) $booking['id'] ?>"
                        class="btn btn-outline-secondary"
                    >

                        <i class="bi bi-chat-dots"></i>

                        Chat with <?= e($booking['provider_name']) ?>

                    </a>


                    <!-- SHARE LOCATION BUTTON -->
                    <?php if ($canShareLocation): ?>

                        <a
                            href="<?= e(APP_URL) ?>/customer/location_share.php?booking_id=<?= (int) $booking['id'] ?>"
                            class="btn btn-outline-primary"
                        >

                            <i class="bi bi-geo-alt"></i>

                            Share My Location

                        </a>

                    <?php endif; ?>


                    <!-- CONFIRM COMPLETION BUTTON -->
                    <?php if ($canConfirmCompletion): ?>

                        <form
                            method="POST"
                            class="d-inline"
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="action"
                                value="confirm_completion"
                            >

                            <button
                                type="submit"
                                class="btn btn-success"
                                onclick="return confirm('Are you sure the provider has completed the booking?');"
                            >

                                <i class="bi bi-check-circle"></i>

                                Confirm Completion

                            </button>

                        </form>

                    <?php endif; ?>


                    <!-- REVIEW BUTTON -->
                    <?php if ($canReview): ?>

                        <a
                            href="<?= e(APP_URL) ?>/customer/review.php?booking_id=<?= (int) $booking['id'] ?>"
                            class="btn btn-warning text-dark"
                        >

                            <i class="bi bi-star-fill"></i>

                            Leave a Review

                        </a>

                    <?php endif; ?>


                    <!-- COMPLETED + REVIEWED -->
                    <?php if (
                        $booking['booking_status'] === 'completed'
                        && $review
                    ): ?>

                        <span class="btn btn-outline-success disabled">

                            <i class="bi bi-check-circle"></i>

                            Review Submitted

                        </span>

                    <?php endif; ?>


                </div>


            </div>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>