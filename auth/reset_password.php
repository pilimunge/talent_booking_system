<?php
/**
 * auth/reset_password.php
 * Reset tokens expire after 60 minutes.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

const TOKEN_EXPIRY_MINUTES = 60;

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = $_GET['email'] ?? $_POST['email'] ?? '';
$errors = [];
$tokenValid = false;

if ($token === '' || $email === '') {
    $errors[] = 'Invalid or missing reset link.';
} else {
    $stmt = $pdo->prepare(
        'SELECT * FROM password_resets
         WHERE token = :token AND email = :email
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute(['token' => $token, 'email' => $email]);
    $resetRow = $stmt->fetch();

    if (!$resetRow) {
        $errors[] = 'This reset link is invalid.';
    } elseif (strtotime($resetRow['created_at']) < strtotime('-' . TOKEN_EXPIRY_MINUTES . ' minutes')) {
        $errors[] = 'This reset link has expired. Please request a new one.';
    } else {
        $tokenValid = true;
    }
}

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
        $update->execute(['password' => $hashedPassword, 'email' => $email]);

        // Invalidate all reset tokens for this email
        $cleanup = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
        $cleanup->execute(['email' => $email]);

        set_flash('success', 'Your password has been reset. Please log in.');
        redirect('auth/login.php');
    }
}

$page_title = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold text-center mb-1">Reset Password</h3>
                    <p class="text-secondary text-center mb-4">Choose a new password for your account</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($tokenValid): ?>
                        <form method="POST" action="" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <input type="hidden" name="email" value="<?= e($email) ?>">

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8" autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Reset Password</button>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <a href="<?= e(APP_URL) ?>/auth/forgot_password.php" class="btn btn-outline-primary">Request a new link</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
