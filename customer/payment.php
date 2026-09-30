<?php
/**
 * customer/payment.php
 *
 * Manual/mock payment flow.
 *
 * Customer:
 * 1. Opens payment page for an accepted booking.
 * 2. Sends money using the selected payment method.
 * 3. Enters transaction reference.
 * 4. System records payment as paid.
 * 5. Booking changes to ongoing.
 *
 * Commission is NOT created here.
 * Commission will be created only after:
 *
 * Provider -> Mark as Completed
 * Customer -> Confirm Completion
 *
 * This prevents commission from being earned before the service
 * has actually been completed and confirmed.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');


/*
|--------------------------------------------------------------------------
| Get Booking ID
|--------------------------------------------------------------------------
*/
$bookingId = (int) (
    $_GET['booking_id']
    ?? $_POST['booking_id']
    ?? 0
);

if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking.');
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
        p.user_id AS provider_user_id,
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


/*
|--------------------------------------------------------------------------
| Validate Booking
|--------------------------------------------------------------------------
*/
if (!$booking) {
    set_flash('error', 'Booking not found.');
    redirect('customer/bookings.php');
}


/*
|--------------------------------------------------------------------------
| Booking must be accepted before payment
|--------------------------------------------------------------------------
*/
if ($booking['booking_status'] !== 'accepted') {
    set_flash(
        'error',
        'This booking is not ready for payment.'
    );

    redirect(
        'customer/booking_view.php?id=' . $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| Check Existing Payment
|--------------------------------------------------------------------------
*/
$existingPayment = $pdo->prepare(
    'SELECT id
     FROM payments
     WHERE booking_id = :id
     LIMIT 1'
);

$existingPayment->execute([
    'id' => $bookingId
]);

if ($existingPayment->fetch()) {

    set_flash(
        'info',
        'Payment has already been recorded for this booking.'
    );

    redirect(
        'customer/booking_view.php?id=' . $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| Form Variables
|--------------------------------------------------------------------------
*/

/*
 * Amount is taken from provider booking price.
 * Customer should not be able to change it.
 */
$requiredAmount = (float) $booking['price'];

$errors = [];

$old = [
    'payment_method' => 'mpesa',
    'transaction_reference' => ''
];


/*
|--------------------------------------------------------------------------
| Process Payment
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();


    $old['payment_method'] =
        $_POST['payment_method'] ?? '';

    $old['transaction_reference'] =
        trim($_POST['transaction_reference'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Valid Payment Methods
    |--------------------------------------------------------------------------
    */
    $validMethods = [
        'mpesa',
        'airtel_money',
        'tigo_pesa',
        'halopesa',
        'bank_transfer'
    ];


    /*
    |--------------------------------------------------------------------------
    | Validate Payment Method
    |--------------------------------------------------------------------------
    */
    if (
        !in_array(
            $old['payment_method'],
            $validMethods,
            true
        )
    ) {

        $errors[] =
            'Please select a valid payment method.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Transaction Reference
    |--------------------------------------------------------------------------
    */
    if ($old['transaction_reference'] === '') {

        $errors[] =
            'Please enter the transaction reference from your payment confirmation SMS.';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Transaction Reference
    |--------------------------------------------------------------------------
    */
    if ($old['transaction_reference'] !== '') {

        $refCheck = $pdo->prepare(
            'SELECT id
             FROM payments
             WHERE transaction_reference = :ref
             LIMIT 1'
        );

        $refCheck->execute([
            'ref' => $old['transaction_reference']
        ]);

        if ($refCheck->fetch()) {

            $errors[] =
                'This transaction reference has already been used.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Payment
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        try {

            /*
            |--------------------------------------------------------------------------
            | START TRANSACTION
            |--------------------------------------------------------------------------
            */
            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Payment
            |--------------------------------------------------------------------------
            */
            $insert = $pdo->prepare(
                'INSERT INTO payments
                (
                    booking_id,
                    amount,
                    payment_method,
                    payment_status,
                    transaction_reference
                )
                VALUES
                (
                    :booking_id,
                    :amount,
                    :payment_method,
                    "paid",
                    :reference
                )'
            );

            $insert->execute([
                'booking_id'    => $bookingId,
                'amount'        => $requiredAmount,
                'payment_method' => $old['payment_method'],
                'reference'     => $old['transaction_reference']
            ]);


            /*
            |--------------------------------------------------------------------------
            | Get Payment ID
            |--------------------------------------------------------------------------
            */
            $paymentId = (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Change Booking Status
            |--------------------------------------------------------------------------
            */
            $updateBooking = $pdo->prepare(
                'UPDATE bookings
                 SET booking_status = "ongoing"
                 WHERE id = :id
                   AND booking_status = "accepted"'
            );

            $updateBooking->execute([
                'id' => $bookingId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Make Sure Booking Was Updated
            |--------------------------------------------------------------------------
            */
            if ($updateBooking->rowCount() !== 1) {

                throw new Exception(
                    'Booking status could not be updated.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Notify Provider
            |--------------------------------------------------------------------------
            */
            push_notification(
                $pdo,
                (int) $booking['provider_user_id'],
                'Payment Received',
                'Payment of '
                . format_money($requiredAmount)
                . ' received for "'
                . $booking['event_name']
                . '".',
                'customer'
            );

            /*
            |--------------------------------------------------------------------------
            | Notify Admin (if one exists)
            |--------------------------------------------------------------------------
            */
            $adminStmt = $pdo->query(
                "SELECT id
                 FROM users
                 WHERE role = 'admin'
                 LIMIT 1"
            );

            $admin = $adminStmt->fetch();

            if ($admin) {

                push_notification(
                    $pdo,
                    (int) $admin['id'],
                    'Payment Received',
                    format_money($requiredAmount)
                    . ' payment received for booking "'
                    . $booking['event_name']
                    . '".',
                    'customer'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */
            audit_log(
                $pdo,
                'Confirmed Payment',
                'Paid ' . format_money($requiredAmount) . ' via ' . $old['payment_method']
                    . ' for booking "' . $booking['event_name'] . '" (ref: ' . $old['transaction_reference'] . ').',
                'payment',
                $paymentId
            );

            /*
            |--------------------------------------------------------------------------
            | Existing Activity Log
            |--------------------------------------------------------------------------
            */
            log_activity(
                $pdo,
                current_user_id(),
                'Made payment for booking #'
                . $bookingId
                . ' (Payment ID #'
                . $paymentId
                . ')'
            );



            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */
            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */
            set_flash(
                'success',
                'Payment recorded successfully!'
            );


            redirect(
                'customer/booking_view.php?id=' . $bookingId
            );


        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Log Error
            |--------------------------------------------------------------------------
            */
            error_log(
                'Payment failed for booking #'
                . $bookingId
                . ': '
                . $e->getMessage()
            );


            $errors[] =
                'Something went wrong recording your payment. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Payment Methods
|--------------------------------------------------------------------------
*/
$paymentMethods = [
    'mpesa'         => 'M-Pesa',
    'airtel_money'  => 'Airtel Money',
    'tigo_pesa'     => 'Tigo Pesa',
    'halopesa'      => 'HaloPesa',
    'bank_transfer' => 'Bank Transfer'
];


$current_page = 'bookings';
$page_title = 'Make Payment';

require_once __DIR__ . '/../includes/header.php';
?>


<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <!-- Back -->
            <a
                href="<?= e(APP_URL) ?>/customer/booking_view.php?id=<?= (int) $bookingId ?>"
                class="small text-decoration-none"
            >
                <i class="bi bi-arrow-left"></i>
                Back to booking
            </a>


            <div class="card border-0 shadow-sm mt-3">

                <div class="card-body p-4">

                    <h5 class="fw-bold mb-1">
                        Complete Payment
                    </h5>

                    <p class="text-secondary small mb-4">

                        Booking:
                        <?= e($booking['event_name']) ?>

                        with

                        <?= e($booking['provider_name']) ?>

                    </p>


                    <!-- Amount -->
                    <div class="alert alert-primary">

                        <div class="small">
                            Amount to Pay
                        </div>

                        <div class="fs-4 fw-bold">

                            <?= format_money($requiredAmount) ?>

                        </div>

                    </div>


                    <!-- Information -->
                    <div class="alert alert-info small">

                        <i class="bi bi-info-circle"></i>

                        Send the amount shown above using your
                        selected payment method. Then enter the
                        transaction reference from your confirmation SMS.

                    </div>


                    <!-- Errors -->
                    <?php if (!empty($errors)): ?>

                        <div class="alert alert-danger">

                            <ul class="mb-0 ps-3">

                                <?php foreach ($errors as $error): ?>

                                    <li>
                                        <?= e($error) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>


                    <!-- Payment Form -->
                    <form
                        method="POST"
                        action=""
                        novalidate
                    >

                        <?= csrf_field() ?>


                        <input
                            type="hidden"
                            name="booking_id"
                            value="<?= (int) $bookingId ?>"
                        >


                        <!-- Amount -->
                        <div class="mb-3">

                            <label class="form-label">
                                Amount (TZS)
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?= e(number_format($requiredAmount, 2)) ?>"
                                readonly
                            >

                            <div class="form-text">
                                The amount is fixed according to the provider's booking price.
                            </div>

                        </div>


                        <!-- Payment Method -->
                        <div class="mb-3">

                            <label class="form-label">
                                Payment Method
                            </label>

                            <select
                                name="payment_method"
                                class="form-select"
                                required
                            >

                                <?php foreach (
                                    $paymentMethods
                                    as $value => $label
                                ): ?>

                                    <option
                                        value="<?= e($value) ?>"
                                        <?= $old['payment_method'] === $value
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= e($label) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Transaction Reference -->
                        <div class="mb-4">

                            <label class="form-label">
                                Transaction Reference
                            </label>

                            <input
                                type="text"
                                name="transaction_reference"
                                class="form-control"
                                value="<?= e($old['transaction_reference']) ?>"
                                placeholder="e.g. QG7X8YABC1"
                                maxlength="150"
                                required
                            >

                            <div class="form-text">
                                Found in your mobile money confirmation SMS.
                            </div>

                        </div>


                        <!-- Submit -->
                        <button
                            type="submit"
                            class="btn btn-primary w-100 py-2 fw-semibold"
                        >

                            <i class="bi bi-check-circle"></i>

                            Confirm Payment

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
