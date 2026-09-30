<?php
/**
 * admin/_sidebar.php
 * $current_page must be set before including this file.
 */
$current_page = $current_page ?? '';
?>

<div class="col-lg-3 col-xl-2 px-0 dashboard-sidebar">

    <div class="p-3">

        <ul class="nav flex-column">

            <!-- Dashboard -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/dashboard.php"
                >
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>


            <!-- Manage Users -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'users' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/users.php"
                >
                    <i class="bi bi-people"></i>
                    Manage Users
                </a>
            </li>


            <!-- Manage Providers -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'providers' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/providers.php"
                >
                    <i class="bi bi-person-badge"></i>
                    Manage Providers
                </a>
            </li>


            <!-- Categories -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'categories' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/categories.php"
                >
                    <i class="bi bi-tags"></i>
                    Categories
                </a>
            </li>


            <!-- Bookings -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'bookings' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/bookings.php"
                >
                    <i class="bi bi-calendar-check"></i>
                    Bookings
                </a>
            </li>


            <!-- Payments -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'payments' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/payments.php"
                >
                    <i class="bi bi-credit-card"></i>
                    Payments
                </a>
            </li>


            <!-- Commissions -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'commissions' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/commissions.php"
                >
                    <i class="bi bi-cash-stack"></i>
                    Commissions
                </a>
            </li>


            <!-- Reviews -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'reviews' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/reviews.php"
                >
                    <i class="bi bi-star"></i>
                    Reviews
                </a>
            </li>


            <!-- Reports -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'reports' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/reports.php"
                >
                    <i class="bi bi-graph-up"></i>
                    Reports
                </a>
            </li>
            <a href="<?= e(APP_URL) ?>/admin/audit_logs.php"
   class="nav-link <?= $current_page === 'audit_logs' ? 'active' : '' ?>">

    <i class="bi bi-shield-check"></i>

    <span>Audit Logs</span>

</a>


            <!-- Notifications -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'notifications' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/notifications.php"
                >
                    <i class="bi bi-bell"></i>
                    Notifications
                </a>
            </li>


            <!-- Settings -->
            <li class="nav-item">
                <a
                    class="nav-link <?= $current_page === 'settings' ? 'active' : '' ?>"
                    href="<?= e(APP_URL) ?>/admin/settings.php"
                >
                    <i class="bi bi-gear"></i>
                    Settings
                </a>
            </li>

        </ul>

    </div>

</div>

