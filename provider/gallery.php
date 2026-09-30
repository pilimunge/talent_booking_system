<?php
/**
 * provider/gallery.php
 * Upload/delete portfolio gallery photos.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$errors = [];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_image_id'])) {
    verify_csrf();

    $imageId = (int) $_POST['delete_image_id'];
    $stmt = $pdo->prepare('SELECT * FROM gallery WHERE id = :id AND provider_id = :pid');
    $stmt->execute(['id' => $imageId, 'pid' => $providerProfile['id']]);
    $image = $stmt->fetch();

    if ($image) {
        if (file_exists(UPLOAD_GALLERY_DIR . $image['image_path'])) {
            unlink(UPLOAD_GALLERY_DIR . $image['image_path']);
        }
        $delete = $pdo->prepare('DELETE FROM gallery WHERE id = :id');
        $delete->execute(['id' => $imageId]);
        set_flash('success', 'Image removed.');
    }
    redirect('provider/gallery.php');
}

// Handle upload (multiple files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_images'])) {
    verify_csrf();

    $uploadedCount = 0;
    $fileCount = count($_FILES['gallery_images']['name']);

    if ($fileCount > 10) {
        $errors[] = 'You can upload a maximum of 10 images at once.';
    } else {
        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($_FILES['gallery_images']['name'][$i])) {
                continue;
            }
            $file = [
                'name' => $_FILES['gallery_images']['name'][$i],
                'type' => $_FILES['gallery_images']['type'][$i],
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                'error' => $_FILES['gallery_images']['error'][$i],
                'size' => $_FILES['gallery_images']['size'][$i],
            ];

            try {
                $filename = handle_image_upload($file, UPLOAD_GALLERY_DIR);
                if ($filename) {
                    $insert = $pdo->prepare('INSERT INTO gallery (provider_id, image_path) VALUES (:pid, :path)');
                    $insert->execute(['pid' => $providerProfile['id'], 'path' => $filename]);
                    $uploadedCount++;
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($uploadedCount > 0) {
            set_flash('success', $uploadedCount . ' image(s) uploaded successfully.');
            redirect('provider/gallery.php');
        }
    }
}

$galleryStmt = $pdo->prepare('SELECT * FROM gallery WHERE provider_id = :pid ORDER BY created_at DESC');
$galleryStmt->execute(['pid' => $providerProfile['id']]);
$galleryImages = $galleryStmt->fetchAll();

$current_page = 'gallery';
$page_title = 'My Gallery';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">My Gallery</h3>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Upload Photos</h6>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <input type="file" name="gallery_images[]" id="galleryInput" class="form-control" accept="image/*" multiple required>
                                <div class="form-text">You can select up to 10 images at once. Max 3MB each. <span id="galleryCount" class="fw-semibold"></span></div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>

                <?php if (empty($galleryImages)): ?>
                    <div class="alert alert-light border text-center py-5">
                        <i class="bi bi-images fs-1 text-secondary"></i>
                        <p class="mt-3 mb-0">No photos uploaded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($galleryImages as $img): ?>
                            <div class="col-6 col-md-3">
                                <div class="position-relative">
                                    <img src="<?= e(UPLOAD_GALLERY_URL . $img['image_path']) ?>" class="gallery-thumb" alt="Gallery photo">
                                    <form method="POST" action="" class="position-absolute top-0 end-0 m-1" data-confirm="Remove this photo?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_image_id" value="<?= (int) $img['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
