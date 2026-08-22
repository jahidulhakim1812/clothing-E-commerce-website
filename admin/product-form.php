<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
$pageTitle = 'Product Form';

$id = (int)($_GET['id'] ?? 0);
$product = null;
$errors = [];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $description = sanitize($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discountPrice = $_POST['discount_price'] !== '' ? (float)$_POST['discount_price'] : null;
    // Purchase Amount (cost price) — only Super Admin can set/change this.
    $costPrice = $product['cost_price'] ?? null;
    if (isSuperAdmin()) {
        $costPrice = ($_POST['cost_price'] ?? '') !== '' ? (float)$_POST['cost_price'] : null;
    }
    $sku = sanitize($_POST['sku'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);
    $sizeOptions = sanitize($_POST['size_options'] ?? '');
    $colorOptions = sanitize($_POST['color_options'] ?? '');

    // Per-size measurements (chest/waist/hip in inches), entered as a JSON
    // string by the size-chart builder script below. Only keep rows that
    // actually have at least one measurement filled in.
    $sizeChart = null;
    $rawSizeChart = json_decode($_POST['size_chart_json'] ?? '', true);
    if (is_array($rawSizeChart)) {
        $cleanChart = [];
        foreach ($rawSizeChart as $sizeKey => $row) {
            if (!is_array($row)) continue;
            $sizeKey = strtoupper(sanitize($sizeKey));
            $chest = sanitize($row['chest'] ?? '');
            $waist = sanitize($row['waist'] ?? '');
            $hip = sanitize($row['hip'] ?? '');
            if ($sizeKey === '' || ($chest === '' && $waist === '' && $hip === '')) continue;
            $cleanChart[$sizeKey] = ['chest' => $chest, 'waist' => $waist, 'hip' => $hip];
        }
        if (!empty($cleanChart)) $sizeChart = json_encode($cleanChart);
    }
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';

    if ($name === '') $errors[] = 'Product name is required.';
    if ($categoryId <= 0) $errors[] = 'Please select a category.';
    if ($price <= 0) $errors[] = 'Price must be greater than zero.';

    if (empty($errors)) {
        $image = $product['image'] ?? null;
        $gallery = $product['gallery'] ?? null;

        if (!empty($_FILES['image']['name'])) {
            $uploaded = uploadImage($_FILES['image'], UPLOAD_DIR);
            if ($uploaded) $image = $uploaded;
        }

        if (!empty($_FILES['gallery']['name'][0])) {
            $galleryNames = [];
            foreach ($_FILES['gallery']['name'] as $i => $gName) {
                if ($gName === '') continue;
                $singleFile = [
                    'name' => $_FILES['gallery']['name'][$i],
                    'type' => $_FILES['gallery']['type'][$i],
                    'tmp_name' => $_FILES['gallery']['tmp_name'][$i],
                    'error' => $_FILES['gallery']['error'][$i],
                    'size' => $_FILES['gallery']['size'][$i],
                ];
                $g = uploadImage($singleFile, UPLOAD_DIR);
                if ($g) $galleryNames[] = $g;
            }
            if ($galleryNames) $gallery = implode(',', $galleryNames);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE products SET category_id=?, name=?, description=?, price=?, discount_price=?, cost_price=?, sku=?, stock=?, image=?, gallery=?, size_options=?, size_chart=?, color_options=?, featured=?, status=? WHERE id=?");
            $stmt->execute([$categoryId, $name, $description, $price, $discountPrice, $costPrice, $sku, $stock, $image, $gallery, $sizeOptions, $sizeChart, $colorOptions, $featured, $status, $id]);
            flash('success', 'Product updated successfully.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, price, discount_price, cost_price, sku, stock, image, gallery, size_options, size_chart, color_options, featured, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$categoryId, $name, $description, $price, $discountPrice, $costPrice, $sku, $stock, $image, $gallery, $sizeOptions, $sizeChart, $colorOptions, $featured, $status, $_SESSION['employee_id']]);
            $newId = $pdo->lastInsertId();
            if ($stock > 0) {
                $log = $pdo->prepare("INSERT INTO inventory_logs (product_id, change_qty, reason) VALUES (?,?,?)");
                $log->execute([$newId, $stock, 'Initial stock']);
            }
            flash('success', 'Product added successfully.');
        }
        redirect('products.php');
    }
}

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $e) echo sanitize($e) . '<br>'; ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h3><?= $product ? 'Edit Product' : 'Add New Product' ?></h3>
        <a href="products.php" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Products</a>
    </div>

    <form method="post" action="product-form.php<?= $id ? '?id=' . $id : '' ?>" enctype="multipart/form-data">
        <div class="form-row-2">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="name" required value="<?= sanitize($product['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category_id" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (($product['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"><?= sanitize($product['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row-3">
            <div class="form-group">
                <label>Regular Price (৳) *</label>
                <input type="number" step="0.01" name="price" required value="<?= sanitize($product['price'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Discount Price (৳)</label>
                <input type="number" step="0.01" name="discount_price" value="<?= sanitize($product['discount_price'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" value="<?= sanitize($product['sku'] ?? '') ?>">
            </div>
        </div>

        <?php if (isSuperAdmin()): ?>
        <div class="form-row-3">
            <div class="form-group">
                <label>Purchase Amount (৳) <small style="color:var(--a-text-light);">(cost price — used to calculate profit)</small></label>
                <input type="number" step="0.01" name="cost_price" value="<?= sanitize($product['cost_price'] ?? '') ?>">
            </div>
        </div>
        <?php else: ?>
            <input type="hidden" name="cost_price" value="">
        <?php endif; ?>

        <div class="form-row-2">
            <div class="form-group">
                <label>Stock Quantity *</label>
                <input type="number" name="stock" required value="<?= sanitize($product['stock'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label>Main Product Image</label>
                <input type="file" name="image" accept="image/*">
                <?php if (!empty($product['image'])): ?>
                    <img src="../assets/uploads/products/<?= sanitize($product['image']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;margin-top:8px;" onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Gallery Images <small style="color:var(--a-text-light);">(multiple — shown as thumbnails on the product page)</small></label>
            <input type="file" name="gallery[]" accept="image/*" multiple>
            <?php if (!empty($product['gallery'])): ?>
                <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
                    <?php foreach (explode(',', $product['gallery']) as $g): ?>
                        <img src="../assets/uploads/products/<?= sanitize($g) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px;" onerror="this.style.display='none'">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label>Size Options <small style="color:var(--a-text-light);">(comma separated, e.g. S,M,L,XL)</small></label>
                <input type="text" name="size_options" id="sizeOptionsInput" value="<?= sanitize($product['size_options'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Color Options <small style="color:var(--a-text-light);">(comma separated)</small></label>
                <input type="text" name="color_options" value="<?= sanitize($product['color_options'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group" id="sizeChartGroup" style="display:none;">
            <label>Size Chart <small style="color:var(--a-text-light);">(measurements in inches — shown to customers on the product page, e.g. 34-36)</small></label>
            <table class="admin-table" id="sizeChartTable">
                <thead><tr><th style="width:80px;">Size</th><th>Chest</th><th>Waist</th><th>Hip</th></tr></thead>
                <tbody id="sizeChartBody"></tbody>
            </table>
            <input type="hidden" name="size_chart_json" id="sizeChartJson">
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="active" <?= ($product['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                    <input type="checkbox" name="featured" style="width:auto;" <?= !empty($product['featured']) ? 'checked' : '' ?>> Mark as Featured Product
                </label>
            </div>
        </div>

        <button type="submit" class="btn"><?= $product ? 'Update Product' : 'Add Product' ?></button>
    </form>
</div>

<script>
(function () {
    var sizeInput = document.getElementById('sizeOptionsInput');
    var group = document.getElementById('sizeChartGroup');
    var tbody = document.getElementById('sizeChartBody');
    var hiddenJson = document.getElementById('sizeChartJson');

    // Existing per-size measurements for this product (if any), keyed by
    // uppercase size name — pre-fills the table when editing a product.
    var existing = <?= json_encode(!empty($product['size_chart']) ? (json_decode($product['size_chart'], true) ?: new stdClass()) : new stdClass()) ?>;

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function captureCurrent() {
        tbody.querySelectorAll('input').forEach(function (inp) {
            var size = inp.dataset.size, field = inp.dataset.field;
            if (!existing[size]) existing[size] = {};
            existing[size][field] = inp.value;
        });
    }

    function render() {
        captureCurrent();
        var sizes = sizeInput.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        tbody.innerHTML = '';
        if (!sizes.length) { group.style.display = 'none'; return; }
        group.style.display = 'block';
        sizes.forEach(function (sz) {
            var key = sz.toUpperCase();
            var row = existing[key] || {};
            var tr = document.createElement('tr');
            ['chest', 'waist', 'hip'].forEach(function (field, i) {
                if (i === 0) {
                    var tdLabel = document.createElement('td');
                    tdLabel.innerHTML = '<strong>' + escapeHtml(sz) + '</strong>';
                    tr.appendChild(tdLabel);
                }
                var td = document.createElement('td');
                var input = document.createElement('input');
                input.type = 'text';
                input.dataset.size = key;
                input.dataset.field = field;
                input.value = row[field] || '';
                input.placeholder = field === 'chest' ? 'e.g. 34-36' : (field === 'waist' ? 'e.g. 28-30' : 'e.g. 36-38');
                input.style.cssText = 'width:100%;padding:6px 8px;';
                td.appendChild(input);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    if (sizeInput) {
        sizeInput.addEventListener('input', render);
        render();

        sizeInput.form.addEventListener('submit', function () {
            captureCurrent();
            var sizes = sizeInput.value.split(',').map(function (s) { return s.trim().toUpperCase(); }).filter(Boolean);
            var data = {};
            sizes.forEach(function (key) {
                if (existing[key]) data[key] = existing[key];
            });
            hiddenJson.value = JSON.stringify(data);
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
