<?php
require_once __DIR__ . '/config/config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.status = 'active'");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    redirect('products.php');
}
$pageTitle = $product['name'];

$related = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.category_id = ? AND p.id != ? AND p.status='active' LIMIT 4");
$related->execute([$product['category_id'], $id]);
$relatedProducts = $related->fetchAll();

$hasDiscount = !empty($product['discount_price']) && $product['discount_price'] < $product['price'];
$discountPct = $hasDiscount ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
$sizes = !empty($product['size_options']) ? array_map('trim', explode(',', $product['size_options'])) : [];
$colors = !empty($product['color_options']) ? array_map('trim', explode(',', $product['color_options'])) : [];

// Gallery images = main image + any extra gallery images
$galleryImages = [$product['image']];
if (!empty($product['gallery'])) {
    foreach (explode(',', $product['gallery']) as $g) {
        $g = trim($g);
        if ($g !== '') $galleryImages[] = $g;
    }
}
$galleryImages = array_values(array_unique(array_filter($galleryImages)));

// Fallback generic size chart (inches) — used only for a size that the
// admin hasn't set custom measurements for on this specific product.
$sizeChart = [
    'XS' => ['chest' => '32-34', 'waist' => '26-28', 'hip' => '34-36'],
    'S'  => ['chest' => '34-36', 'waist' => '28-30', 'hip' => '36-38'],
    'M'  => ['chest' => '36-38', 'waist' => '30-32', 'hip' => '38-40'],
    'L'  => ['chest' => '40-42', 'waist' => '34-36', 'hip' => '42-44'],
    'XL' => ['chest' => '44',    'waist' => '38',    'hip' => '46'],
    'XXL'=> ['chest' => '46-48', 'waist' => '40-42', 'hip' => '48-50'],
];

// Per-product size chart, entered by the admin when the product was
// added/edited (products.size_chart, JSON: {"S":{"chest":"..","waist":"..","hip":".."}, ...}).
// Any size with custom measurements overrides the generic fallback above.
if (!empty($product['size_chart'])) {
    $customChart = json_decode($product['size_chart'], true);
    if (is_array($customChart)) {
        foreach ($customChart as $sizeKey => $row) {
            if (!is_array($row)) continue;
            $sizeKey = strtoupper(trim($sizeKey));
            $chest = trim($row['chest'] ?? '');
            $waist = trim($row['waist'] ?? '');
            $hip = trim($row['hip'] ?? '');
            if ($chest === '' && $waist === '' && $hip === '') continue;
            $sizeChart[$sizeKey] = ['chest' => $chest, 'waist' => $waist, 'hip' => $hip];
        }
    }
}

// ------------------------------------------------------------
// Handle new review submissions (goes to "pending" until an
// admin/employee approves it from the Reviews panel).
// ------------------------------------------------------------
$reviewSubmitted = false;
$reviewErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rName = sanitize($_POST['review_name'] ?? '');
    $rEmail = sanitize($_POST['review_email'] ?? '');
    $rRating = max(1, min(5, (int)($_POST['review_rating'] ?? 0)));
    $rComment = sanitize($_POST['review_comment'] ?? '');

    if ($rName === '') $reviewErrors[] = 'Please enter your name.';
    if ($rComment === '') $reviewErrors[] = 'Please write a short review.';
    if ((int)($_POST['review_rating'] ?? 0) < 1) $reviewErrors[] = 'Please select a star rating.';

    if (empty($reviewErrors)) {
        $custId = isCustomerLoggedIn() ? $_SESSION['customer_id'] : null;
        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, customer_id, customer_name, customer_email, rating, comment, status) VALUES (?,?,?,?,?,?,'pending')");
        $stmt->execute([$id, $custId, $rName, $rEmail, $rRating, $rComment]);
        $reviewSubmitted = true;
    }
}

// Approved reviews + rating summary
$revStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC");
$revStmt->execute([$id]);
$productReviews = $revStmt->fetchAll();
$reviewCount = count($productReviews);
$avgRating = $reviewCount > 0 ? array_sum(array_column($productReviews, 'rating')) / $reviewCount : 0;

// Star-count breakdown for the rating bars (5..1)
$starBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($productReviews as $r) {
    $rt = (int)$r['rating'];
    if (isset($starBreakdown[$rt])) $starBreakdown[$rt]++;
}

include __DIR__ . '/includes/header.php';
?>

