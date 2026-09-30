<?php
/**
 * provider/_sidebar.php
 * $current_page must be set before including this file.
 */
$current_page = $current_page ?? '';
?>
<div class="col-lg-3 col-xl-2 px-0 dashboard-sidebar">
    <div class="p-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'dashboard' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'bookings' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/bookings.php">
                    <i class="bi bi-calendar-check"></i> Booking Requests
                </a>
            </li>

             <li class="nav-item">
                <a class="nav-link <?= $current_page === 'chat' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/messages.php">
                    <i class="bi bi-calendar-check"></i> Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'availability' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/availability.php">
                    <i class="bi bi-calendar3"></i> Availability
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'earnings' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/earnings.php">
                    <i class="bi bi-cash-stack"></i> Earnings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'reviews' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/reviews.php">
                    <i class="bi bi-star"></i> Reviews
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'gallery' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/gallery.php">
                    <i class="bi bi-images"></i> Gallery
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'notifications' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $current_page === 'profile' ? 'active' : '' ?>" href="<?= e(APP_URL) ?>/provider/profile.php">
                    <i class="bi bi-person"></i> My Profile
                </a>
            </li>
        </ul>
    </div>
</div>
