<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Shop By Video Categories';

$errors = [];
$editCat = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($name === '') $errors[] = 'Category name is required.';

    $thumb = null;
    if (!empty($_FILES['thumbnail']['name'])) {
        $uploaded = uploadImage($_FILES['thumbnail'], UPLOAD_DIR);
        if ($uploaded) $thumb = $uploaded;
    }

    if (empty($errors)) {
        if ($id > 0) {
            if ($thumb) {
                $stmt = $pdo->prepare("UPDATE video_categories SET name=?, thumbnail=?, sort_order=?, status=? WHERE id=?");
                $stmt->execute([$name, $thumb, $sortOrder, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE video_categories SET name=?, sort_order=?, status=? WHERE id=?");
                $stmt->execute([$name, $sortOrder, $status, $id]);
            }
            flash('success', 'Video category updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO video_categories (name, thumbnail, sort_order, status, created_by) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $thumb, $sortOrder, $status, $_SESSION['employee_id']]);
            flash('success', 'Video category added successfully.');
        }
        redirect('video-categories.php');
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM video_categories WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Video category deleted. Videos using it keep their file but lose the category tag.');
    redirect('video-categories.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM video_categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCat = $stmt->fetch();
}

$categories = $pdo->query("
    SELECT vc.*, (SELECT COUNT(*) FROM shop_videos sv WHERE sv.category_id = vc.id) AS video_count
    FROM video_categories vc ORDER BY vc.sort_order ASC
")->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="alert alert-info">This section is completely separate from Products. Create your categories here, then upload the actual video clips on <a href="shop-videos.php">Shop By Video</a> — that is the only place videos can be uploaded. Only vertical (portrait) videos are accepted.</div>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header"><h3>Video Categories</h3></div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Thumb</th><th>Name</th><th>Videos</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><img class="table-thumb" src="../assets/uploads/products/<?= sanitize($c['thumbnail']) ?>" onerror="this.src='https://images.unsplash.com/photo-1445205170230-053b83016050?w=100&q=80'" alt=""></td>
                        <td><?= sanitize($c['name']) ?></td>
                        <td><?= (int)$c['video_count'] ?></td>
                        <td><?= (int)$c['sort_order'] ?></td>
                        <td><span class="status-badge status-<?= sanitize($c['status']) ?>"><?= sanitize($c['status']) ?></span></td>
                        <td>
                            <a href="video-categories.php?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                            <a href="video-categories.php?delete=<?= (int)$c['id'] ?>" class="btn btn-sm confirm-delete"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?><tr><td colspan="6" style="text-align:center;color:var(--a-text-light);">No video categories yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3><?= $editCat ? 'Edit Category' : 'Add New Category' ?></h3></div>
        <form method="post" action="video-categories.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $editCat['id'] ?? '' ?>">
            <div class="form-group">
                <label>Category Name * <small style="color:var(--a-text-light);">(e.g. Wedding Edit, Everyday Wear)</small></label>
                <input type="text" name="name" required value="<?= sanitize($editCat['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Thumbnail <small style="color:var(--a-text-light);">(optional, for admin reference only)</small></label>
                <input type="file" name="thumbnail" accept="image/*">
                <?php if (!empty($editCat['thumbnail'])): ?>
                    <img src="../assets/uploads/products/<?= sanitize($editCat['thumbnail']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:50%;margin-top:8px;" onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="<?= sanitize($editCat['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= ($editCat['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editCat['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-block"><?= $editCat ? 'Update Category' : 'Add Category' ?></button>
            <?php if ($editCat): ?><a href="video-categories.php" class="btn btn-outline btn-block" style="margin-top:8px;">Cancel</a><?php endif; ?>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
