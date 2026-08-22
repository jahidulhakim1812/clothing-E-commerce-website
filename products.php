<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Shop';

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = ["p.status = 'active'"];
$params = [];

if (!empty($_GET['category'])) {
    $where[] = "p.category_id = :category";
    $params[':category'] = (int)$_GET['category'];
}
if (!empty($_GET['search'])) {
    $where[] = "(p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}
if (!empty($_GET['min_price'])) {
    $where[] = "COALESCE(p.discount_price, p.price) >= :min_price";
    $params[':min_price'] = (float)$_GET['min_price'];
}
if (!empty($_GET['max_price'])) {
    $where[] = "COALESCE(p.discount_price, p.price) <= :max_price";
    $params[':max_price'] = (float)$_GET['max_price'];
}
if (($_GET['filter'] ?? '') === 'flash_sale') {
    $where[] = "p.is_flash_sale = 1";
}
if (($_GET['filter'] ?? '') === 'discounted') {
    $where[] = "p.discount_price IS NOT NULL";
}
$whereSql = implode(' AND ', $where);

$sort = $_GET['sort'] ?? 'newest';
$orderSql = match ($sort) {
    'price_low'  => 'COALESCE(p.discount_price, p.price) ASC',
    'price_high' => 'COALESCE(p.discount_price, p.price) DESC',
    'name'       => 'p.name ASC',
    default      => 'p.created_at DESC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $perPage));

$sql = "SELECT p.*, c.name AS category_name FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE $whereSql ORDER BY $orderSql LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<?php
$bannerTitle = 'Shop Our Collection';
if (($_GET['filter'] ?? '') === 'flash_sale') $bannerTitle = 'Flash Sale';
if (($_GET['filter'] ?? '') === 'discounted') $bannerTitle = 'Handmade Treasures On Offer';
?>
<div class="page-banner">
    <div class="container">
        <h1><?= sanitize($bannerTitle) ?></h1>
        <p><?= $totalProducts ?> products available</p>
    </div>
</div>

<section class="section">
    <div class="container shop-layout">

        <!-- Filters Sidebar -->
        <aside class="panel" style="background:#fff;border-radius:12px;padding:24px;box-shadow:var(--shadow);">
            <form method="get" action="products.php">
                <h4 style="margin-bottom:16px;">Categories</h4>
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
                    <label style="display:flex;gap:8px;align-items:center;font-size:14px;">
                        <input type="radio" name="category" value="" <?= empty($_GET['category']) ? 'checked' : '' ?> onchange="this.form.submit()"> All Categories
                    </label>
                    <?php foreach ($categories as $cat): ?>
                    <label style="display:flex;gap:8px;align-items:center;font-size:14px;">
                        <input type="radio" name="category" value="<?= (int)$cat['id'] ?>" <?= (($_GET['category'] ?? '') == $cat['id']) ? 'checked' : '' ?> onchange="this.form.submit()"> <?= sanitize($cat['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <h4 style="margin-bottom:16px;">Price Range</h4>
                <div style="margin-bottom:16px;">
    <input type="number" name="min_price" placeholder="Min"
           value="<?= sanitize($_GET['min_price'] ?? '') ?>"
           style="width:100%; margin-bottom:10px;">

    <input type="number" name="max_price" placeholder="Max"
           value="<?= sanitize($_GET['max_price'] ?? '') ?>"
           style="width:100%;">
</div>
                <?php if (!empty($_GET['search'])): ?><input type="hidden" name="search" value="<?= sanitize($_GET['search']) ?>"><?php endif; ?>
                <button type="submit" class="btn btn-block btn-sm">Apply Filters</button>
                <a href="products.php" class="btn btn-outline btn-block btn-sm" style="margin-top:8px;">Clear Filters</a>
            </form>
        </aside>

        <!-- Products -->
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:10px;">
                <span style="color:var(--text-light);font-size:14px;"><?= sanitize($_GET['search'] ?? '') ? 'Results for "' . sanitize($_GET['search']) . '"' : 'Showing ' . count($products) . ' of ' . $totalProducts ?></span>
                <form method="get" style="display:flex;gap:8px;align-items:center;">
                    <?php foreach (['category', 'search', 'min_price', 'max_price'] as $key): if (!empty($_GET[$key])): ?>
                        <input type="hidden" name="<?= $key ?>" value="<?= sanitize($_GET[$key]) ?>">
                    <?php endif; endforeach; ?>
                    <label style="font-size:14px;">Sort:</label>
                    <select name="sort" onchange="this.form.submit()" style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
                    </select>
                </form>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your filters or search term.</p>
                </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $queryParams = $_GET;
                for ($i = 1; $i <= $totalPages; $i++):
                    $queryParams['page'] = $i;
                ?>
                <a href="products.php?<?= http_build_query($queryParams) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
