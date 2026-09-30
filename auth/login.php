<?php
/**
 * auth/login.php
 * User login page
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';


/*
|--------------------------------------------------------------------------
| Prevent already logged-in users from opening login page
|--------------------------------------------------------------------------
*/

if (is_logged_in()) {
    redirect('index.php');
}


$errors = [];
$oldEmail = '';


/*
|--------------------------------------------------------------------------
| Handle Login Form
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

    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);


    /*
    |--------------------------------------------------------------------------
    | Identifiers used for rate limiting
    |--------------------------------------------------------------------------
    */

    $emailKey = mb_strtolower($oldEmail);
    $ipKey = $_SERVER['REMOTE_ADDR'] ?? 'unknown';


    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    $locked = (
        $emailKey !== ''
        && is_login_locked($pdo, 'email', $emailKey)
    ) || is_login_locked($pdo, 'ip', $ipKey);


    if ($locked) {

        $errors[] =
            'Too many failed login attempts. Please wait '
            . LOGIN_LOCKOUT_MINUTES
            . ' minutes and try again.';

    }


    /*
    |--------------------------------------------------------------------------
    | Validate input
    |--------------------------------------------------------------------------
    */

    elseif ($oldEmail === '' || $password === '') {

        $errors[] = 'Please enter both email and password.';

    }


    /*
    |--------------------------------------------------------------------------
    | Find user
    |--------------------------------------------------------------------------
    */

    else {

        $stmt = $pdo->prepare(
            'SELECT *
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'email' => $oldEmail
        ]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Check email and password
        |--------------------------------------------------------------------------
        */

        if (
            !$user
            || !password_verify($password, $user['password'])
        ) {

            /*
            |--------------------------------------------------------------------------
            | Do not reveal whether email or password is wrong
            |--------------------------------------------------------------------------
            */

            $errors[] = 'Invalid email or password.';


            /*
            |--------------------------------------------------------------------------
            | Record failed login attempt
            |--------------------------------------------------------------------------
            */

            record_login_attempt(
                $pdo,
                'email',
                $emailKey
            );

            record_login_attempt(
                $pdo,
                'ip',
                $ipKey
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Check suspended account
        |--------------------------------------------------------------------------
        */

        elseif ($user['status'] === 'suspended') {

            $errors[] =
                'Your account has been suspended. Please contact support.';

        }


        /*
        |--------------------------------------------------------------------------
        | Check inactive account
        |--------------------------------------------------------------------------
        */

        elseif ($user['status'] === 'inactive') {

            $errors[] =
                'Your account is inactive. Please contact support.';

        }


        /*
        |--------------------------------------------------------------------------
        | Check removed account
        |--------------------------------------------------------------------------
        */

        elseif ($user['status'] === 'removed') {

            $errors[] =
                'Your account has been removed. Please contact support.';

        }


        /*
        |--------------------------------------------------------------------------
        | Successful Login
        |--------------------------------------------------------------------------
        */

        else {

            /*
            |--------------------------------------------------------------------------
            | Regenerate session ID
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | This happens BEFORE header.php is loaded.
            |
            */

            session_regenerate_id(true);


            /*
            |--------------------------------------------------------------------------
            | Store user information in session
            |--------------------------------------------------------------------------
            */

            $_SESSION['user_id'] = (int) $user['id'];

            $_SESSION['user_role'] = $user['role'];

            $_SESSION['user_name'] = $user['fullname'];


            /*
            |--------------------------------------------------------------------------
            | Clear failed login attempts
            |--------------------------------------------------------------------------
            */

            clear_login_attempts(
                $pdo,
                'email',
                $emailKey
            );

            clear_login_attempts(
                $pdo,
                'ip',
                $ipKey
            );


            /*
            |--------------------------------------------------------------------------
            | Audit Log
            |--------------------------------------------------------------------------
            */

            audit_log(
                $pdo,
                'LOGIN',
                'User logged into the system.',
                'user',
                (int) $user['id']
            );


            /*
            |--------------------------------------------------------------------------
            | Remember Me
            |--------------------------------------------------------------------------
            */

            if ($rememberMe) {

                /*
                |--------------------------------------------------------------------------
                | Generate secure random token
                |--------------------------------------------------------------------------
                */

                $token = bin2hex(
                    random_bytes(32)
                );


                /*
                |--------------------------------------------------------------------------
                | Store only SHA-256 hash in database
                |--------------------------------------------------------------------------
                */

                $hashedToken = hash(
                    'sha256',
                    $token
                );


                /*
                |--------------------------------------------------------------------------
                | Update user's remember token
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare(
                    'UPDATE users
                     SET remember_token = :token
                     WHERE id = :id'
                );

                $update->execute([
                    'token' => $hashedToken,
                    'id' => $user['id']
                ]);


                /*
                |--------------------------------------------------------------------------
                | Determine HTTPS
                |--------------------------------------------------------------------------
                */

                $isHttps =
                    (
                        !empty($_SERVER['HTTPS'])
                        && $_SERVER['HTTPS'] !== 'off'
                    )
                    || (
                        ($_SERVER['SERVER_PORT'] ?? null) == 443
                    );


                /*
                |--------------------------------------------------------------------------
                | Set Remember Me Cookie
                |--------------------------------------------------------------------------
                */

                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires'  => time() + REMEMBER_ME_LIFETIME,
                        'path'     => '/',
                        'domain'   => '',
                        'secure'   => $isHttps,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Existing Activity Log
            |--------------------------------------------------------------------------
            */

            log_activity(
                $pdo,
                $user['id'],
                'Logged in.'
            );


            /*
            |--------------------------------------------------------------------------
            | Redirect according to user role
            |--------------------------------------------------------------------------
            */

            $redirectMap = [
                'admin'    => 'admin/dashboard.php',
                'provider' => 'provider/dashboard.php',
                'customer' => 'customer/dashboard.php',
            ];


            redirect(
                $redirectMap[$user['role']] ?? 'index.php'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
|
| IMPORTANT:
| header.php is loaded ONLY after all login processing above.
|
*/

$page_title = 'Login';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-md-6 col-lg-5">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4 p-md-5">

                    <!-- Page Title -->

                    <h3 class="fw-bold text-center mb-1">
                        Welcome Back
                    </h3>

                    <p class="text-secondary text-center mb-4">
                        Log in to your account
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


                    <!-- Login Form -->

                    <form
                        method="POST"
                        action=""
                        novalidate
                    >

                        <?= csrf_field() ?>


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
                                value="<?= e($oldEmail) ?>"
                                required
                                autofocus
                            >

                        </div>


                        <!-- Password -->

                        <div class="mb-3">

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
                            >

                        </div>


                        <!-- Remember Me + Forgot Password -->

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="remember_me"
                                    name="remember_me"
                                >

                                <label
                                    class="form-check-label small"
                                    for="remember_me"
                                >
                                    Remember Me
                                </label>

                            </div>


                            <a
                                href="<?= e(APP_URL) ?>/auth/forgot_password.php"
                                class="small"
                            >
                                Forgot password?
                            </a>

                        </div>


                        <!-- Login Button -->

                        <button
                            type="submit"
                            class="btn btn-primary w-100 py-2 fw-semibold"
                        >
                            Log In
                        </button>

                    </form>


                    <!-- Register -->

                    <p class="text-center mt-4 mb-0 small">

                        Don't have an account?

                        <a
                            href="<?= e(APP_URL) ?>/auth/register.php"
                        >
                            Sign up
                        </a>

                    </p>

                </div>

            </div>


            <!-- Demo Admin -->

            <div class="alert alert-light border mt-3 small">

                <strong>Demo Admin:</strong>

                admin@talentbooking.com / Admin@123

            </div>

        </div>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>