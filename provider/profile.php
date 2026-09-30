<?php
/**
 * provider/profile.php
 * Create or edit the provider's extended profile.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('provider');

$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$userStmt->execute(['id' => current_user_id()]);
$user = $userStmt->fetch();

$profileStmt = $pdo->prepare(
    'SELECT * FROM providers WHERE user_id = :uid LIMIT 1'
);
$profileStmt->execute(['uid' => current_user_id()]);
$profile = $profileStmt->fetch();

$categories = $pdo->query(
    'SELECT * FROM categories ORDER BY category_name'
)->fetchAll();

$errors = [];
$success = false;

$old = [
    'category_id' => $profile['category_id'] ?? '',
    'bio' => $profile['bio'] ?? '',
    'experience_years' => $profile['experience_years'] ?? 0,
    'location' => $profile['location'] ?? '',
    'price' => $profile['price'] ?? '',
    'skills' => $profile['skills'] ?? '',
    'service_description' => $profile['service_description'] ?? '',
    'video_links' => $profile['video_links'] ?? '',
];


/*
|--------------------------------------------------------------------------
| UPDATE / CREATE PROVIDER PROFILE
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !isset($_POST['change_password'])
) {

    verify_csrf();

    foreach (
        [
            'category_id',
            'bio',
            'experience_years',
            'location',
            'price',
            'skills',
            'service_description',
            'video_links'
        ] as $field
    ) {
        $old[$field] = trim($_POST[$field] ?? '');
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */
    if (
        $old['category_id'] === '' ||
        !is_numeric($old['category_id'])
    ) {
        $errors[] = 'Please select a category.';
    }

    if ($old['location'] === '') {
        $errors[] = 'Please enter your location.';
    }

    if (
        !is_numeric($old['price']) ||
        (float) $old['price'] < 0
    ) {
        $errors[] = 'Please enter a valid price.';
    }

    if (
        !is_numeric($old['experience_years']) ||
        (int) $old['experience_years'] < 0
    ) {
        $errors[] = 'Please enter valid years of experience.';
    }


    /*
    |--------------------------------------------------------------------------
    | COVER IMAGE
    |--------------------------------------------------------------------------
    */
    $newCoverFilename = null;

    if (
        empty($errors) &&
        !empty($_FILES['cover_image']['name'])
    ) {
        try {

            $newCoverFilename = handle_image_upload(
                $_FILES['cover_image'],
                UPLOAD_COVER_DIR
            );

        } catch (RuntimeException $e) {

            $errors[] = $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | PROFILE IMAGE
    |--------------------------------------------------------------------------
    */
    $newProfileImageFilename = null;

    if (
        empty($errors) &&
        !empty($_FILES['profile_image']['name'])
    ) {
        try {

            $newProfileImageFilename = handle_image_upload(
                $_FILES['profile_image'],
                UPLOAD_PROFILE_DIR
            );

        } catch (RuntimeException $e) {

            $errors[] = $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE PROFILE
    |--------------------------------------------------------------------------
    */
    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | UPDATE USER PROFILE IMAGE
            |--------------------------------------------------------------------------
            */
            if ($newProfileImageFilename) {

                if (
                    !empty($user['profile_image']) &&
                    file_exists(
                        UPLOAD_PROFILE_DIR .
                        $user['profile_image']
                    )
                ) {
                    unlink(
                        UPLOAD_PROFILE_DIR .
                        $user['profile_image']
                    );
                }

                $updateUser = $pdo->prepare(
                    'UPDATE users
                     SET profile_image = :image
                     WHERE id = :id'
                );

                $updateUser->execute([
                    'image' => $newProfileImageFilename,
                    'id' => current_user_id()
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE EXISTING PROVIDER PROFILE
            |--------------------------------------------------------------------------
            */
            if ($profile) {

                /*
                 * If the profile was rejected and the provider
                 * edits/resubmits it, send it back to pending.
                 */
                $newApprovalStatus =
                    $profile['approval_status'] === 'rejected'
                        ? 'pending'
                        : $profile['approval_status'];


                /*
                 * Delete old cover image if a new one was uploaded.
                 */
                if (
                    $newCoverFilename &&
                    !empty($profile['cover_image']) &&
                    file_exists(
                        UPLOAD_COVER_DIR .
                        $profile['cover_image']
                    )
                ) {
                    unlink(
                        UPLOAD_COVER_DIR .
                        $profile['cover_image']
                    );
                }


                $sql = '
                    UPDATE providers
                    SET
                        category_id = :category_id,
                        bio = :bio,
                        experience_years = :experience_years,
                        location = :location,
                        price = :price,
                        skills = :skills,
                        service_description = :service_description,
                        video_links = :video_links,
                        approval_status = :approval_status
                ';

                $params = [
                    'category_id' => $old['category_id'],
                    'bio' => $old['bio'] ?: null,
                    'experience_years' => $old['experience_years'],
                    'location' => $old['location'],
                    'price' => $old['price'],
                    'skills' => $old['skills'] ?: null,
                    'service_description' =>
                        $old['service_description'] ?: null,
                    'video_links' =>
                        $old['video_links'] ?: null,
                    'approval_status' => $newApprovalStatus,
                    'id' => $profile['id'],
                ];


                /*
                 * Add cover image only when a new image was uploaded.
                 */
                if ($newCoverFilename) {

                    $sql .= ',
                        cover_image = :cover_image
                    ';

                    $params['cover_image'] =
                        $newCoverFilename;
                }


                $sql .= ' WHERE id = :id';


                $update = $pdo->prepare($sql);
                $update->execute($params);


                /*
                 |--------------------------------------------------------------------------
                 | AUDIT: UPDATED PROFILE
                 |--------------------------------------------------------------------------
                 */
                audit_log(
                    $pdo,
                    'Updated Profile',
                    'Updated provider profile (location: ' . ($old['location'] ?: 'not set')
                        . ', price: ' . format_money($old['price']) . ').',
                    'provider',
                    (int) $profile['id']
                );


                $success = true;

            }


            /*
            |--------------------------------------------------------------------------
            | CREATE NEW PROVIDER PROFILE
            |--------------------------------------------------------------------------
            */
            else {

                $insert = $pdo->prepare(
                    'INSERT INTO providers (
                        user_id,
                        category_id,
                        bio,
                        experience_years,
                        location,
                        price,
                        cover_image,
                        skills,
                        service_description,
                        video_links,
                        approval_status
                    )
                    VALUES (
                        :user_id,
                        :category_id,
                        :bio,
                        :experience_years,
                        :location,
                        :price,
                        :cover_image,
                        :skills,
                        :service_description,
                        :video_links,
                        "pending"
                    )'
                );

                $insert->execute([
                    'user_id' => current_user_id(),
                    'category_id' => $old['category_id'],
                    'bio' => $old['bio'] ?: null,
                    'experience_years' =>
                        $old['experience_years'],
                    'location' => $old['location'],
                    'price' => $old['price'],
                    'cover_image' => $newCoverFilename,
                    'skills' => $old['skills'] ?: null,
                    'service_description' =>
                        $old['service_description'] ?: null,
                    'video_links' =>
                        $old['video_links'] ?: null,
                ]);


                /*
                 * Get newly created provider profile ID.
                 */
                $newProviderId =
                    (int) $pdo->lastInsertId();


                /*
                 |--------------------------------------------------------------------------
                 | AUDIT: CREATED PROFILE
                 |--------------------------------------------------------------------------
                 */
                audit_log(
                    $pdo,
                    'Created Provider Profile',
                    'Completed provider profile setup (location: ' . ($old['location'] ?: 'not set')
                        . ', price: ' . format_money($old['price']) . ').',
                    'provider',
                    $newProviderId
                );


                $success = true;
            }


            /*
            |--------------------------------------------------------------------------
            | EXISTING ACTIVITY LOG
            |--------------------------------------------------------------------------
            */
            log_activity(
                $pdo,
                current_user_id(),
                $profile
                    ? 'Updated provider profile.'
                    : 'Created provider profile.'
            );


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */
            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | REFRESH DATA
            |--------------------------------------------------------------------------
            */
            $profileStmt->execute([
                'uid' => current_user_id()
            ]);

            $profile = $profileStmt->fetch();


            $userStmt->execute([
                'id' => current_user_id()
            ]);

            $user = $userStmt->fetch();


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] =
                'Unable to save your profile. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/
$passwordErrors = [];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['change_password'])
) {

    verify_csrf();

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_new_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | PASSWORD VALIDATION
    |--------------------------------------------------------------------------
    */
    if (
        !password_verify(
            $currentPassword,
            $user['password']
        )
    ) {
        $passwordErrors[] =
            'Current password is incorrect.';
    }

    if (strlen($newPassword) < 8) {

        $passwordErrors[] =
            'New password must be at least 8 characters.';
    }

    if ($newPassword !== $confirmPassword) {

        $passwordErrors[] =
            'New passwords do not match.';
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE NEW PASSWORD
    |--------------------------------------------------------------------------
    */
    if (empty($passwordErrors)) {

        $hashedPassword =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


        $update = $pdo->prepare(
            'UPDATE users
             SET password = :password
             WHERE id = :id'
        );

        $update->execute([
            'password' => $hashedPassword,
            'id' => current_user_id()
        ]);


        /*
         |--------------------------------------------------------------------------
         | AUDIT: PASSWORD CHANGE
         |--------------------------------------------------------------------------
         |
         | IMPORTANT:
         | Do NOT store the old password, new password,
         | password hash, or any secret in the audit log.
         |
         */
        audit_log(
            $pdo,
            'Changed Password',
            'Account password was changed.',
            'user',
            current_user_id()
        );


        log_activity(
            $pdo,
            current_user_id(),
            'Changed account password.'
        );


        set_flash(
            'success',
            'Password changed successfully.'
        );

        redirect('provider/profile.php');
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

            <div class="container-fluid" style="max-width: 800px;">

                <h3 class="fw-bold mb-1">
                    My Provider Profile
                </h3>


                <?php if ($profile): ?>

                    <p class="mb-4">
                        Approval status:

                        <span class="badge <?= approval_status_badge($profile['approval_status']) ?>">
                            <?= ucfirst(e($profile['approval_status'])) ?>
                        </span>
                    </p>

                <?php else: ?>

                    <p class="text-secondary mb-4">
                        Complete your profile below to start receiving bookings.
                    </p>

                <?php endif; ?>


                <?php if ($success): ?>

                    <div class="alert alert-success">
                        Profile saved successfully!
                    </div>

                <?php endif; ?>


                <?php if (!empty($errors)): ?>

                    <div class="alert alert-danger">

                        <ul class="mb-0 ps-3">

                            <?php foreach ($errors as $error): ?>

                                <li><?= e($error) ?></li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <!-- PROFILE CARD -->
                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-body p-4">

                        <form
                            method="POST"
                            action=""
                            enctype="multipart/form-data"
                            novalidate
                        >

                            <?= csrf_field() ?>


                            <!-- PROFILE IMAGE -->
                            <div class="text-center mb-4">

                                <img
                                    id="profilePreview"
                                    src="<?= $user['profile_image']
                                        ? e(UPLOAD_PROFILE_URL . $user['profile_image'])
                                        : 'https://placehold.co/120x120?text=' .
                                          urlencode(
                                              substr(
                                                  $user['fullname'],
                                                  0,
                                                  1
                                              )
                                          )
                                    ?>"
                                    class="avatar-lg mb-2"
                                    alt=""
                                >

                                <div>

                                    <label class="btn btn-sm btn-outline-primary">

                                        <i class="bi bi-camera"></i>
                                        Profile Picture

                                        <input
                                            type="file"
                                            name="profile_image"
                                            class="d-none image-preview-input"
                                            data-preview="#profilePreview"
                                            accept="image/*"
                                        >

                                    </label>

                                </div>

                            </div>


                            <!-- COVER IMAGE -->
                            <div class="mb-3">

                                <label class="form-label">
                                    Cover Image
                                </label>

                                <input
                                    type="file"
                                    name="cover_image"
                                    class="form-control"
                                    accept="image/*"
                                >

                                <?php if (
                                    $profile &&
                                    $profile['cover_image']
                                ): ?>

                                    <img
                                        src="<?= e(
                                            UPLOAD_COVER_URL .
                                            $profile['cover_image']
                                        ) ?>"
                                        class="cover-banner mt-2"
                                        style="height:100px;border-radius:8px;"
                                        alt="Current cover"
                                    >

                                <?php endif; ?>

                            </div>


                            <!-- CATEGORY + LOCATION -->
                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Category
                                    </label>

                                    <select
                                        name="category_id"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Select category
                                        </option>

                                        <?php foreach (
                                            $categories as $cat
                                        ): ?>

                                            <option
                                                value="<?= (int) $cat['id'] ?>"
                                                <?= (string) $old['category_id'] ===
                                                    (string) $cat['id']
                                                    ? 'selected'
                                                    : ''
                                                ?>
                                            >
                                                <?= e(
                                                    $cat['category_name']
                                                ) ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Location
                                    </label>

                                    <input
                                        type="text"
                                        name="location"
                                        class="form-control"
                                        value="<?= e($old['location']) ?>"
                                        placeholder="e.g. Dar es Salaam"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- EXPERIENCE + PRICE -->
                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Years of Experience
                                    </label>

                                    <input
                                        type="number"
                                        name="experience_years"
                                        class="form-control"
                                        value="<?= e(
                                            $old['experience_years']
                                        ) ?>"
                                        min="0"
                                        max="80"
                                        required
                                    >

                                </div>


                                <div class="col-md-6 mb-3">

                                    <label class="form-label">
                                        Starting Price (TZS)
                                    </label>

                                    <input
                                        type="number"
                                        step="0.01"
                                        name="price"
                                        class="form-control"
                                        value="<?= e($old['price']) ?>"
                                        min="0"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- BIO -->
                            <div class="mb-3">

                                <label class="form-label">
                                    Biography
                                </label>

                                <textarea
                                    name="bio"
                                    class="form-control"
                                    rows="4"
                                    placeholder="Tell customers about yourself..."
                                ><?= e($old['bio']) ?></textarea>

                            </div>


                            <!-- SKILLS -->
                            <div class="mb-3">

                                <label class="form-label">
                                    Skills
                                    <span class="text-secondary">
                                        (comma-separated)
                                    </span>
                                </label>

                                <input
                                    type="text"
                                    name="skills"
                                    class="form-control"
                                    value="<?= e($old['skills']) ?>"
                                    placeholder="e.g. Public Speaking, Bilingual, Crowd Engagement"
                                >

                            </div>


                            <!-- SERVICE DESCRIPTION -->
                            <div class="mb-3">

                                <label class="form-label">
                                    Service Description
                                </label>

                                <textarea
                                    name="service_description"
                                    class="form-control"
                                    rows="3"
                                    placeholder="What's included in your service..."
                                ><?= e(
                                    $old['service_description']
                                ) ?></textarea>

                            </div>


                            <!-- VIDEO LINKS -->
                            <div class="mb-4">

                                <label class="form-label">
                                    Video Links
                                    <span class="text-secondary">
                                        (comma-separated URLs)
                                    </span>
                                </label>

                                <input
                                    type="text"
                                    name="video_links"
                                    class="form-control"
                                    value="<?= e($old['video_links']) ?>"
                                    placeholder="https://youtube.com/..., https://youtube.com/..."
                                >

                            </div>


                            <button
                                type="submit"
                                class="btn btn-primary px-4"
                            >
                                <?= $profile
                                    ? 'Update Profile'
                                    : 'Create Profile'
                                ?>
                            </button>

                        </form>

                    </div>

                </div>


                <!-- CHANGE PASSWORD -->
                <div class="card border-0 shadow-sm">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-3">
                            Change Password
                        </h5>


                        <?php if (!empty($passwordErrors)): ?>

                            <div class="alert alert-danger">

                                <ul class="mb-0 ps-3">

                                    <?php foreach (
                                        $passwordErrors as $error
                                    ): ?>

                                        <li><?= e($error) ?></li>

                                    <?php endforeach; ?>

                                </ul>

                            </div>

                        <?php endif; ?>


                        <form
                            method="POST"
                            action=""
                            novalidate
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="change_password"
                                value="1"
                            >


                            <div class="mb-3">

                                <label class="form-label">
                                    Current Password
                                </label>

                               
