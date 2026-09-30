<?php
/**
 * admin/commissions.php
 *
 * Shows all commissions earned by the administrator.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$statusFilter = $_GET['status'] ?? '';

$allowedStatuses = [
    'pending',
    'earned',
    'paid'
];

$conditions = ['1=1'];
$params = [];


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    $statusFilter !== ''
    && in_array($statusFilter, $allowedStatuses, true)
) {
    $conditions[] = 'c.commission_status = :status';
    $params['status'] = $statusFilter;
}


$where = implode(' AND ', $conditions);


/*
|--------------------------------------------------------------------------
| Get Commissions
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "SELECT
        c.*,

        b.event_name,
        b.event_type,
        b.event_date,

        cu.fullname AS customer_name,

        pu.fullname AS provider_name

     FROM commissions c

     JOIN bookings b
        ON b.id = c.booking_id

     JOIN users cu
        ON cu.id = b.customer_id

     JOIN providers p
        ON p.id = c.provider_id

     JOIN users pu
        ON pu.id = p.user_id

     WHERE {$where}

     ORDER BY c.created_at DESC"
);

$stmt->execute($params);

$commissions = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

/*
 * Total commission earned.
 */
$totalCommissionStmt = $pdo->query(
    "SELECT
        COALESCE(SUM(commission_amount), 0)
     FROM commissions
     WHERE commission_status = 'earned'"
);

$totalCommission = (float) $totalCommissionStmt->fetchColumn();


/*
 * Total commission paid.
 */
$totalPaidCommissionStmt = $pdo->query(
    "SELECT
        COALESCE(SUM(commission_amount), 0)
     FROM commissions
     WHERE commission_status = 'paid'"
);

$totalPaidCommission = (float) $totalPaidCommissionStmt->fetchColumn();


/*
 * Total commission pending.
 */
$totalPendingCommissionStmt = $pdo->query(
    "SELECT
        COALESCE(SUM(commission_amount), 0)
     FROM commissions
     WHERE commission_status = 'pending'"
);

$totalPendingCommission = (float) $totalPendingCommissionStmt->fetchColumn();


/*
 * Total payment value associated with commissions.
 */
$totalPaymentStmt = $pdo->query(
    "SELECT
        COALESCE(SUM(payment_amount), 0)
     FROM commissions"
);

$totalPaymentAmount = (float) $totalPaymentStmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$current_page = 'commissions';
$page_title = 'Commissions';

require_once __DIR__ . '/../includes/header.php';
?>


