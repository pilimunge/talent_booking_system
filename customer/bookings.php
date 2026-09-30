<?php
/**
 * customer/bookings.php
 * List of the customer's bookings with status filtering and cancel action.
 *
 * Audit actions:
 * - Cancelled Booking
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');


/*
|--------------------------------------------------------------------------
| Handle Cancel Booking
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['cancel_booking_id'])
) {

    verify_csrf();

    $bookingId = (int) $_POST['cancel_booking_id'];

    /*
    |--------------------------------------------------------------------------
    | Get Booking
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare(
        'SELECT *
         FROM bookings
         WHERE id = :id
           AND customer_id = :cid
         LIMIT 1'
    );

    $stmt->execute([
        'id'  => $bookingId,
        'cid' => current_user_id()
    ]);

    $booking = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Check Whether Booking Can Be Cancelled
    |--------------------------------------------------------------------------
    */
    if (
        $booking
        && in_array(
            $booking['booking_status'],
            ['pending', 'accepted'],
            true
        )
    ) {

        try {

            /*
            |--------------------------------------------------------------------------
            | Start Transaction
            |--------------------------------------------------------------------------
            */
            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Cancel Booking
            |--------------------------------------------------------------------------
            */
            $update = $pdo->prepare(
                'UPDATE bookings
                 SET booking_status = "cancelled"
                 WHERE id = :id
                   AND customer_id = :cid
                   AND booking_status IN ("pending", "accepted")'
            );

            $update->execute([
                'id'  => $bookingId,
                'cid' => current_user_id()
            ]);


            /*
            |--------------------------------------------------------------------------
            | Make Sure Booking Was Cancelled
            |--------------------------------------------------------------------------
            */
            if ($update->rowCount() !== 1) {

                throw new Exception(
                    'Booking could not be cancelled.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Get Provider User ID
            |--------------------------------------------------------------------------
            */
            $providerStmt = $pdo->prepare(
                'SELECT user_id
                 FROM providers
                 WHERE id = :id
                 LIMIT 1'
            );

            $providerStmt->execute([
                'id' => $booking['provider_id']
            ]);

            $providerUserId = $providerStmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Notify Provider
            |--------------------------------------------------------------------------
            */
            if ($providerUserId) {

                push_notification(
                    $pdo,
                    (int) $providerUserId,
                    'Booking Cancelled',
                    current_user_name()
                    . ' cancelled the booking "'
                    . $booking['event_name']
                    . '".',
                    'customer'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            | This is placed AFTER the cancellation succeeds.
            */
            audit_log(
                $pdo,
                'Cancelled Booking',
                'Cancelled booking "' . $booking['event_name'] . '".',
                'booking',
                $bookingId
            );


            /*
            |--------------------------------------------------------------------------
            | Existing Activity Log
            |--------------------------------------------------------------------------
            */
            log_activity(
                $pdo,
                current_user_id(),
                'Cancelled booking #'
                . $bookingId
            );


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */
            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            */
            set_flash(
                'success',
                'Booking cancelled successfully.'
            );

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Booking cancellation failed for booking #'
                . $bookingId
                . ': '
                . $e->getMessage()
            );

            set_flash(
                'error',
                'Something went wrong while cancelling the booking.'
            );
        }

    } else {

        set_flash(
            'error',
            'This booking can no longer be cancelled.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */
    redirect('customer/bookings.php');
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/
$statusFilter = $_GET['status'] ?? '';

$conditions = [
    'b.customer_id = :cid'
];

$params = [
    'cid' => current_user_id()
];


/*
|--------------------------------------------------------------------------
| Apply Status Filter
|--------------------------------------------------------------------------
*/
$allowedStatuses = [
    'pending',
    'accepted',
    'rejected',
    'cancelled',
    'ongoing',
    'completed'
];

if (
    $statusFilter !== ''
    && in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $conditions[] =
        'b.booking_status = :status';

    $params['status'] =
        $statusFilter;
}


$where = implode(
    ' AND ',
    $conditions
);


/*
|--------------------------------------------------------------------------
| Get Customer Bookings
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    "SELECT
        b.*,
        u.fullname AS provider_name
     FROM bookings b
     JOIN providers p
        ON p.id = b.provider_id
     JOIN users u
        ON u.id = p.user_id
     WHERE {$where}
     ORDER BY b.created_at DESC"
);

$stmt->execute($params);

$bookings = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/
$current_page = 'bookings';
$page_title = 'My Bookings';

require_once __DIR__ . '/../includes/header.php';
?>


<div class="container-fluid">

    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>


        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid">


                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">

                    <h3 class="fw-bold mb-0">
                        My Bookings
                    </h3>

                    <a
                        href="<?= e(APP_URL) ?>/customer/search.php"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-plus-lg"></i>
                        New Booking
                    </a>

                </div>


                <!-- Status Filters -->
                <div class="d-flex gap-2 mb-4 flex-wrap">

                    <a
                        href="?status="
                        class="btn btn-sm <?= $statusFilter === ''
                            ? 'btn-primary'
                            : 'btn-outline-secondary'
                        ?>"
                    >
                        All
                    </a>


                    <?php foreach (
                        [
                            'pending',
                            'accepted',
                            'rejected',
                            'ongoing',
                            'completed',
                            'cancelled'
                        ] as $s
                    ): ?>

                        <a
                            href="?status=<?= e($s) ?>"
                            class="btn btn-sm <?= $statusFilter === $s
                                ? 'btn-primary'
                                : 'btn-outline-secondary'
                            ?>"
                        >
                            <?= ucfirst(e($s)) ?>
                        </a>

                    <?php endforeach; ?>

                </div>


                <!-- No Bookings -->
                <?php if (empty($bookings)): ?>

                    <div class="alert alert-light border text-center py-5">

                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>

                        <p class="mb-0">
                            No bookings found.
                        </p>

                    </div>


                <?php else: ?>


                    <!-- Bookings Table -->
                    <div class="card border-0 shadow-sm">

                        <div class="table-responsive">

                            <table class="table table-hover mb-0 align-middle">

                                <thead class="table-light">

                                    <tr>
                                        <th>Event</th>
                                        <th>Provider</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>

                                </thead>


                                <tbody>

                                <?php foreach ($bookings as $b): ?>

                                    <tr>

                                        <!-- Event -->
                                        <td>

                                            <?= e($b['event_name']) ?>

                                            <br>

                                            <span class="small text-secondary">
                                                <?= e($b['event_type']) ?>
                                            </span>

                                        </td>


                                        <!-- Provider -->
                                        <td>
                                            <?= e($b['provider_name']) ?>
                                        </td>


                                        <!-- Date -->
                                        <td>
                                            <?= format_date($b['event_date']) ?>
                                        </td>


                                        <!-- Status -->
                                        <td>

                                            <span
                                                class="badge <?= booking_status_badge(
                                                    $b['booking_status']
                                                ) ?>"
                                            >
                                                <?= ucfirst(
                                                    e($b['booking_status'])
                                                ) ?>
                                            </span>

                                        </td>


                                        <!-- Actions -->
                                        <td class="text-end">

                                            <a
                                                href="<?= e(APP_URL) ?>/customer/booking_view.php?id=<?= (int) $b['id'] ?>"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                View
                                            </a>


                                            <?php if (
                                                in_array(
                                                    $b['booking_status'],
                                                    ['pending', 'accepted'],
                                                    true
                                                )
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action=""
                                                    class="d-inline"
                                                    data-confirm="Are you sure you want to cancel this booking?"
                                                >

                                                    <?= csrf_field() ?>

                                                    <input
                                                        type="hidden"
                                                        name="cancel_booking_id"
                                                        value="<?= (int) $b['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                    >
                                                        Cancel
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>