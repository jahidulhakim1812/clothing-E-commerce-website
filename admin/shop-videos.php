<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Shop By Video';

$errors = [];
$editVid = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $title = sanitize($_POST['title'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0) ?: null;
    $shopLink = sanitize($_POST['shop_link'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (!$categoryId) $errors[] = 'Please select a video category.';
    if ($id <= 0 && empty($_FILES['video']['name'])) $errors[] = 'A vertical video file is required.';

    $video = $editVid['video'] ?? null;
    $cover = null;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM shop_videos WHERE id = ?");
        $stmt->execute([$id]);
        $editVid = $stmt->fetch();
        $video = $editVid['video'] ?? null;
        $cover = $editVid['cover_image'] ?? null;
    }

    if (empty($errors)) {
        if (!empty($_FILES['video']['name'])) {
            $uploadedVideo = uploadVideo($_FILES['video'], UPLOAD_DIR . '../videos/');
            if ($uploadedVideo) $video = $uploadedVideo;
            elseif ($uploadedVideo === false) $errors[] = 'Video upload failed — only mp4, webm or mov files are accepted.';
        }
        if (!empty($_FILES['cover_image']['name'])) {
            $uploadedCover = uploadImage($_FILES['cover_image'], UPLOAD_DIR);
            if ($uploadedCover) $cover = $uploadedCover;
        }
    }

    if (empty($errors)) {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE shop_videos SET category_id=?, title=?, video=?, cover_image=?, product_id=?, shop_link=?, sort_order=?, status=? WHERE id=?");
            $stmt->execute([$categoryId, $title, $video, $cover, $productId, $shopLink, $sortOrder, $status, $id]);
            flash('success', 'Video updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO shop_videos (category_id, title, video, cover_image, product_id, shop_link, sort_order, status, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$categoryId, $title, $video, $cover, $productId, $shopLink, $sortOrder, $status, $_SESSION['employee_id']]);
            flash('success', 'Video uploaded successfully.');
        }
        redirect('shop-videos.php');
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM shop_videos WHERE id = ?");
    $stmt->execute([(int)$_GET['delete']]);
    flash('success', 'Video deleted.');
    redirect('shop-videos.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM shop_videos WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editVid = $stmt->fetch();
}

$videoCategories = $pdo->query("SELECT * FROM video_categories WHERE status='active' ORDER BY sort_order ASC")->fetchAll();
$products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll();

$videos = $pdo->query("
    SELECT sv.*, vc.name AS category_name, p.name AS product_name
    FROM shop_videos sv
    LEFT JOIN video_categories vc ON vc.id = sv.category_id
    LEFT JOIN products p ON p.id = sv.product_id
    ORDER BY sv.sort_order ASC, sv.created_at DESC
")->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg = flash('success')): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="alert alert-info">This is the <strong>only</strong> place videos can be uploaded for the storefront — the Products page no longer has a video field. Only vertical (portrait, 9:16) clips are accepted. Need a new category first? Manage them on <a href="video-categories.php">Video Categories</a>.</div>

<?php if (empty($videoCategories)): ?>
<div class="alert alert-error">You need at least one active video category before you can upload a video. <a href="video-categories.php">Create one here</a>.</div>
<?php endif; ?>

<div class="grid-2">
    <div class="panel">
        <div class="panel-header"><h3>Uploaded Videos</h3></div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Video</th><th>Category</th><th>Links To</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($videos as $v): ?>
                    <tr>
                        <td><video class="table-thumb" style="height:70px;width:44px;object-fit:cover;border-radius:8px;" src="../assets/uploads/videos/<?= sanitize($v['video']) ?>" muted preload="metadata"></video></td>
                        <td><?= sanitize($v['category_name'] ?? '—') ?><?php if ($v['title']): ?><br><small style="color:var(--a-text-light);"><?= sanitize($v['title']) ?></small><?php endif; ?></td>
                        <td><?php if ($v['product_name']): ?><i class="fa-solid fa-shirt"></i> <?= sanitize($v['product_name']) ?><?php elseif ($v['shop_link']): ?><i class="fa-solid fa-link"></i> Custom link<?php else: ?><span style="color:var(--a-text-light);">None</span><?php endif; ?></td>
                        <td><?= (int)$v['sort_order'] ?></td>
                        <td><span class="status-badge status-<?= sanitize($v['status']) ?>"><?= sanitize($v['status']) ?></span></td>
                        <td>
                            <a href="shop-videos.php?edit=<?= (int)$v['id'] ?>" class="btn btn-sm btn-outline"><i class="fa-solid fa-pen"></i></a>
                            <a href="shop-videos.php?delete=<?= (int)$v['id'] ?>" class="btn btn-sm confirm-delete"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($videos)): ?><tr><td colspan="6" style="text-align:center;color:var(--a-text-light);">No videos uploaded yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3><?= $editVid ? 'Edit Video' : 'Upload New Video' ?></h3></div>
        <form method="post" action="shop-videos.php" enctype="multipart/form-data">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $editVid['id'] ?? '' ?>">

            <div class="form-group">
                <label>Video Category *</label>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($videoCategories as $vc): ?>
                        <option value="<?= (int)$vc['id'] ?>" <?= (($editVid['category_id'] ?? '') == $vc['id']) ? 'selected' : '' ?>><?= sanitize($vc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Caption / Title <small style="color:var(--a-text-light);">(optional, shown over the video)</small></label>
                <input type="text" name="title" value="<?= sanitize($editVid['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Vertical Video <?= $editVid ? '' : '*' ?> <small style="color:var(--a-text-light);">(portrait 9:16 mp4/webm/mov)</small></label>
                <input type="file" name="video" id="svVideoInput" accept="video/mp4,video/webm,video/quicktime" <?= $editVid ? '' : 'required' ?>>
                <p id="svOrientationWarning" style="display:none;color:#c0392b;font-size:12.5px;margin-top:6px;"><i class="fa-solid fa-triangle-exclamation"></i> This video looks landscape/square. Shop By Video only supports vertical (portrait, 9:16) clips — please upload a vertical clip.</p>
                <?php if (!empty($editVid['video'])): ?>
                    <p style="margin-top:6px;font-size:12.5px;color:var(--a-text-light);"><i class="fa-solid fa-circle-play"></i> Video already uploaded — choose a new file to replace it.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Custom Cover Image <small style="color:var(--a-text-light);">(optional — falls back to the linked product's image)</small></label>
                <input type="file" name="cover_image" accept="image/*">
                <?php if (!empty($editVid['cover_image'])): ?>
                    <img src="../assets/uploads/products/<?= sanitize($editVid['cover_image']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:8px;margin-top:8px;" onerror="this.style.display='none'">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Link To Product <small style="color:var(--a-text-light);">(optional — powers the "Shop Now" button)</small></label>
                <select name="product_id">
                    <option value="">— None —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (($editVid['product_id'] ?? '') == $p['id']) ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Or Custom Link <small style="color:var(--a-text-light);">(used only if no product is linked above)</small></label>
                <input type="text" name="shop_link" placeholder="https://..." value="<?= sanitize($editVid['shop_link'] ?? '') ?>">
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="<?= sanitize($editVid['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= ($editVid['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editVid['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-block" <?= empty($videoCategories) ? 'disabled' : '' ?>><?= $editVid ? 'Update Video' : 'Upload Video' ?></button>
            <?php if ($editVid): ?><a href="shop-videos.php" class="btn btn-outline btn-block" style="margin-top:8px;">Cancel</a><?php endif; ?>
        </form>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('svVideoInput');
    var warn = document.getElementById('svOrientationWarning');
    if (!input) return;
    input.addEventListener('change', function () {
        warn.style.display = 'none';
        var file = input.files && input.files[0];
        if (!file) return;
        var url = URL.createObjectURL(file);
        var v = document.createElement('video');
        v.preload = 'metadata';
        v.onloadedmetadata = function () {
            URL.revokeObjectURL(url);
            if (v.videoWidth && v.videoHeight && v.videoWidth >= v.videoHeight) {
                warn.style.display = 'block';
            }
        };
        v.src = url;
    });
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
