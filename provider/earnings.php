<?php
/**
 * provider/earnings.php
 *
 * Shows provider earnings AFTER admin commission has been deducted.
 *
 * Example:
 * Payment = 100,000
 * Commission = 10%
 * Admin = 10,000
 * Provider = 90,000
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
require_once __DIR__ . '/_require_profile.php';


/*
|--------------------------------------------------------------------------
| Provider Profile ID
|--------------------------------------------------------------------------
*/
$providerId = (int) $providerProfile['id'];


/*
|--------------------------------------------------------------------------
| Total Provider Earnings
|--------------------------------------------------------------------------
|
| We use commissions.provider_amount instead of payments.amount.
|
| This means the provider sees the amount AFTER commission.
|
| Only "earned" or "paid" commissions are counted.
|
*/
$totalStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(c.provider_amount), 0)
     FROM commissions c
     WHERE c.provider_id = :pid
     AND c.commission_status IN ("earned", "paid")'
);

$totalStmt->execute([
    'pid' => $providerId
]);

$totalEarnings = (float) $totalStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| This Month Provider Earnings
|--------------------------------------------------------------------------
*/
$monthStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(c.provider_amount), 0)
     FROM commissions c
     WHERE c.provider_id = :pid
     AND c.commission_status IN ("earned", "paid")
     AND MONTH(c.created_at) = MONTH(CURRENT_DATE())
     AND YEAR(c.created_at) = YEAR(CURRENT_DATE())'
);

$monthStmt->execute([
    'pid' => $providerId
]);

$monthEarnings = (float) $monthStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Earnings
|--------------------------------------------------------------------------
|
| If a commission is still pending, it is shown separately.
|
*/
$pendingStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(c.provider_amount), 0)
     FROM commissions c
     WHERE c.provider_id = :pid
     AND c.commission_status = "pending"'
);

$pendingStmt->execute([
    'pid' => $providerId
]);

$pendingEarnings = (float) $pendingStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Earnings History
|--------------------------------------------------------------------------
|
| We join commissions with bookings and customers.
|
*/
$listStmt = $pdo->prepare(
    'SELECT
        c.id,
        c.booking_id,
        c.payment_amount,
        c.commission_rate,
        c.commission_amount,
        c.provider_amount,
        c.commission_status,
        c.created_at,

        b.event_name,
        b.event_date,

        u.fullname AS customer_name

     FROM commissions c

     JOIN bookings b
        ON b.id = c.booking_id

     JOIN users u
        ON u.id = b.customer_id

     WHERE c.provider_id = :pid

     ORDER BY c.created_at DESC'
);

$listStmt->execute([
    'pid' => $providerId
]);

