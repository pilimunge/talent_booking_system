<?php
/**
 * admin/settings.php
 * Manage the single-row `settings` table:
 * site name, logo, contact info, and commission rate.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

/*
|--------------------------------------------------------------------------
| Get Settings
|--------------------------------------------------------------------------
*/
$settings = $pdo->query('SELECT * FROM settings LIMIT 1')->fetch();

/*
|--------------------------------------------------------------------------
| Create Default Settings Row If None Exists
|--------------------------------------------------------------------------
*/
if (!$settings) {
    $pdo->exec(
        'INSERT INTO settings
            (site_name, commission_rate)
         VALUES
            ("Talent Booking System", 10.00)'
    );

    $settings = $pdo->query('SELECT * FROM settings LIMIT 1')->fetch();
}

$errors = [];
$success = false;

/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $siteName = trim($_POST['site_name'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');

    /*
     * Commission rate
     * Example:
     * 10 = 10%
     * 15 = 15%
     * 20 = 20%
     */
    $commissionRateInput = trim($_POST['commission_rate'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($siteName === '') {
        $errors[] = 'Site name is required.';
    }

    if (
        $contactEmail !== '' &&
        !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Please enter a valid contact email.';
    }

    if ($commissionRateInput === '') {
        $errors[] = 'Commission rate is required.';
    } elseif (!is_numeric($commissionRateInput)) {
        $errors[] = 'Commission rate must be a number.';
    } else {

        $commissionRate = (float) $commissionRateInput;

        if ($commissionRate < 0 || $commissionRate > 100) {
            $errors[] = 'Commission rate must be between 0% and 100%.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Logo Upload
    |--------------------------------------------------------------------------
    */

    $newLogoFilename = null;

    if (
        empty($errors) &&
        !empty($_FILES['logo']['name'])
    ) {
        try {

            // Reuse the profile images directory for the logo.
            $newLogoFilename = handle_image_upload(
                $_FILES['logo'],
                UPLOAD_PROFILE_DIR
            );

        } catch (RuntimeException $e) {

            $errors[] = $e->getMessage();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save Settings
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $sql = '
            UPDATE settings
            SET
                site_name = :site_name,
                contact_email = :contact_email,
                contact_phone = :contact_phone,
                commission_rate = :commission_rate
        ';

        $params = [
            'site_name'       => $siteName,
            'contact_email'  => $contactEmail !== '' ? $contactEmail : null,
            'contact_phone'  => $contactPhone !== '' ? $contactPhone : null,
            'commission_rate' => $commissionRate,
            'id'              => $settings['id']
        ];

        /*
        |--------------------------------------------------------------------------
        | Update Logo Only If New Logo Was Uploaded
        |--------------------------------------------------------------------------
        */

        if ($newLogoFilename) {

            $sql .= ', logo = :logo';

            $params['logo'] = $newLogoFilename;
        }

        $sql .= ' WHERE id = :id';

        /*
        |--------------------------------------------------------------------------
        | Execute Update
        |--------------------------------------------------------------------------
        */

        $update = $pdo->prepare($sql);
        $update->execute($params);

        /*
        |--------------------------------------------------------------------------
        | Log Activity
        |--------------------------------------------------------------------------
        */

        log_activity(
            $pdo,
            current_user_id(),
            'Updated site settings including commission rate.'
        );

        $success = true;

        /*
        |--------------------------------------------------------------------------
        | Reload Settings
        |--------------------------------------------------------------------------
        */

        $settings = $pdo
            ->query('SELECT * FROM settings LIMIT 1')
            ->fetch();
    }
}

/*
|--------------------------------------------------------------------------
| Page Variables
|--------------------------------------------------------------------------
*/

$current_page = 'settings';
$page_title = 'Website Settings';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">

        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">

            <div class="container-fluid" style="max-width: 700px;">

                <h3 class="fw-bold mb-4">
                    Website Settings
                </h3>

                <!-- Success Message -->
                <?php if ($success): ?>

                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        Settings updated successfully.
                    </div>

                <?php endif; ?>


                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>

                    <div class="alert alert-danger">

                        <ul class="mb-0 ps-3">

                            <?php foreach ($errors as $error): ?>

                                <li><?= e($error) ?></li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">

                        <form
                            method="POST"
                            action=""
                            enctype="multipart/form-data"
                            novalidate
                        >

                            <?= csrf_field() ?>


                            <!-- Current Logo -->
                            <?php if (!empty($settings['logo'])): ?>

                                <div class="mb-3 text-center">

                                    <img
                                        src="<?= e(UPLOAD_PROFILE_URL . $settings['logo']) ?>"
                                        style="max-height:80px;"
                                        alt="Current logo"
                                        class="img-fluid"
                                    >

                                </div>

                            <?php endif; ?>


                            <!-- Logo -->
                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Site Logo
                                </label>

                                <input
                                    type="file"
                                    name="logo"
                                    class="form-control"
                                    accept="image/*"
                                >

                                <div class="form-text">
                                    Upload a new logo only if you want to replace the current one.
                                </div>

                            </div>


                            <!-- Site Name -->
                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Site Name
                                </label>

                                <input
                                    type="text"
                                    name="site_name"
                                    class="form-control"
                                    value="<?= e($settings['site_name']) ?>"
                                    required
                                >

                            </div>


                            <!-- Contact Email -->
                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Contact Email
                                </label>

                                <input
                                    type="email"
                                    name="contact_email"
                                    class="form-control"
                                    value="<?= e($settings['contact_email'] ?? '') ?>"
                                >

                            </div>


                            <!-- Contact Phone -->
                            <div class="mb-3">

                                <label class="form-label fw-semibold">
                                    Contact Phone
                                </label>

                                <input
                                    type="text"
                                    name="contact_phone"
                                    class="form-control"
                                    value="<?= e($settings['contact_phone'] ?? '') ?>"
                                >

                            </div>


                            <!-- Commission Rate -->
                            <div class="mb-4">

                                <label class="form-label fw-semibold">
                                    Commission Rate
                                </label>

                                <div class="input-group">

                                    <input
                                        type="number"
                                        name="commission_rate"
                                        class="form-control"
                                        value="<?= e($settings['commission_rate'] ?? '10.00') ?>"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        required
                                    >

                                    <span class="input-group-text">
                                        %
                                    </span>

                                </div>


                            </div>


                            <!-- Save Button -->
                            <button
                                type="submit"
                                class="btn btn-primary px-4"
                            >
                                <i class="bi bi-save"></i>
                                Save Settings
                            </button>

                        </form>

                    </div>

                </div>


        


                <!-- Existing System Note -->
                
            </div>

        </div>

    </div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
