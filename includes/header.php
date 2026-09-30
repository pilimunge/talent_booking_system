<?php

/**
 * =========================================================
 * header.php
 * Shared Header - Talent Booking System
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/language.php';


/*
|--------------------------------------------------------------------------
| Page title
|--------------------------------------------------------------------------
*/
$page_title = $page_title ?? APP_NAME;


/*
|--------------------------------------------------------------------------
| Unread notifications
|--------------------------------------------------------------------------
*/
$unread_count = 0;

if (is_logged_in()) {

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM notifications
         WHERE user_id = :uid
           AND status = "unread"'
    );

    $stmt->execute([
        'uid' => current_user_id()
    ]);

    $unread_count = (int) $stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| Current user role
|--------------------------------------------------------------------------
*/
$currentRole = current_user_role();


/*
|--------------------------------------------------------------------------
| Dashboard URL according to role
|--------------------------------------------------------------------------
*/
if ($currentRole === 'admin') {

    $dashboard_link = APP_URL . '/admin/dashboard.php';

} elseif ($currentRole === 'provider') {

    $dashboard_link = APP_URL . '/provider/dashboard.php';

} elseif ($currentRole === 'customer') {

    $dashboard_link = APP_URL . '/customer/dashboard.php';

} else {

    $dashboard_link = APP_URL . '/index.php';
}


/*
|--------------------------------------------------------------------------
| Profile URL according to role
|--------------------------------------------------------------------------
*/
$profile_link = APP_URL . '/index.php';

if ($currentRole === 'admin') {

    $profile_link = APP_URL . '/admin/profile.php';

} elseif ($currentRole === 'provider') {

    $profile_link = APP_URL . '/provider/profile.php';

} elseif ($currentRole === 'customer') {

    $profile_link = APP_URL . '/customer/profile.php';
}


/*
|--------------------------------------------------------------------------
| Notification URL according to role
|--------------------------------------------------------------------------
*/
$notification_link = APP_URL . '/index.php';

if ($currentRole === 'admin') {

    $notification_link = APP_URL . '/admin/notifications.php';

} elseif ($currentRole === 'provider') {

    $notification_link = APP_URL . '/provider/notifications.php';

} elseif ($currentRole === 'customer') {

    $notification_link = APP_URL . '/customer/notifications.php';
}

?>

<!DOCTYPE html>

<html lang="<?= $currentLanguage === 'sw' ? 'sw' : 'en' ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= e($page_title) ?> |
        <?= e(APP_NAME) ?>
    </title>


    <!-- =====================================================
         BOOTSTRAP 5 CSS
         ===================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >


    <!-- =====================================================
         CUSTOM CSS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="<?= e(APP_URL) ?>/assets/css/style.css"
    >

</head>


<body>


<!-- =========================================================
     NAVBAR
     ========================================================= -->

<nav
    class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm"