<section class="section" style="padding-top:30px;">
    <div class="container">
        <div style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
            <a href="index.php">Home</a> / <a href="products.php">Shop</a> / <a href="products.php?category=<?= (int)$product['category_id'] ?>"><?= sanitize($product['category_name']) ?></a> / <?= sanitize($product['name']) ?>
        </div>

        <div class="product-details">
            <!-- ===== LEFT: GALLERY ===== -->
            <div class="pd-gallery-wrap">
                <?php if (count($galleryImages) > 1): ?>
                <div class="pd-thumbs" id="pdThumbs">
                    <?php foreach ($galleryImages as $i => $img): ?>
                    <button type="button" class="pd-thumb <?= $i === 0 ? 'active' : '' ?>" data-img="assets/uploads/products/<?= sanitize($img) ?>">
                        <img src="assets/uploads/products/<?= sanitize($img) ?>" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=200&q=80'" alt="<?= sanitize($product['name']) ?>">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="pd-main-img-wrap">
                    <div class="pd-zoom-badge" id="pdZoomBadge"><i class="fa-solid fa-magnifying-glass-plus"></i> Zoom</div>
                    <img id="pdMainImg" src="assets/uploads/products/<?= sanitize($galleryImages[0] ?? $product['image']) ?>"
                         onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=700&q=80'"
                         alt="<?= sanitize($product['name']) ?>">
                </div>
            </div>

            <!-- ===== RIGHT: INFO ===== -->
            <div class="pd-info">
                <div class="cat" style="color:var(--text-light);text-transform:uppercase;letter-spacing:1px;font-size:13px;"><?= sanitize($product['category_name']) ?></div>
                <h1 style="font-family:'Cormorant Garamond', serif;letter-spacing:3px;font-weight:600;"><?= sanitize($product['name']) ?></h1>

                <div class="pd-rating-row">
                    <span class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-<?= $i <= round($avgRating) ? 'solid' : 'regular' ?> fa-star"></i>
                        <?php endfor; ?>
                    </span>
                    <span class="count"><?= number_format($avgRating, 1) ?>(<?= $reviewCount ?> review<?= $reviewCount != 1 ? 's' : '' ?>)</span>
                    <a href="#reviewsSection" class="write-review">Write a Review</a>
                </div>

                <div class="pd-price-row">
                    <span class="pd-sale-price"><?= formatPrice($hasDiscount ? $product['discount_price'] : $product['price']) ?></span>
                    <?php if ($hasDiscount): ?>
                        <del class="pd-original-price"><?= formatPrice($product['price']) ?></del>
                        <span class="pd-discount-badge"><?= $discountPct ?>% OFF</span>
                    <?php endif; ?>
                </div>

                <div class="pd-stock <?= $product['stock'] > 0 ? '' : 'out' ?>">
                    <span class="pd-stock-dot"></span>
                    <span><?= $product['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?></span>
                </div>

                <p style="margin:6px 0 16px;color:var(--text-light);"><?= nl2br(sanitize($product['description'])) ?></p>

                <form action="cart.php" method="post" class="ajax-cart-form">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="action" value="add">

                    <?php if ($sizes): ?>
                    <div class="pd-option-group">
                        <div class="pd-option-header">
                            <span class="pd-option-label">SIZE:</span>
                            <span class="pd-selected-size" id="pdSelectedSize">Select Size</span>
                            <button type="button" class="pd-size-chart-btn" id="pdSizeChartBtn"><i class="fa-solid fa-ruler-horizontal"></i> Size Chart</button>
                        </div>
                        <div class="pd-size-grid">
                            <?php foreach ($sizes as $s): ?>
                                <?php $range = $sizeChart[strtoupper($s)]['chest'] ?? ''; ?>
                                <button type="button" class="pd-size-btn" data-size="<?= sanitize($s) ?>">
                                    <span class="sz-label"><?= sanitize($s) ?></span>
                                    <?php if ($range): ?><span class="sz-range"><?= $range ?></span><?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="size" id="pdSizeInput" value="">
                        <p class="pd-size-note"><i class="fa-solid fa-circle-info"></i> Please check the size chart before ordering for the best fit.</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($colors): ?>
                    <div class="pd-option-group">
                        <div class="pd-option-header"><span class="pd-option-label">COLOR:</span></div>
                        <div class="option-group">
                            <?php foreach ($colors as $i => $c): ?>
                            <label>
                                <input type="radio" name="color" value="<?= sanitize($c) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                <span class="<?= $i === 0 ? 'selected' : '' ?>"><?= sanitize($c) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="pd-option-group">
                        <span class="pd-option-label">QUANTITY:</span>
                        <div class="pd-qty-wrap" style="margin-top:8px;">
                            <button type="button" class="pd-qty-btn qty-minus">−</button>
                            <input type="number" class="pd-qty-input" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>">
                            <button type="button" class="pd-qty-btn qty-plus">+</button>
                        </div>
                    </div>

                    <div class="pd-btn-group">
                        <button type="submit" class="pd-cart-btn" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-shopping-bag"></i> ADD TO CART
                        </button>
                        <button type="submit" name="buy_now" value="1" class="pd-order-btn" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-bolt"></i> ORDER NOW
                        </button>
                    </div>
                </form>

                <div class="pd-wishlist-row">
                    <button type="button" class="pd-wishlist-btn" id="pdWishlist">
                        <i class="fa-regular fa-heart"></i> <span>Add to Wishlist</span>
                    </button>
                </div>

                <div class="pd-share-section">
                    <span class="pd-share-label"><i class="fa-solid fa-share-alt"></i> Share This Product:</span>
                    <div class="pd-share-icons">
                        <a class="pd-share-icon pd-share-fb" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://' . ($_SERVER['HTTP_HOST'] ?? '') . '/product-details.php?id=' . $id) ?>"><i class="fa-brands fa-facebook-f"></i></a>
                        <a class="pd-share-icon pd-share-wa" target="_blank" rel="noopener" href="https://api.whatsapp.com/send?text=<?= urlencode($product['name']) ?>"><i class="fa-brands fa-whatsapp"></i></a>
                        <a class="pd-share-icon pd-share-ig" target="_blank" rel="noopener" href="https://www.instagram.com/"><i class="fa-brands fa-instagram"></i></a>
                        <a class="pd-share-icon pd-share-tw" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= urlencode($product['name']) ?>"><i class="fa-brands fa-x-twitter"></i></a>
                        <a class="pd-share-icon pd-share-pt" target="_blank" rel="noopener" href="https://pinterest.com/pin/create/button/"><i class="fa-brands fa-pinterest-p"></i></a>
                        <button type="button" class="pd-share-icon pd-share-copy" id="pdCopyLink"><i class="fa-solid fa-link"></i></button>
                    </div>
                </div>

                <hr class="pd-divider">
                <ul class="pd-meta-list">
                    <li><span class="pd-meta-key">SKU:</span> <?= sanitize($product['sku'] ?: '—') ?></li>
                    <li><span class="pd-meta-key">Category:</span> <a href="products.php?category=<?= (int)$product['category_id'] ?>"><?= sanitize($product['category_name']) ?></a></li>
                </ul>
            </div>
        </div>

        <!-- ===== DESCRIPTIONS / REVIEWS TABS ===== -->
        <section class="pd-tabs-section" id="reviewsSection">
            <div class="pd-tab-nav">
                <button type="button" class="pd-tab-link active" data-tab="descriptions">DESCRIPTIONS</button>
                <button type="button" class="pd-tab-link" data-tab="reviews">REVIEWS (<?= $reviewCount ?>)</button>
            </div>

            <div class="pd-tab-panel active" id="tab-descriptions">
                <div class="pd-desc-body"><?= nl2br(sanitize($product['description'])) ?></div>
            </div>

            <div class="pd-tab-panel" id="tab-reviews">
                <?php if ($reviewSubmitted): ?>
                    <div class="alert alert-success">Thank you! Your review has been submitted and will appear once it's approved.</div>
                <?php elseif ($reviewErrors): ?>
                    <div class="alert alert-error"><?php foreach ($reviewErrors as $e) echo sanitize($e) . '<br>'; ?></div>
                <?php endif; ?>

                <div class="pd-reviews-grid">
                    <div class="pd-rating-summary">
                        <div class="pd-rating-big"><?= number_format($avgRating, 1) ?></div>
                        <div class="pd-rating-stars-big">
                            <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= round($avgRating) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                        </div>
                        <p style="font-size:13px;color:var(--text-light);">Based on <?= $reviewCount ?> review<?= $reviewCount != 1 ? 's' : '' ?></p>
                        <div class="pd-rating-bars">
                            <?php for ($s = 5; $s >= 1; $s--): $pct = $reviewCount > 0 ? round(($starBreakdown[$s] / $reviewCount) * 100) : 0; ?>
                            <div class="pd-rbar-row">
                                <span><?= $s ?> <i class="fa-solid fa-star" style="color:var(--gold);"></i></span>
                                <div class="pd-rbar"><div class="pd-rbar-fill" style="width:<?= $pct ?>%"></div></div>
                                <span><?= $starBreakdown[$s] ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div>
                        <?php if (empty($productReviews)): ?>
                            <p style="color:var(--text-light);margin-bottom:20px;">No reviews yet — be the first to share your thoughts!</p>
                        <?php endif; ?>
                        <?php foreach ($productReviews as $r): ?>
                        <div class="pd-review-card">
                            <div class="pd-rev-header">
                                <div class="pd-rev-avatar"><?= strtoupper(substr($r['customer_name'], 0, 1)) ?></div>
                                <div>
                                    <strong class="pd-rev-name"><?= sanitize($r['customer_name']) ?></strong>
                                    <div class="pd-rev-stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= $r['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></div>
                                </div>
                                <span class="pd-rev-date" style="margin-left:auto;"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                            </div>
                            <p class="pd-rev-text"><?= nl2br(sanitize($r['comment'])) ?></p>
                        </div>
                        <?php endforeach; ?>

                        <form method="post" action="product-details.php?id=<?= (int)$id ?>#reviewsSection" style="margin-top:26px;">
                            <input type="hidden" name="submit_review" value="1">
                            <h5 style="margin-bottom:14px;">Write Your Review</h5>
                            <div class="pd-star-picker" id="pdStarPicker">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label>
                                    <input type="radio" name="review_rating" value="<?= $i ?>" style="display:none;" onclick="document.querySelectorAll('#pdStarPicker span').forEach(function(sp){sp.style.color = (parseInt(sp.dataset.v) <= <?= $i ?>) ? '#F0B23D' : '#ccc';})">
                                    <span data-v="<?= $i ?>">&#9733;</span>
                                </label>
                                <?php endfor; ?>
                            </div>
                            <div class="pd-review-form-row">
                                <input type="text" name="review_name" class="pd-form-input" placeholder="Your Name *" required>
                                <input type="email" name="review_email" class="pd-form-input" placeholder="Your Email (optional)">
                            </div>
                            <textarea name="review_comment" rows="4" class="pd-form-input" style="margin-top:12px;" placeholder="Your Review *" required></textarea>
                            <button type="submit" class="btn" style="margin-top:14px;"><i class="fa-solid fa-paper-plane"></i> Submit Review</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($relatedProducts): ?>
        <div class="section-title" style="margin-top:20px;">
            <span class="eyebrow">You May Also Like</span>
            <h2>Related Products</h2>
        </div>
        <div class="product-grid">
            <?php foreach ($relatedProducts as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===== Size Chart Modal ===== -->
<?php if ($sizes): ?>
<div class="pd-modal-overlay" id="pdSizeChartOverlay"></div>
<div class="pd-size-chart-modal" id="pdSizeChartModal">
    <div class="pd-modal-head">
        <h5><i class="fa-solid fa-ruler-horizontal"></i> Size Guide</h5>
        <button type="button" class="pd-modal-close" id="pdSizeChartClose"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="pd-modal-body">
        <p style="font-size:13px;color:var(--text-light);margin-bottom:14px;">All measurements are in inches. Measure yourself for the best fit.</p>
        <div class="pd-size-table-wrap">
            <table>
                <thead><tr><th>Size</th><th>Chest</th><th>Waist</th><th>Hip</th></tr></thead>
                <tbody>
                <?php foreach ($sizes as $s): $row = $sizeChart[strtoupper($s)] ?? null; if (!$row) continue; ?>
                    <tr><td><strong><?= sanitize($s) ?></strong></td><td><?= $row['chest'] ?></td><td><?= $row['waist'] ?></td><td><?= $row['hip'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pd-size-tips">
            <h6 style="margin-bottom:8px;">How to Measure</h6>
            <ul>
                <li><strong>Chest:</strong> Measure around the fullest part of your chest.</li>
                <li><strong>Waist:</strong> Measure around the narrowest part of your waist.</li>
                <li><strong>Hip:</strong> Measure around the fullest part of your hips.</li>
            </ul>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== Product Image Zoom Lightbox ===== -->
<div class="pd-lightbox" id="pdLightbox">
    <button type="button" class="pd-lightbox-close" id="pdLightboxClose"><i class="fa-solid fa-xmark"></i></button>
    <div class="pd-lightbox-img-wrap">
        <img id="pdLightboxImg" src="" alt="<?= sanitize($product['name']) ?>">
    </div>
    <div class="pd-lightbox-hint">Click the image to zoom in, click again to zoom out</div>
</div>

<script>
(function () {
    /* Gallery thumbnail swap */
    document.querySelectorAll('.pd-thumb').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.pd-thumb').forEach(function (x) { x.classList.remove('active'); });
            t.classList.add('active');
            document.getElementById('pdMainImg').src = t.dataset.img;
        });
    });

    /* ===== Image zoom lightbox ===== */
    var pdLightbox = document.getElementById('pdLightbox');
    var pdLightboxImg = document.getElementById('pdLightboxImg');
    var pdMainImg = document.getElementById('pdMainImg');
    var pdZoomBadge = document.getElementById('pdZoomBadge');
    var lbZoomed = false;

    function openLightbox() {
        pdLightboxImg.src = pdMainImg.src;
        pdLightboxImg.style.transform = 'scale(1)';
        pdLightboxImg.classList.remove('zoomed');
        lbZoomed = false;
        pdLightbox.classList.add('open');
        document.body.classList.add('modal-open');
    }
    function closeLightbox() {
        pdLightbox.classList.remove('open');
        document.body.classList.remove('modal-open');
    }
    if (pdMainImg) pdMainImg.addEventListener('click', openLightbox);
    if (pdZoomBadge) pdZoomBadge.addEventListener('click', function (e) { e.stopPropagation(); openLightbox(); });
    if (pdLightboxImg) pdLightboxImg.addEventListener('click', function (e) {
        e.stopPropagation();
        if (!lbZoomed) {
            var rect = pdLightboxImg.getBoundingClientRect();
            var xPct = ((e.clientX - rect.left) / rect.width) * 100;
            var yPct = ((e.clientY - rect.top) / rect.height) * 100;
            pdLightboxImg.style.transformOrigin = xPct + '% ' + yPct + '%';
            pdLightboxImg.style.transform = 'scale(2.2)';
            pdLightboxImg.classList.add('zoomed');
            lbZoomed = true;
        } else {
            pdLightboxImg.style.transform = 'scale(1)';
            pdLightboxImg.classList.remove('zoomed');
            lbZoomed = false;
        }
    });
    var pdLightboxClose = document.getElementById('pdLightboxClose');
    if (pdLightboxClose) pdLightboxClose.addEventListener('click', closeLightbox);
    if (pdLightbox) pdLightbox.addEventListener('click', function (e) {
        if (e.target === pdLightbox) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && pdLightbox && pdLightbox.classList.contains('open')) closeLightbox();
    });

    /* Size selection */
    document.querySelectorAll('.pd-size-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.pd-size-btn').forEach(function (x) { x.classList.remove('active'); });
            btn.classList.add('active');
            var size = btn.dataset.size;
            document.getElementById('pdSizeInput').value = size;
            document.getElementById('pdSelectedSize').textContent = size;
        });
    });

    /* Size chart modal */
    var chartBtn = document.getElementById('pdSizeChartBtn');
    var chartModal = document.getElementById('pdSizeChartModal');
    var chartOverlay = document.getElementById('pdSizeChartOverlay');
    function openChart() { if (chartModal) { chartModal.style.display = 'block'; chartOverlay.style.display = 'block'; document.body.classList.add('modal-open'); } }
    function closeChart() { if (chartModal) { chartModal.style.display = 'none'; chartOverlay.style.display = 'none'; document.body.classList.remove('modal-open'); } }
    if (chartBtn) chartBtn.addEventListener('click', openChart);
    var chartClose = document.getElementById('pdSizeChartClose');
    if (chartClose) chartClose.addEventListener('click', closeChart);
    if (chartOverlay) chartOverlay.addEventListener('click', closeChart);

    /* Quantity stepper */
    document.querySelectorAll('.qty-minus').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = b.parentElement.querySelector('input');
            input.value = Math.max(parseInt(input.min || 1), (parseInt(input.value) || 1) - 1);
        });
    });
    document.querySelectorAll('.qty-plus').forEach(function (b) {
        b.addEventListener('click', function () {
            var input = b.parentElement.querySelector('input');
            var max = parseInt(input.max) || 999;
            input.value = Math.min(max, (parseInt(input.value) || 1) + 1);
        });
    });

    /* Wishlist toggle (visual only) */
    var wish = document.getElementById('pdWishlist');
    if (wish) wish.addEventListener('click', function () {
        wish.classList.toggle('active');
        var icon = wish.querySelector('i');
        icon.classList.toggle('fa-regular');
        icon.classList.toggle('fa-solid');
    });

    /* Copy link */
    var copyBtn = document.getElementById('pdCopyLink');
    if (copyBtn) copyBtn.addEventListener('click', function () {
        navigator.clipboard.writeText(window.location.href).then(function () {
            copyBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
            setTimeout(function () { copyBtn.innerHTML = '<i class="fa-solid fa-link"></i>'; }, 1500);
        });
    });

    /* Descriptions / Reviews tabs */
    document.querySelectorAll('.pd-tab-link').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.pd-tab-link').forEach(function (x) { x.classList.remove('active'); });
            document.querySelectorAll('.pd-tab-panel').forEach(function (x) { x.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
        });
    });

})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
