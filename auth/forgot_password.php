<?php
/**
 * auth/forgot_password.php
 *
 * NOTE: This demo does not send real emails (no SMTP configured). Instead,
 * after a valid email is submitted, it stores a reset token in the
 * `password_resets` table and displays the reset link directly on screen
 * so you can test the flow immediately in XAMPP without mail setup.
 * In production, replace the "display link" block with an actual email send
 * (e.g. via PHPMailer) and remove the on-screen link.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Always show the same success message whether or not the email
        // exists, to avoid leaking which emails are registered.
        if ($user) {
            $token = bin2hex(random_bytes(32));

            $insert = $pdo->prepare('INSERT INTO password_resets (email, token) VALUES (:email, :token)');
            $insert->execute(['email' => $email, 'token' => $token]);

            $resetLink = APP_URL . '/auth/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
        }

        set_flash('success', 'If that email exists in our system, a password reset link has been generated.');
    }
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold text-center mb-1">Forgot Password</h3>
                    <p class="text-secondary text-center mb-4">Enter your email to receive a reset link</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($resetLink): ?>
                        <div class="alert alert-info small">
                            <strong>Demo mode (no email server configured):</strong><br>
                            Click below to reset your password:<br>
                            <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Send Reset Link</button>
                    </form>

                    <p class="text-center mt-4 mb-0 small">
                        <a href="<?= e(APP_URL) ?>/auth/login.php">Back to login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
