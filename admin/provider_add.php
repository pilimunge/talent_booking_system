<?php
/**
 * admin/provider_add.php
 * Admin creates a provider account directly (user + provider profile
 * in one step). Since the admin is entering the details themselves,
 * the new profile is approved by default so it's immediately visible
 * to customers — the admin can pick "Pending" instead if they'd
 * rather review it first via Manage Providers.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';
require_role('admin');

$categories = $pdo->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();

$errors = [];
$old = [
    'fullname' => '',
    'email' => '',
    'phone' => '',
    'category_id' => '',
    'location' => '',
    'price' => '',
    'experience_years' => 0,
    'bio' => '',
    'skills' => '',
    'service_description' => '',
    'approval_status' => 'approved',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['fullname'] = trim($_POST['fullname'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['category_id'] = $_POST['category_id'] ?? '';
    $old['location'] = trim($_POST['location'] ?? '');
    $old['price'] = $_POST['price'] ?? '';
    $old['experience_years'] = $_POST['experience_years'] ?? 0;
    $old['bio'] = trim($_POST['bio'] ?? '');
    $old['skills'] = trim($_POST['skills'] ?? '');
    $old['service_description'] = trim($_POST['service_description'] ?? '');
    $old['approval_status'] = $_POST['approval_status'] ?? 'approved';
    $password = $_POST['password'] ?? '';

    // ----- Validation -----
    if ($old['fullname'] === '' || mb_strlen($old['fullname']) < 3) {
        $errors[] = 'Please enter the provider\'s full name (at least 3 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^\+?[0-9\s-]{9,15}$/', $old['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    $categoryValid = false;
    foreach ($categories as $c) {
        if ((string) $c['id'] === (string) $old['category_id']) {
            $categoryValid = true;
            break;
        }
    }
    if (!$categoryValid) {
        $errors[] = 'Please choose a valid category.';
    }
    if ($old['price'] === '' || !is_numeric($old['price']) || (float) $old['price'] < 0) {
        $errors[] = 'Please enter a valid price.';
    }
    if (!in_array($old['approval_status'], ['approved', 'pending'], true)) {
        $old['approval_status'] = 'approved';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    // ----- Create account + provider profile -----
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $userStmt = $pdo->prepare(
                'INSERT INTO users (fullname, email, phone, password, role, status)
                 VALUES (:fullname, :email, :phone, :password, "provider", "active")'
            );
            $userStmt->execute([
                'fullname' => $old['fullname'],
                'email' => $old['email'],
                'phone' => $old['phone'] ?: null,
                'password' => $hashedPassword,
            ]);
            $newUserId = (int) $pdo->lastInsertId();

            $providerStmt = $pdo->prepare(
                'INSERT INTO providers (
                    user_id, category_id, bio, experience_years, location,
                    price, skills, service_description, approval_status
                ) VALUES (
                    :user_id, :category_id, :bio, :experience_years, :location,
                    :price, :skills, :service_description, :approval_status
                )'
            );
            $providerStmt->execute([
                'user_id' => $newUserId,
                'category_id' => (int) $old['category_id'],
                'bio' => $old['bio'] ?: null,
                'experience_years' => (int) $old['experience_years'],
                'location' => $old['location'] ?: null,
                'price' => (float) $old['price'],
                'skills' => $old['skills'] ?: null,
                'service_description' => $old['service_description'] ?: null,
                'approval_status' => $old['approval_status'],
            ]);
            $newProviderId = (int) $pdo->lastInsertId();

            log_activity($pdo, current_user_id(), "Created provider account #{$newUserId} ({$old['fullname']}).");
            audit_log(
                $pdo,
                'PROVIDER_CREATE',
                "Created provider #{$newProviderId} ({$old['fullname']}) with status \"{$old['approval_status']}\".",
                'provider',
                $newProviderId
            );

            $pdo->commit();

            set_flash('success', $old['fullname'] . ' was added as a provider. Share their login email and the password you set with them.');
            redirect('admin/providers.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not create the provider: ' . $e->getMessage();
        }
    }
}

$current_page = 'providers';
$page_title = 'Add Provider';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid" style="max-width: 800px;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold mb-0">Add Provider</h3>
                    <a href="<?= e(APP_URL) ?>/admin/providers.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Manage Providers
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <?= csrf_field() ?>

                            <h6 class="fw-bold text-secondary text-uppercase small mb-3">Account details</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="fullname" class="form-control" value="<?= e($old['fullname']) ?>" required minlength="3">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>" placeholder="+255 7XX XXX XXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Temporary Password</label>
                                    <input type="text" name="password" class="form-control" minlength="8" required>
                                    <div class="form-text">At least 8 characters. Share this with the provider so they can log in.</div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-secondary text-uppercase small mb-3">Provider profile</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Choose category...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= (int) $cat['id'] ?>" <?= (string) $old['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" value="<?= e($old['location']) ?>" placeholder="e.g. Dar es Salaam">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Price (TZS)</label>
                                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e((string) $old['price']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Experience (years)</label>
                                    <input type="number" min="0" max="80" name="experience_years" class="form-control" value="<?= (int) $old['experience_years'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Initial Status</label>
                                    <select name="approval_status" class="form-select">
                                        <option value="approved" <?= $old['approval_status'] === 'approved' ? 'selected' : '' ?>>Approved (visible immediately)</option>
                                        <option value="pending" <?= $old['approval_status'] === 'pending' ? 'selected' : '' ?>>Pending (review later)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Skills <span class="text-secondary">(comma-separated)</span></label>
                                    <input type="text" name="skills" class="form-control" value="<?= e($old['skills']) ?>" placeholder="e.g. Wedding MC, Corporate Events">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Bio</label>
                                    <textarea name="bio" class="form-control" rows="3"><?= e($old['bio']) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Service Description</label>
                                    <textarea name="service_description" class="form-control" rows="3"><?= e($old['service_description']) ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="<?= e(APP_URL) ?>/admin/providers.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Create Provider
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
