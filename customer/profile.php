<?php
/**
 * customer/profile.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => current_user_id()]);
$user = $stmt->fetch();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (mb_strlen($fullname) < 3) {
        $errors[] = 'Full name must be at least 3 characters.';
    }
    if ($phone !== '' && !preg_match('/^\+?[0-9\s-]{9,15}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }

    $newImageFilename = null;
    if (empty($errors) && !empty($_FILES['profile_image']['name'])) {
        try {
            $newImageFilename = handle_image_upload($_FILES['profile_image'], UPLOAD_PROFILE_DIR);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        if ($newImageFilename) {
            if (!empty($user['profile_image']) && file_exists(UPLOAD_PROFILE_DIR . $user['profile_image'])) {
                unlink(UPLOAD_PROFILE_DIR . $user['profile_image']);
            }
            $update = $pdo->prepare('UPDATE users SET fullname = :fullname, phone = :phone, profile_image = :image WHERE id = :id');
            $update->execute(['fullname' => $fullname, 'phone' => $phone ?: null, 'image' => $newImageFilename, 'id' => current_user_id()]);
        } else {
            $update = $pdo->prepare('UPDATE users SET fullname = :fullname, phone = :phone WHERE id = :id');
            $update->execute(['fullname' => $fullname, 'phone' => $phone ?: null, 'id' => current_user_id()]);
        }

        $_SESSION['user_name'] = $fullname;
        $success = true;

        $stmt->execute(['id' => current_user_id()]);
        $user = $stmt->fetch();
    }
}

// Handle password change (separate form)
$passwordErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_new_password'] ?? '';

    if (!password_verify($currentPassword, $user['password'])) {
        $passwordErrors[] = 'Current password is incorrect.';
    }
    if (strlen($newPassword) < 8) {
        $passwordErrors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'New passwords do not match.';
    }

    if (empty($passwordErrors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
        $update->execute(['password' => $hashedPassword, 'id' => current_user_id()]);
        set_flash('success', 'Password changed successfully.');
        redirect('customer/profile.php');
    }
}

$current_page = 'profile';
$page_title = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid" style="max-width: 700px;">
                <h3 class="fw-bold mb-4">My Profile</h3>

                <?php if ($success): ?>
                    <div class="alert alert-success">Profile updated successfully.</div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <form method="POST" action="" enctype="multipart/form-data" novalidate>
                            <?= csrf_field() ?>

                            <div class="text-center mb-4">
                                <img id="profilePreview" src="<?= $user['profile_image'] ? e(UPLOAD_PROFILE_URL . $user['profile_image']) : 'https://placehold.co/120x120?text=' . urlencode(substr($user['fullname'], 0, 1)) ?>" class="avatar-lg mb-2" alt="">
                                <div>
                                    <label class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-camera"></i> Change Photo
                                        <input type="file" name="profile_image" class="d-none image-preview-input" data-preview="#profilePreview" accept="image/*">
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="fullname" class="form-control" value="<?= e($user['fullname']) ?>" required minlength="3">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                                <div class="form-text">Email cannot be changed.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>" placeholder="+255 7XX XXX XXX">
                            </div>

                            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Change Password</h5>

                        <?php if (!empty($passwordErrors)): ?>
                            <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($passwordErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="change_password" value="1">

                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="8">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_new_password" class="form-control" required minlength="8">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary px-4">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
