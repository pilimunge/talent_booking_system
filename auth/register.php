<?php
/**
 * auth/register.php
 * New account registration for customers and providers.
 *
 * Providers are created with approval handled at the `providers`
 * table level later. They complete their provider profile after
 * first login.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';


/*
|--------------------------------------------------------------------------
| Prevent already logged-in users from opening registration page
|--------------------------------------------------------------------------
*/

if (is_logged_in()) {
    redirect('index.php');
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];

$old = [
    'fullname' => '',
    'email'    => '',
    'phone'    => '',
    'role'     => 'customer',
];


/*
|--------------------------------------------------------------------------
| Handle Registration Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Verify CSRF token
    |--------------------------------------------------------------------------
    */

    verify_csrf();


    /*
    |--------------------------------------------------------------------------
    | Get submitted values
    |--------------------------------------------------------------------------
    */

    $old['fullname'] = trim($_POST['fullname'] ?? '');

    $old['email'] = trim($_POST['email'] ?? '');

    $old['phone'] = trim($_POST['phone'] ?? '');

    $old['role'] = $_POST['role'] ?? 'customer';

    $password = $_POST['password'] ?? '';

    $confirmPassword = $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate Full Name
    |--------------------------------------------------------------------------
    */

    if (
        $old['fullname'] === ''
        || mb_strlen($old['fullname']) < 3
    ) {

        $errors[] =
            'Please enter your full name (at least 3 characters).';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {

        $errors[] =
            'Please enter a valid email address.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Phone Number
    |--------------------------------------------------------------------------
    */

    if (
        $old['phone'] !== ''
        && !preg_match(
            '/^\+?[0-9\s-]{9,15}$/',
            $old['phone']
        )
    ) {

        $errors[] =
            'Please enter a valid phone number.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Account Type
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $old['role'],
            ['customer', 'provider'],
            true
        )
    ) {

        $errors[] =
            'Please select a valid account type.';
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Password
    |--------------------------------------------------------------------------
    */

    if (strlen($password) < 8) {

        $errors[] =
            'Password must be at least 8 characters long.';
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Password
    |--------------------------------------------------------------------------
    */

    if ($password !== $confirmPassword) {

        $errors[] =
            'Passwords do not match.';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Existing Email
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'email' => $old['email']
        ]);

        if ($stmt->fetch()) {

            $errors[] =
                'An account with this email already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Account
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        /*
        |--------------------------------------------------------------------------
        | Hash Password
        |--------------------------------------------------------------------------
        */

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        /*
        |--------------------------------------------------------------------------
        | Insert User
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            'INSERT INTO users
                (
                    fullname,
                    email,
                    phone,
                    password,
                    role,
                    status
                )
             VALUES
                (
                    :fullname,
                    :email,
                    :phone,
                    :password,
                    :role,
                    "active"
                )'
        );

        $stmt->execute([
            'fullname' => $old['fullname'],
            'email'    => $old['email'],
            'phone'    => $old['phone'] ?: null,
            'password' => $hashedPassword,
            'role'     => $old['role'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Get New User ID
        |--------------------------------------------------------------------------
        */

        $newUserId = (int) $pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | Activity Log
        |--------------------------------------------------------------------------
        */

        log_activity(
            $pdo,
            $newUserId,
            'Registered a new ' . $old['role'] . ' account.'
        );


        /*
        |--------------------------------------------------------------------------
        | Regenerate Session ID
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | This must happen BEFORE header.php is loaded.
        |
        */

        session_regenerate_id(true);


        /*
        |--------------------------------------------------------------------------
        | Store User Information in Session
        |--------------------------------------------------------------------------
        */

        $_SESSION['user_id'] =
            $newUserId;

        $_SESSION['user_role'] =
            $old['role'];

        $_SESSION['user_name'] =
            $old['fullname'];


        /*
        |--------------------------------------------------------------------------
        | Provider Registration
        |--------------------------------------------------------------------------
        */

        if ($old['role'] === 'provider') {

            set_flash(
                'success',
                'Welcome! Please complete your provider profile so customers can find and book you.'
            );

            redirect(
                'provider/profile.php'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Customer Registration
        |--------------------------------------------------------------------------
        */

        set_flash(
            'success',
            'Welcome to ' . APP_NAME . '! Your account has been created.'
        );

        redirect(
            'customer/dashboard.php'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Page Display
|--------------------------------------------------------------------------
|
| IMPORTANT:
| header.php is loaded ONLY after all registration processing.
|
*/

$page_title = 'Create Account';

require_once __DIR__ . '/../includes/header.php';

?>


<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-7 col-lg-6">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4 p-md-5">


                    <!-- Page Title -->

                    <h3 class="fw-bold text-center mb-1">
                        Create Your Account
                    </h3>

                    <p class="text-secondary text-center mb-4">
                        Join as a customer or as a talent provider
                    </p>


                    <!-- Error Messages -->

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


                    <!-- Registration Form -->

                    <form
                        method="POST"
                        action=""
                        novalidate
                    >

                        <?= csrf_field() ?>


                        <!-- Account Type -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                I am registering as
                            </label>

                            <div
                                class="btn-group w-100"
                                role="group"
                            >

                                <!-- Customer -->

                                <input
                                    type="radio"
                                    class="btn-check"
                                    name="role"
                                    id="roleCustomer"
                                    value="customer"
                                    <?= $old['role'] === 'customer'
                                        ? 'checked'
                                        : '' ?>
                                >

                                <label
                                    class="btn btn-outline-primary"
                                    for="roleCustomer"
                                >
                                    <i class="bi bi-person"></i>
                                    Customer
                                </label>


                                <!-- Provider -->

                                <input
                                    type="radio"
                                    class="btn-check"
                                    name="role"
                                    id="roleProvider"
                                    value="provider"
                                    <?= $old['role'] === 'provider'
                                        ? 'checked'
                                        : '' ?>
                                >

                                <label
                                    class="btn btn-outline-primary"
                                    for="roleProvider"
                                >
                                    <i class="bi bi-mic"></i>
                                    Talent Provider
                                </label>

                            </div>

                        </div>


                        <!-- Full Name -->

                        <div class="mb-3">

                            <label
                                for="fullname"
                                class="form-label"
                            >
                                Full Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="fullname"
                                name="fullname"
                                value="<?= e($old['fullname']) ?>"
                                required
                                minlength="3"
                            >

                        </div>


                        <!-- Email -->

                        <div class="mb-3">

                            <label
                                for="email"
                                class="form-label"
                            >
                                Email Address
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?= e($old['email']) ?>"
                                required
                            >

                        </div>


                        <!-- Phone -->

                        <div class="mb-3">

                            <label
                                for="phone"
                                class="form-label"
                            >
                                Phone Number

                                <span class="text-secondary">
                                    (optional)
                                </span>

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?= e($old['phone']) ?>"
                                placeholder="+255 7XX XXX XXX"
                            >

                        </div>


                        <!-- Passwords -->

                        <div class="row">

                            <!-- Password -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="password"
                                    class="form-label"
                                >
                                    Password
                                </label>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    required
                                    minlength="8"
                                >

                            </div>


                            <!-- Confirm Password -->

                            <div class="col-md-6 mb-3">

                                <label
                                    for="confirm_password"
                                    class="form-label"
                                >
                                    Confirm Password
                                </label>

                                <input
                                    type="password"
                                    class="form-control"
                                    id="confirm_password"
                                    name="confirm_password"
                                    required
                                    minlength="8"
                                >

                            </div>

                        </div>


                        <!-- Submit -->

                        <button
                            type="submit"
                            class="btn btn-primary w-100 py-2 fw-semibold"
                        >
                            Create Account
                        </button>

                    </form>


                    <!-- Login Link -->

                    <p class="text-center mt-4 mb-0 small">

                        Already have an account?

                        <a
                            href="<?= e(APP_URL) ?>/auth/login.php"
                        >
                            Log in
                        </a>

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>