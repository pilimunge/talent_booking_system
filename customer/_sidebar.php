<?php
/**
 * customer/_sidebar.php
 * Include inside a page that already has $current_page set, e.g.:
 *   $current_page = 'dashboard';
 * Used only on customer/* dashboard pages (not on public pages like search.php).
 */
$current_page = $current_page ?? '';
?>
<div class="col-lg-3 col-xl-2 px-0 dashboard-sidebar">
    <div class="p-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/customer/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'search' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/customer/search.php">
                    <i class="bi bi-search"></i> Find Talent
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'bookings' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/customer/bookings.php">
                    <i class="bi bi-calendar-check"></i> My Bookings
                </a>
            </li>
            <a
    class="nav-link"
    href="<?= e(APP_URL) ?>/customer/messages.php"
>
    <i class="bi bi-envelope"></i>
    Messages
</a>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'notifications' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/customer/notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/customer/profile.php">
                    <i class="bi bi-person"></i> My Profile
                </a>
            </li>
        </ul>
    </div>
</div>