<div class="container-fluid">

    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>


        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid">


                <!--
                |--------------------------------------------------------------------------
                | PAGE HEADER
                |--------------------------------------------------------------------------
                -->

                <div class="d-flex justify-content-between align-items-center mb-4">

                    <div>

                        <h3 class="fw-bold mb-1">
                            Commissions
                        </h3>

                        <p class="text-secondary mb-0">
                            Track administrator commission from completed bookings.
                        </p>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | SUMMARY CARDS
                |--------------------------------------------------------------------------
                -->

                <div class="row g-3 mb-4">


                    <!-- Total Payment -->
                    <div class="col-md-6 col-xl-3">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <p class="text-secondary small mb-1">
                                            Payment Value
                                        </p>

                                        <h4 class="fw-bold mb-0">
                                            <?= format_money($totalPaymentAmount) ?>
                                        </h4>

                                    </div>

                                    <div class="text-primary fs-3">
                                        <i class="bi bi-credit-card"></i>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Earned Commission -->
                    <div class="col-md-6 col-xl-3">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <p class="text-secondary small mb-1">
                                            Commission Earned
                                        </p>

                                        <h4 class="fw-bold mb-0 text-success">
                                            <?= format_money($totalCommission) ?>
                                        </h4>

                                    </div>

                                    <div class="text-success fs-3">
                                        <i class="bi bi-cash-stack"></i>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Pending Commission -->
                    <div class="col-md-6 col-xl-3">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <p class="text-secondary small mb-1">
                                            Pending Commission
                                        </p>

                                        <h4 class="fw-bold mb-0 text-warning">
                                            <?= format_money($totalPendingCommission) ?>
                                        </h4>

                                    </div>

                                    <div class="text-warning fs-3">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Paid Commission -->
                    <div class="col-md-6 col-xl-3">

                        <div class="card border-0 shadow-sm h-100">

                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-start">

                                    <div>

                                        <p class="text-secondary small mb-1">
                                            Commission Paid
                                        </p>

                                        <h4 class="fw-bold mb-0">
                                            <?= format_money($totalPaidCommission) ?>
                                        </h4>

                                    </div>

                                    <div class="text-secondary fs-3">
                                        <i class="bi bi-check2-circle"></i>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | FILTER
                |--------------------------------------------------------------------------
                -->

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-body">

                        <form method="GET" action="" class="row g-2 align-items-end">

                            <div class="col-md-4">

                                <label class="form-label small fw-semibold">
                                    Commission Status
                                </label>

                                <select
                                    name="status"
                                    class="form-select"
                                >

                                    <option value="">
                                        All Statuses
                                    </option>

                                    <?php foreach ($allowedStatuses as $status): ?>

                                        <option
                                            value="<?= e($status) ?>"
                                            <?= $statusFilter === $status ? 'selected' : '' ?>
                                        >
                                            <?= ucfirst(e($status)) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-md-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary w-100"
                                >

                                    <i class="bi bi-funnel"></i>

                                    Filter

                                </button>

                            </div>


                            <?php if ($statusFilter !== ''): ?>

                                <div class="col-md-2">

                                    <a
                                        href="<?= e(APP_URL) ?>/admin/commissions.php"
                                        class="btn btn-outline-secondary w-100"
                                    >

                                        <i class="bi bi-x-circle"></i>

                                        Clear

                                    </a>

                                </div>

                            <?php endif; ?>

                        </form>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | COMMISSION TABLE
                |--------------------------------------------------------------------------
                -->

                <div class="card border-0 shadow-sm">

                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-hover align-middle mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th class="px-3">
                                            Event
                                        </th>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Provider
                                        </th>

                                        <th>
                                            Payment
                                        </th>

                                        <th>
                                            Rate
                                        </th>

                                        <th>
                                            Admin Commission
                                        </th>

                                        <th>
                                            Provider Amount
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

                                    <?php foreach ($commissions as $commission): ?>

                                        <tr>

                                            <!-- Event -->
                                            <td class="px-3">

                                                <div class="fw-semibold">
                                                    <?= e($commission['event_name']) ?>
                                                </div>

                                                <div class="small text-secondary">

                                                    <?= e($commission['event_type']) ?>

                                                    <?php if (!empty($commission['event_date'])): ?>

                                                        ·
                                                        <?= format_date($commission['event_date']) ?>

                                                    <?php endif; ?>

                                                </div>

                                            </td>


                                            <!-- Customer -->
                                            <td>

                                                <?= e($commission['customer_name']) ?>

                                            </td>


                                            <!-- Provider -->
                                            <td>

                                                <?= e($commission['provider_name']) ?>

                                            </td>


                                            <!-- Payment -->
                                            <td class="fw-semibold">

                                                <?= format_money(
                                                    $commission['payment_amount']
                                                ) ?>

                                            </td>


                                            <!-- Commission Rate -->
                                            <td>

                                                <?= e(
                                                    number_format(
                                                        (float) $commission['commission_rate'],
                                                        2
                                                    )
                                                ) ?>%

                                            </td>


                                            <!-- Admin Commission -->
                                            <td>

                                                <span class="fw-bold text-success">

                                                    <?= format_money(
                                                        $commission['commission_amount']
                                                    ) ?>

                                                </span>

                                            </td>


                                            <!-- Provider Amount -->
                                            <td>

                                                <?= format_money(
                                                    $commission['provider_amount']
                                                ) ?>

                                            </td>


                                            <!-- Status -->
                                            <td>

                                                <?php
                                                $status = $commission['commission_status'];

                                                $statusClass = match ($status) {
                                                    'earned'  => 'bg-success',
                                                    'paid'    => 'bg-primary',
                                                    'pending' => 'bg-warning text-dark',
                                                    default   => 'bg-secondary'
                                                };
                                                ?>

                                                <span class="badge <?= $statusClass ?>">

                                                    <?= ucfirst(e($status)) ?>

                                                </span>

                                            </td>


                                            <!-- Date -->
                                            <td class="small">

                                                <?= format_date(
                                                    $commission['created_at']
                                                ) ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>


                                    <?php if (empty($commissions)): ?>

                                        <tr>

                                            <td
                                                colspan="9"
                                                class="text-center text-secondary py-5"
                                            >

                                                <div class="mb-2 fs-2">

                                                    <i class="bi bi-cash-coin"></i>

                                                </div>

                                                <div class="fw-semibold">
                                                    No commissions found.
                                                </div>

                                                <div class="small mt-1">
                                                    Commissions will appear here
                                                    after a customer confirms
                                                    completion of a paid booking.
                                                </div>

                                            </td>

                                        </tr>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>


                <!--
                |--------------------------------------------------------------------------
                | INFORMATION
                |--------------------------------------------------------------------------
                -->

            


            </div>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
