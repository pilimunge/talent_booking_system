<?php
/**
 * provider/availability.php
 * Simple availability scheduler: provider marks specific dates as
 * available or unavailable. Customers see this reflected on the public
 * profile is out of scope for this MVP list view, but the data model
 * fully supports adding that later.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_date'])) {
    verify_csrf();

    $date = $_POST['available_date'] ?? '';
    $status = $_POST['status'] ?? 'available';

    if (!$date || !strtotime($date)) {
        $errors[] = 'Please select a valid date.';
    } elseif (!in_array($status, ['available', 'unavailable'], true)) {
        $errors[] = 'Invalid status.';
    } else {
        // Upsert: one row per provider+date
        $stmt = $pdo->prepare('SELECT id FROM availability WHERE provider_id = :pid AND available_date = :date');
        $stmt->execute(['pid' => $providerProfile['id'], 'date' => $date]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $pdo->prepare('UPDATE availability SET status = :status WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $existing['id']]);
        } else {
            $insert = $pdo->prepare('INSERT INTO availability (provider_id, available_date, status) VALUES (:pid, :date, :status)');
            $insert->execute(['pid' => $providerProfile['id'], 'date' => $date, 'status' => $status]);
        }

        set_flash('success', 'Availability updated for ' . format_date($date) . '.');
        redirect('provider/availability.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $deleteId = (int) $_POST['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM availability WHERE id = :id AND provider_id = :pid');
    $stmt->execute(['id' => $deleteId, 'pid' => $providerProfile['id']]);
    set_flash('success', 'Entry removed.');
    redirect('provider/availability.php');
}

$listStmt = $pdo->prepare('SELECT * FROM availability WHERE provider_id = :pid AND available_date >= CURRENT_DATE() ORDER BY available_date ASC');
$listStmt->execute(['pid' => $providerProfile['id']]);
$availabilityEntries = $listStmt->fetchAll();

$current_page = 'availability';
$page_title = 'Availability';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid" style="max-width: 700px;">
                <h3 class="fw-bold mb-4">My Availability</h3>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Set Date Status</h6>
                        <form method="POST" action="" class="row g-2 align-items-end">
                            <?= csrf_field() ?>
                            <input type="hidden" name="add_date" value="1">
                            <div class="col-md-5">
                                <label class="form-label small">Date</label>
                                <input type="date" name="available_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Status</label>
                                <select name="status" class="form-select">
                                    <option value="unavailable">Unavailable (blocked)</option>
                                    <option value="available">Available</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">Save</button>
                            </div>
                        </form>
                        <div class="form-text mt-2">By default, all dates are considered available unless marked otherwise here.</div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Upcoming Entries</h6></div>
                    <div class="card-body p-0">
                        <?php if (empty($availabilityEntries)): ?>
                            <p class="text-secondary p-4 mb-0">No specific entries set. All upcoming dates are treated as available.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($availabilityEntries as $entry): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-semibold"><?= format_date($entry['available_date']) ?></span>
                                            <span class="badge <?= $entry['status'] === 'available' ? 'bg-success' : 'bg-danger' ?> ms-2"><?= ucfirst($entry['status']) ?></span>
                                        </div>
                                        <form method="POST" action="" data-confirm="Remove this entry?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="delete_id" value="<?= (int) $entry['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
