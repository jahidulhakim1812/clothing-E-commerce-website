<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
requireSuperAdmin();
$pageTitle = 'Category Management';

$errors = [];
$editCategory = null;

// Handle Add / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($name === '') $errors[] = 'Category name is required.';

    if (empty($errors)) {
        $image = null;

        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadImage($_FILES['image'], UPLOAD_DIR);
            if ($uploaded) $image = $uploaded;
        }

        if ($id > 0) {
            if ($image) {
                $stmt = $pdo->prepare("UPDATE categories SET name=?, description=?, image=?, status=? WHERE id=?");
                $stmt->execute([$name, $description, $image, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name=?, description=?, status=? WHERE id=?");
                $stmt->execute([$name, $description, $status, $id]);
            }
            flash('success', 'Category updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, image, status) VALUES (?,?,?,?)");
            $stmt->execute([$name, $description, $image, $status]);
            flash('success', 'Category added successfully.');
        }
        redirect('categories.php');
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Category deleted.');
    redirect('categories.php');
}

// Handle edit load
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCategory = $stmt->fetch();
}

$categories = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c ORDER BY c.created_at DESC")->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header">
            <h3>All Categories</h3>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Name</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><img class="table-thumb" src="../assets/uploads/products/<?= sanitize($c['image']) ?>" onerror="this.src='https://images.unsplash.com/photo-1483985988355-763728e1935b?w=100&q=80'" alt=""></td>
                        <td><?= sanitize($c['name']) ?></td>
                        <td><?= (int)$c['product_count'] ?></td>
                        <td><span class="status-badge status-<?= sanitize($c['status']) ?>"><?= sanitize($c['status']) ?></span></td>
                        <td>
                            <a href="categories.php?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                            <a href="categories.php?delete=<?= (int)$c['id'] ?>" class="btn btn-sm btn" onclick="return confirm('Delete this category? Products inside will also be deleted.')"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3><?= $editCategory ? 'Edit Category' : 'Add New Category' ?></h3></div>
        <form method="post" action="categories.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $editCategory['id'] ?? '' ?>">
            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" required value="<?= sanitize($editCategory['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?= sanitize($editCategory['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Category Image</label>
                <input type="file" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($editCategory['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($editCategory['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-block"><?= $editCategory ? 'Update Category' : 'Add Category' ?></button>
            <?php if ($editCategory): ?><a href="categories.php" class="btn btn-outline btn-block" style="margin-top:8px;">Cancel</a><?php endif; ?>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
