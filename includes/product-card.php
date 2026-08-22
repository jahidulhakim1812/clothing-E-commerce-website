<?php
/** Expects $p (product row) in scope */
$hasDiscount = !empty($p['discount_price']) && $p['discount_price'] < $p['price'];
$discountPct = $hasDiscount ? round((($p['price'] - $p['discount_price']) / $p['price']) * 100) : 0;

// Lightweight rating summary pulled from approved reviews (only queried if $pdo is in scope)
$pcRatingAvg = 0; $pcRatingCount = 0;
if (isset($pdo)) {
    static $pcRatingCache = [];
    if (!array_key_exists($p['id'], $pcRatingCache)) {
        $rs = $pdo->prepare("SELECT COUNT(*) cnt, COALESCE(AVG(rating),0) avg_r FROM reviews WHERE product_id = ? AND status = 'approved'");
        $rs->execute([$p['id']]);
        $pcRatingCache[$p['id']] = $rs->fetch();
    }
    $pcRatingAvg = (float)$pcRatingCache[$p['id']]['avg_r'];
    $pcRatingCount = (int)$pcRatingCache[$p['id']]['cnt'];
}
?>
<div class="product-card">
    <a href="product-details.php?id=<?= (int)$p['id'] ?>">
        <div class="product-thumb">
            <?php if ($hasDiscount): ?><span class="badge-discount">-<?= $discountPct ?>%</span><?php endif; ?>
            <?php if (!empty($p['featured'])): ?><span class="badge-featured">Featured</span><?php endif; ?>
            <img src="assets/uploads/products/<?= sanitize($p['image']) ?>"
                 onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=500&q=80'"
                 alt="<?= sanitize($p['name']) ?>">
        </div>
    </a>
    <div class="product-actions">
        <a href="product-details.php?id=<?= (int)$p['id'] ?>" title="View Details"><i class="fa-solid fa-eye"></i></a>
        <form action="cart.php" method="post" class="ajax-cart-form" style="display:contents;">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <input type="hidden" name="action" value="quick_add">
            <button type="submit" title="Add to Cart"><i class="fa-solid fa-cart-plus"></i></button>
        </form>
    </div>
    <div class="product-info">
        <div class="cat"><?= sanitize($p['category_name']) ?></div>
        <h3><?= sanitize($p['name']) ?></h3>
        <?php if ($pcRatingCount > 0): ?>
        <div class="pc-rating" style="color:#F0B23D;font-size:11px;margin:2px 0 4px;">
            <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= round($pcRatingAvg) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
            <span style="color:var(--text-light);margin-left:4px;">(<?= $pcRatingCount ?>)</span>
        </div>
        <?php endif; ?>
        <div class="product-price">
            <span class="price-now"><?= formatPrice($hasDiscount ? $p['discount_price'] : $p['price']) ?></span>
            <?php if ($hasDiscount): ?><span class="price-old"><?= formatPrice($p['price']) ?></span><?php endif; ?>
        </div>
    </div>
</div>