$earnings = $listStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/
$current_page = 'earnings';
$page_title = 'Earnings';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>


        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>
                        <h3 class="fw-bold mb-1">
                            Earnings
                        </h3>

                        <p class="text-secondary mb-0">
                            Your earnings after platform commission.
                        </p>
                    </div>

                </div>


                <!-- =====================================================
                     SUMMARY CARDS
                     ===================================================== -->

                <div class="row g-3 mb-4">


                    <!-- Total Earnings -->
                    <div class="col-md-4">

                        <div class="stat-card bg-teal">

                            <i class="bi bi-cash-stack stat-icon"></i>

                            <p class="stat-value mb-0">
                                <?= format_money($totalEarnings) ?>
                            </p>

                            <p class="mb-0 small opacity-75">
                                Total Earnings
                            </p>

                        </div>

                    </div>


                    <!-- This Month -->
                    <div class="col-md-4">

                        <div class="stat-card bg-purple">

                            <i class="bi bi-calendar-month stat-icon"></i>

                            <p class="stat-value mb-0">
                                <?= format_money($monthEarnings) ?>
                            </p>

                            <p class="mb-0 small opacity-75">
                                This Month
                            </p>

                        </div>

                    </div>


                    <!-- Pending -->
                    <div class="col-md-4">

                        <div class="stat-card bg-warning">

                            <i class="bi bi-hourglass-split stat-icon"></i>

                            <p class="stat-value mb-0">
                                <?= format_money($pendingEarnings) ?>
                            </p>

                            <p class="mb-0 small opacity-75">
                                Pending Earnings
                            </p>

                        </div>

                    </div>

                </div>



                    <div class="d-flex">


                        <div>

                           

                    

                        </div>

                    </div>

                </div>


                <!-- =====================================================
                     EARNINGS HISTORY
                     ===================================================== -->

                <div class="card border-0 shadow-sm">

                    <div class="card-header bg-white py-3">

                        <h5 class="mb-0 fw-bold">
                            Earnings History
                        </h5>

                    </div>


                    <div class="card-body p-0">

                        <?php if (empty($earnings)): ?>

                            <div class="text-center py-5">

                                <i class="bi bi-wallet2 fs-1 text-secondary"></i>

                                <p class="text-secondary mt-3 mb-0">
                                    No earnings available yet.
                                </p>

                            </div>

                        <?php else: ?>

                            <div class="table-responsive">

                                <table class="table table-hover mb-0 align-middle">

                                    <thead class="table-light">

                                        <tr>

                                            <th>
                                                Event
                                            </th>

                                            <th>
                                                Customer
                                            </th>

                                            <th>
                                                Payment
                                            </th>

                                            <th>
                                                Commission
                                            </th>

                                            <th>
                                                Your Earning
                                            </th>

                                            <th>
                                                Status
                                            </th>

                                            <th>
                                                Date
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach ($earnings as $earning): ?>

                                            <tr>

                                                <!-- Event -->
                                                <td>

                                                    <div class="fw-semibold">
                                                        <?= e($earning['event_name']) ?>
                                                    </div>

                                                    <?php if (!empty($earning['event_date'])): ?>

                                                        <small class="text-secondary">
                                                            Event:
                                                            <?= format_date($earning['event_date']) ?>
                                                        </small>

                                                    <?php endif; ?>

                                                </td>


                                                <!-- Customer -->
                                                <td>
                                                    <?= e($earning['customer_name']) ?>
                                                </td>


                                                <!-- Original Payment -->
                                                <td>

                                                    <?= format_money(
                                                        $earning['payment_amount']
                                                    ) ?>

                                                </td>


                                                <!-- Commission -->
                                                <td>

                                                    <span class="text-danger">

                                                        -
                                                        <?= format_money(
                                                            $earning['commission_amount']
                                                        ) ?>

                                                    </span>

                                                    <small class="text-secondary d-block">

                                                        <?= e(
                                                            number_format(
                                                                (float) $earning['commission_rate'],
                                                                2
                                                            )
                                                        ) ?>%

                                                    </small>

                                                </td>


                                                <!-- Provider Earning -->
                                                <td class="fw-bold">

                                                    <?= format_money(
                                                        $earning['provider_amount']
                                                    ) ?>

                                                </td>


                                                <!-- Status -->
                                                <td>

                                                    <?php
                                                    $status = $earning['commission_status'];

                                                    $badgeClass = match ($status) {
                                                        'earned' => 'bg-success',
                                                        'paid'   => 'bg-primary',
                                                        'pending' => 'bg-warning text-dark',
                                                        default  => 'bg-secondary'
                                                    };
                                                    ?>

                                                    <span class="badge <?= $badgeClass ?>">

                                                        <?= e(ucfirst($status)) ?>

                                                    </span>

                                                </td>


                                                <!-- Date -->
                                                <td>

                                                    <?= format_date(
                                                        $earning['created_at']
                                                    ) ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- =====================================================
                     NOTE
                     ===================================================== -->

                <div class="small text-secondary mt-3">

                    <i class="bi bi-shield-check"></i>

                    Earnings shown here are calculated from completed
                    commission records and do not include the platform's
                    commission.

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

