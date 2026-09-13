<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Hero Slider Management';

$errors = [];
$editSlide = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');
    $subtitle = sanitize($_POST['subtitle'] ?? '');
    $buttonText = sanitize($_POST['button_text'] ?? 'Shop Now');
    $buttonLink = sanitize($_POST['button_link'] ?? '#');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($title === '') $errors[] = 'Slide title is required.';

    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $uploaded = uploadImage($_FILES['image'], UPLOAD_DIR);
        if ($uploaded) $image = $uploaded;
    }
    if ($id === 0 && !$image) $errors[] = 'An image is required for new slides.';

    if (empty($errors)) {
        if ($id > 0) {
            if ($image) {
                $stmt = $pdo->prepare("UPDATE hero_slides SET title=?, subtitle=?, image=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                $stmt->execute([$title, $subtitle, $image, $buttonText, $buttonLink, $sortOrder, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE hero_slides SET title=?, subtitle=?, button_text=?, button_link=?, sort_order=?, status=? WHERE id=?");
                $stmt->execute([$title, $subtitle, $buttonText, $buttonLink, $sortOrder, $status, $id]);
            }
            flash('success', 'Slide updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO hero_slides (title, subtitle, image, button_text, button_link, sort_order, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$title, $subtitle, $image, $buttonText, $buttonLink, $sortOrder, $status]);
            flash('success', 'Slide added successfully.');
        }
        redirect('hero_slides.php');
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM hero_slides WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Slide deleted.');
    redirect('hero_slides.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM hero_slides WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editSlide = $stmt->fetch();
}

$slides = $pdo->query("SELECT * FROM hero_slides ORDER BY sort_order ASC")->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header"><h3>Homepage Hero Slides</h3></div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($slides as $s): ?>
                    <tr>
                        <td><img class="table-thumb" src="../assets/uploads/products/<?= sanitize($s['image']) ?>" onerror="this.src='https://images.unsplash.com/photo-1445205170230-053b83016050?w=100&q=80'" alt=""></td>
                        <td><?= sanitize($s['title']) ?></td>
                        <td><?= (int)$s['sort_order'] ?></td>
                        <td><span class="status-badge status-<?= sanitize($s['status']) ?>"><?= sanitize($s['status']) ?></span></td>
                        <td>
                            <a href="hero_slides.php?edit=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                            <a href="hero_slides.php?delete=<?= (int)$s['id'] ?>" class="btn btn-sm confirm-delete"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($slides)): ?><tr><td colspan="5" style="text-align:center;color:var(--a-text-light);">No slides yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3><?= $editSlide ? 'Edit Slide' : 'Add New Slide' ?></h3></div>
        <form method="post" action="hero_slides.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $editSlide['id'] ?? '' ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required value="<?= sanitize($editSlide['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Subtitle</label>
                <input type="text" name="subtitle" value="<?= sanitize($editSlide['subtitle'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Slide Image <?= $editSlide ? '' : '*' ?></label>
                <input type="file" name="image" accept="image/*">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Button Text</label>
                    <input type="text" name="button_text" value="<?= sanitize($editSlide['button_text'] ?? 'Shop Now') ?>">
                </div>
                <div class="form-group">
                    <label>Button Link</label>
                    <input type="text" name="button_link" value="<?= sanitize($editSlide['button_link'] ?? 'products.php') ?>">
                </div>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="<?= sanitize($editSlide['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= ($editSlide['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editSlide['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-block"><?= $editSlide ? 'Update Slide' : 'Add Slide' ?></button>
            <?php if ($editSlide): ?><a href="hero_slides.php" class="btn btn-outline btn-block" style="margin-top:8px;">Cancel</a><?php endif; ?>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