>

    <div class="container">


        <!-- =================================================
             BRAND
             ================================================= -->

        <a
            class="navbar-brand fw-bold"
            href="<?= e(APP_URL) ?>/index.php"
        >

            <i class="bi bi-stars"></i>

            <?= e(APP_NAME) ?>

        </a>


        <!-- =================================================
             MOBILE NAVBAR BUTTON
             ================================================= -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNav"
            aria-controls="mainNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >

            <span class="navbar-toggler-icon"></span>

        </button>


        <!-- =================================================
             NAVBAR CONTENT
             ================================================= -->

        <div
            class="collapse navbar-collapse"
            id="mainNav"
        >


            <!-- =================================================
                 LEFT MENU
                 ================================================= -->

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">


                <!-- HOME -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= e(APP_URL) ?>/index.php"
                    >

                        <?= e(__t('home')) ?>

                    </a>

                </li>


                <!-- BROWSE TALENT -->

                <li class="nav-item">

                    <a
                        class="nav-link"
                        href="<?= e(APP_URL) ?>/customer/search.php"
                    >

                        <?= e(__t('browse_talent')) ?>

                    </a>

                </li>


            </ul>


            <!-- =================================================
                 RIGHT MENU
                 ================================================= -->

            <ul
                class="navbar-nav ms-auto align-items-lg-center"
            >


                <!-- =================================================
                     LANGUAGE DROPDOWN
                     ================================================= -->

                <li class="nav-item dropdown">

                    <a
                        class="nav-link dropdown-toggle d-flex align-items-center gap-1"
                        href="#"
                        id="languageDropdown"
                        role="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >

                        <i class="bi bi-globe2"></i>

                        <span>

                            <?php if ($currentLanguage === 'sw'): ?>

                                Kiswahili

                            <?php else: ?>

                                English

                            <?php endif; ?>

                        </span>

                    </a>


                    <ul
                        class="dropdown-menu dropdown-menu-end shadow"
                        aria-labelledby="languageDropdown"
                    >


                        <!-- ENGLISH -->

                        <li>

                            <a
                                class="dropdown-item <?= $currentLanguage === 'en' ? 'active' : '' ?>"
                                href="?lang=en"
                            >

                                🇬🇧

                                <span class="ms-1">
                                    English
                                </span>

                            </a>

                        </li>


                        <!-- KISWAHILI -->

                        <li>

                            <a
                                class="dropdown-item <?= $currentLanguage === 'sw' ? 'active' : '' ?>"
                                href="?lang=sw"
                            >

                                🇹🇿

                                <span class="ms-1">
                                    Kiswahili
                                </span>

                            </a>

                        </li>


                    </ul>

                </li>


                <?php if (is_logged_in()): ?>


                    <!-- =================================================
                         NOTIFICATIONS
                         ================================================= -->

                    <li class="nav-item">

                        <a
                            class="nav-link position-relative px-3"
                            href="<?= e($notification_link) ?>"
                            title="Notifications"
                        >

                            <i class="bi bi-bell fs-5"></i>


                            <?php if ($unread_count > 0): ?>

                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                >

                                    <?= $unread_count > 9
                                        ? '9+'
                                        : $unread_count
                                    ?>

                                </span>

                            <?php endif; ?>


                        </a>

                    </li>


                    <!-- =================================================
                         USER DROPDOWN
                         ================================================= -->

                    <li class="nav-item dropdown">

                        <a
                            class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                            href="#"
                            id="userDropdown"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >

                            <i class="bi bi-person-circle fs-5"></i>

                            <span>
                                <?= e(current_user_name()) ?>
                            </span>

                        </a>


                        <ul
                            class="dropdown-menu dropdown-menu-end shadow"
                            aria-labelledby="userDropdown"
                        >


                            <!-- DASHBOARD -->

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="<?= e($dashboard_link) ?>"
                                >

                                    <i class="bi bi-speedometer2 me-2"></i>

                                    <?= e(__t('dashboard')) ?>

                                </a>

                            </li>


                            <!-- PROFILE -->

                            <li>

                                <a
                                    class="dropdown-item"
                                    href="<?= e($profile_link) ?>"
                                >

                                    <i class="bi bi-person me-2"></i>

                                    My Profile

                                </a>

                            </li>


                            <!-- DIVIDER -->

                            <li>

                                <hr class="dropdown-divider">

                            </li>


                            <!-- LOGOUT -->

                            <li>

                                <form
                                    action="<?= e(APP_URL) ?>/auth/logout.php"
                                    method="POST"
                                    class="m-0"
                                >

                                    <?= csrf_field() ?>


                                    <button
                                        type="submit"
                                        class="dropdown-item text-danger"
                                        style="border: none; background: none; width: 100%; text-align: left;"
                                    >

                                        <i
                                            class="bi bi-box-arrow-right me-2"
                                        ></i>

                                        <?= e(__t('logout')) ?>

                                    </button>

                                </form>

                            </li>


                        </ul>

                    </li>


                <?php else: ?>


                    <!-- =================================================
                         LOGIN
                         ================================================= -->

                    <li class="nav-item">

                        <a
                            class="nav-link"
                            href="<?= e(APP_URL) ?>/auth/login.php"
                        >

                            <?= e(__t('login')) ?>

                        </a>

                    </li>


                    <!-- =================================================
                         REGISTER
                         ================================================= -->

                    <li class="nav-item">

                        <a
                            class="btn btn-primary btn-sm ms-lg-2 px-3"
                            href="<?= e(APP_URL) ?>/auth/register.php"
                        >

                            <?= e(__t('register')) ?>

                        </a>

                    </li>


                <?php endif; ?>


            </ul>

        </div>

    </div>

</nav>


<!-- =========================================================
     FLASH MESSAGE
     ========================================================= -->

<?php

$flash = get_flash();

if ($flash):

?>

    <div class="container mt-3">

        <div
            class="alert alert-<?=
                e(
                    $flash['type'] === 'error'
                        ? 'danger'
                        : $flash['type']
                )
            ?> alert-dismissible fade show"
            role="alert"
        >

            <?= e($flash['message']) ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>

        </div>

    </div>

<?php endif; ?>