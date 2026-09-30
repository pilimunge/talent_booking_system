<?php
/**
 * admin/categories.php
 * Full CRUD for talent categories.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('admin');

$errors = [];

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    verify_csrf();

    $name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } else {
        $check = $pdo->prepare('SELECT id FROM categories WHERE category_name = :name');
        $check->execute(['name' => $name]);
        if ($check->fetch()) {
            $errors[] = 'This category already exists.';
        } else {
            $insert = $pdo->prepare('INSERT INTO categories (category_name, description) VALUES (:name, :description)');
            $insert->execute(['name' => $name, 'description' => $description ?: null]);
            set_flash('success', 'Category added.');
            redirect('admin/categories.php');
        }
    }
}

// Update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category_id'])) {
    verify_csrf();

    $catId = (int) $_POST['update_category_id'];
    $name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    } else {
        $update = $pdo->prepare('UPDATE categories SET category_name = :name, description = :description WHERE id = :id');
        $update->execute(['name' => $name, 'description' => $description ?: null, 'id' => $catId]);
        set_flash('success', 'Category updated.');
        redirect('admin/categories.php');
    }
}

// Delete category (blocked if providers are using it)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category_id'])) {
    verify_csrf();

    $catId = (int) $_POST['delete_category_id'];
    $usageCheck = $pdo->prepare('SELECT COUNT(*) FROM providers WHERE category_id = :id');
    $usageCheck->execute(['id' => $catId]);

    if ((int) $usageCheck->fetchColumn() > 0) {
        set_flash('error', 'Cannot delete a category that has providers assigned to it.');
    } else {
        $delete = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $delete->execute(['id' => $catId]);
        set_flash('success', 'Category deleted.');
    }
    redirect('admin/categories.php');
}

$categories = $pdo->query(
    'SELECT c.*, COUNT(p.id) as provider_count
     FROM categories c
     LEFT JOIN providers p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.category_name'
)->fetchAll();

$current_page = 'categories';
$page_title = 'Manage Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require __DIR__ . '/_sidebar.php'; ?>

        <div class="col-lg-9 col-xl-10 py-4">
            <div class="container-fluid">
                <h3 class="fw-bold mb-4">Manage Categories</h3>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">Add New Category</h6>
                        <form method="POST" action="" class="row g-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="add_category" value="1">
                            <div class="col-md-4">
                                <input type="text" name="category_name" class="form-control" placeholder="Category name" required>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="description" class="form-control" placeholder="Description (optional)">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr><th>Category</th><th>Description</th><th>Providers</th><th class="text-end">Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <?php $formId = 'editForm' . (int) $cat['id']; ?>
                                    <tr>
                                        <td>
                                            <input type="text" name="category_name" class="form-control form-control-sm" value="<?= e($cat['category_name']) ?>" form="<?= e($formId) ?>">
                                        </td>
                                        <td><input type="text" name="description" class="form-control form-control-sm" value="<?= e($cat['description']) ?>" form="<?= e($formId) ?>"></td>
                                        <td><span class="badge bg-light text-dark border"><?= (int) $cat['provider_count'] ?></span></td>
                                        <td class="text-end">
                                            <!-- Standalone form (not nested in the table) submits the two inputs above via form= attribute -->
                                            <form method="POST" action="" id="<?= e($formId) ?>" class="d-none">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="update_category_id" value="<?= (int) $cat['id'] ?>">
                                            </form>
                                            <button type="submit" class="btn btn-sm btn-outline-primary" form="<?= e($formId) ?>">Save</button>
                                            <?php if ((int) $cat['provider_count'] === 0): ?>
                                                <form method="POST" action="" class="d-inline" data-confirm="Delete category '<?= e($cat['category_name']) ?>'? This cannot be undone.">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="delete_category_id" value="<?= (int) $cat['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Cannot delete: providers assigned">Delete</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

              
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
