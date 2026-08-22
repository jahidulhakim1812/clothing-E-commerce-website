<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Home';

$slides = $pdo->query("SELECT * FROM hero_slides WHERE status='active' ORDER BY sort_order ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name ASC")->fetchAll();
$bestsellers = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_bestseller=1 AND p.status='active' ORDER BY p.created_at DESC LIMIT 10")->fetchAll();
$discounted = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.discount_price IS NOT NULL AND p.status='active' ORDER BY p.created_at DESC LIMIT 10")->fetchAll();
$latestArrivals = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
$topPicks = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.featured=1 AND p.status='active' ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
$flashSale = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_flash_sale=1 AND p.status='active' ORDER BY p.flash_sale_end ASC LIMIT 10")->fetchAll();
$flashEndRow = $pdo->query("SELECT MIN(flash_sale_end) as end_at FROM products WHERE is_flash_sale=1 AND status='active'")->fetch();
$flashEnd = $flashEndRow['end_at'] ?? null;

// Shop By Video — a totally independent section. Categories and videos are
// managed only from their own admin pages, never from Products.
$shopVideoCategories = $pdo->query("SELECT * FROM video_categories WHERE status='active' ORDER BY sort_order ASC")->fetchAll();
$shopVideos = $pdo->query("
    SELECT sv.*, vc.name AS category_name,
           p.name AS product_name, p.image AS product_image, p.price AS product_price, p.discount_price AS product_discount_price
    FROM shop_videos sv
    JOIN video_categories vc ON vc.id = sv.category_id AND vc.status='active'
    LEFT JOIN products p ON p.id = sv.product_id
    WHERE sv.status='active'
    ORDER BY sv.sort_order ASC, sv.created_at DESC
")->fetchAll();
$shopVideosByCategory = [];
foreach ($shopVideos as $sv) {
    $catId = $sv['category_id'];
    $shopVideosByCategory[$catId]['name'] = $sv['category_name'];
    $shopVideosByCategory[$catId]['videos'][] = $sv;
}
foreach ($shopVideoCategories as $vc) {
    if (isset($shopVideosByCategory[$vc['id']])) {
        $shopVideosByCategory[$vc['id']]['thumbnail'] = $vc['thumbnail'];
    }
}

// All-product review slider — latest approved reviews across the whole store
$allReviews = $pdo->query("SELECT r.*, p.name AS product_name, p.image AS product_image FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.status='approved' ORDER BY r.created_at DESC LIMIT 20")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Slider -->
<section class="hero-slider">
    <?php foreach ($slides as $i => $slide): ?>
    <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>">
        <img src="assets/uploads/products/<?= sanitize($slide['image']) ?>" alt="<?= sanitize($slide['title']) ?>">
        <div class="hero-content">
            <span class="hero-eyebrow"><?= sanitize(setting('site_tagline', 'Handmade With Heart')) ?></span>
            <h1><?= sanitize($slide['title']) ?></h1>
            <p><?= sanitize($slide['subtitle']) ?></p>
            <a href="<?= sanitize($slide['button_link']) ?>" class="btn btn-gradient"><?= sanitize($slide['button_text']) ?> <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
    <?php endforeach; ?>
    <button class="hero-arrow prev" id="heroPrev"><i class="fa-solid fa-chevron-left"></i></button>
    <button class="hero-arrow next" id="heroNext"><i class="fa-solid fa-chevron-right"></i></button>
    <div class="hero-dots" id="heroDots"></div>
</section>

<div class="stitch-divider"></div>

<!-- Shop By Categories -->
<section class="section">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">Explore</span>
            <h2>Shop By Category</h2>
            <p>Every piece is made by hand — browse our hand-stitched, hand-embroidered clothing collections.</p>
        </div>
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
            <a href="products.php?category=<?= (int)$cat['id'] ?>" class="category-card">
                <div class="hoop">
                    <div class="hoop-knot"></div>
                    <div class="hoop-inner">
                        <img src="assets/uploads/products/<?= sanitize($cat['image']) ?>" alt="<?= sanitize($cat['name']) ?>">
                    </div>
                </div>
                <span><?= sanitize($cat['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="stitch-divider"></div>

<!-- Fan Favorites (horizontal scroll) -->
<section class="section" style="background:var(--cream-2);padding-top:50px;padding-bottom:50px;">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">Loved By Many</span>
            <h2>Fan Favorites</h2>
            <p>The pieces our community keeps coming back for.</p>
        </div>
        <div class="hscroll-wrap">
            <button class="hscroll-arrow left" data-target="fanScroll"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="hscroll" id="fanScroll">
                <?php foreach ($bestsellers as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
            </div>
            <button class="hscroll-arrow right" data-target="fanScroll"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</section>

<div class="stitch-divider"></div>

<!-- Big Offer Banner + Discounted Scroll -->
<section class="section">
    <div class="container">
        <div class="craft-banner">
            <div class="eyebrow">Limited Time</div>
            <h2>Handmade Treasures, Gentler Prices</h2>
            <a href="products.php?filter=discounted" class="btn">Shop The Offers <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="hscroll-wrap">
            <button class="hscroll-arrow left" data-target="offerScroll"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="hscroll" id="offerScroll">
                <?php foreach ($discounted as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
            </div>
            <button class="hscroll-arrow right" data-target="offerScroll"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</section>

<div class="stitch-divider"></div>

<!-- Shop By Video -->
<?php if (!empty($shopVideosByCategory)): ?>
<section class="ws-section">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">Tap. Watch. Shop.</span>
            <h2>Watch And Shop</h2>
            <p>Tap a category, then tap any video to see the collection in action.</p>
        </div>

        <div class="ws-cat-row" id="wsCatRow">
            <?php $firstCat = true; ?>
            <?php foreach ($shopVideosByCategory as $catId => $catData): ?>
            <button type="button" class="ws-cat-item <?= $firstCat ? 'active' : '' ?>" data-cat="<?= sanitize($catId) ?>">
                <span class="ws-cat-circle"><span class="ws-cat-circle-inner">
                    <?php $firstThumb = $catData['thumbnail'] ?: ($catData['videos'][0]['cover_image'] ?: $catData['videos'][0]['product_image']); ?>
                    <?php if ($firstThumb): ?>
                        <img src="assets/uploads/products/<?= sanitize($firstThumb) ?>" onerror="this.parentElement.innerHTML='<i class=\'fa-solid fa-clapperboard\'></i>'" alt="<?= sanitize($catData['name']) ?>">
                    <?php else: ?>
                        <i class="fa-solid fa-clapperboard"></i>
                    <?php endif; ?>
                </span></span>
                <span><?= sanitize($catData['name']) ?></span>
            </button>
            <?php $firstCat = false; ?>
            <?php endforeach; ?>
        </div>

        <?php $firstCat = true; ?>
        <?php foreach ($shopVideosByCategory as $catId => $catData): ?>
        <div class="ws-video-grid" id="wsGrid-<?= sanitize($catId) ?>" style="<?= $firstCat ? '' : 'display:none;' ?>">
            <?php foreach ($catData['videos'] as $idx => $sv):
                $svName = $sv['title'] ?: ($sv['product_name'] ?: $catData['name']);
                $svPrice = $sv['product_id'] ? formatPrice($sv['product_discount_price'] ?: $sv['product_price']) : '';
                $svUrl = $sv['product_id'] ? ('product-details.php?id=' . (int)$sv['product_id']) : ($sv['shop_link'] ?: '');
                $svThumb = $sv['cover_image'] ?: $sv['product_image'];
            ?>
            <div class="ws-video-card"
                 data-cat="<?= sanitize($catId) ?>"
                 data-index="<?= $idx ?>"
                 data-video="assets/uploads/videos/<?= sanitize($sv['video']) ?>"
                 data-title="<?= sanitize($svName) ?>"
                 data-price="<?= sanitize($svPrice) ?>"
                 data-url="<?= sanitize($svUrl) ?>">
                <?php if ($svThumb): ?>
                <img src="assets/uploads/products/<?= sanitize($svThumb) ?>" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=300&q=80'" alt="<?= sanitize($svName) ?>">
                <?php else: ?>
                <video src="assets/uploads/videos/<?= sanitize($sv['video']) ?>" muted preload="metadata"></video>
                <?php endif; ?>
                <div class="ws-card-overlay"></div>
                <div class="ws-play-btn"><i class="fa-solid fa-play"></i></div>
                <div class="ws-card-info">
                    <?php if ($svPrice): ?><span class="ws-card-badge"><?= $svPrice ?></span><?php endif; ?>
                    <p class="ws-card-name"><?= sanitize($svName) ?></p>
                    <?php if ($svUrl): ?><span class="ws-card-cta">Shop Now &rarr;</span><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php $firstCat = false; ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- TikTok/Story-style vertical video popup -->
<div class="tk-modal" id="svModal">
    <div class="tk-modal-inner">
        <div class="tk-progress-bars" id="svProgressBars"></div>
        <div class="tk-top-controls">
            <button type="button" class="fv-side-btn" id="svMuteBtn" style="width:34px;height:34px;font-size:13px;"><i class="fa-solid fa-volume-high" id="svMuteIcon"></i></button>
            <button type="button" class="tk-close" id="svClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="tk-video-wrap">
            <button type="button" class="tk-nav-area tk-prev" id="svPrev"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="tk-video-container">
                <video id="svVideo" playsinline></video>
                <div class="tk-info">
                    <div class="tk-info-title" id="svTitle">Title</div>
                    <div class="tk-info-sub" id="svSubtitle">Price</div>
                    <a href="#" class="tk-shop-btn" id="svShopBtn" style="display:none;">Shop Now <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
            <button type="button" class="tk-nav-area tk-next" id="svNext"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="stitch-divider"></div>

<!-- New Arrivals Tabs -->
<section class="section" style="background:var(--cream-2);">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">Just Stitched</span>
            <h2>New Arrivals</h2>
            <p>Fresh off the sewing table, hand-finished this week.</p>
        </div>
        <div class="tab-buttons">
            <button class="tab-btn active" data-tab="latestTab">Latest</button>
            <button class="tab-btn" data-tab="pickedTab">Top Picks</button>
        </div>
        <div class="tab-content active" id="latestTab">
            <div class="product-grid">
                <?php foreach ($latestArrivals as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
            </div>
        </div>
        <div class="tab-content" id="pickedTab">
            <div class="product-grid">
                <?php foreach ($topPicks as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
            </div>
        </div>
    </div>
</section>

<div class="stitch-divider"></div>

<!-- Flash Sale with Countdown -->
<?php if ($flashSale): ?>
<section class="section">
    <div class="container">
        <div class="flash-sale-header">
            <div class="section-title" style="text-align:left;margin-bottom:0;">
                <span class="eyebrow">Ends Soon</span>
                <h2>Flash Sale</h2>
            </div>
            <?php if ($flashEnd): ?>
            <div class="countdown" id="flashCountdown" data-end="<?= sanitize($flashEnd) ?>">
                <div class="box"><div class="num" id="cd-days">00</div><div class="lbl">Days</div></div>
                <div class="box"><div class="num" id="cd-hours">00</div><div class="lbl">Hrs</div></div>
                <div class="box"><div class="num" id="cd-mins">00</div><div class="lbl">Min</div></div>
                <div class="box"><div class="num" id="cd-secs">00</div><div class="lbl">Sec</div></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="hscroll-wrap">
            <button class="hscroll-arrow left" data-target="flashScroll"><i class="fa-solid fa-chevron-left"></i></button>
            <div class="hscroll" id="flashScroll">
                <?php foreach ($flashSale as $p): include __DIR__ . '/includes/product-card.php'; endforeach; ?>
            </div>
            <button class="hscroll-arrow right" data-target="flashScroll"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="stitch-divider"></div>

<!-- Customer Reviews Slider -->
<?php if (!empty($allReviews)): ?>
<section class="rev-slider-section" style="background:var(--cream-2);">
    <div class="container">
        <div class="section-title">
            <span class="eyebrow">Kind Words</span>
            <h2>What Our Customers Say</h2>
            <p>Real reviews from real orders across our collection.</p>
        </div>
        <div class="rev-slider-wrap">
            <button type="button" class="rev-arrow rev-prev" id="revPrev"><i class="fa-solid fa-chevron-left"></i></button>
            <div id="revSlides">
                <?php foreach ($allReviews as $i => $rv): ?>
                <div class="rev-slide <?= $i === 0 ? 'active' : '' ?>">
                    <div class="stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?><i class="fa-<?= $s <= $rv['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                    </div>
                    <p class="comment">&ldquo;<?= sanitize($rv['comment']) ?>&rdquo;</p>
                    <div class="reviewer">
                        <img src="assets/uploads/products/<?= sanitize($rv['product_image']) ?>" onerror="this.src='https://images.unsplash.com/photo-1523381210434-271e8be1f52b?w=100&q=80'" alt="">
                        <div style="text-align:left;">
                            <div class="reviewer-name"><?= sanitize($rv['customer_name']) ?></div>
                            <div class="reviewer-product">on <?= sanitize($rv['product_name']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="rev-arrow rev-next" id="revNext"><i class="fa-solid fa-chevron-right"></i></button>
            <div class="rev-dots" id="revDots"></div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
    /* ===== Shop By Video: category switcher ===== */
    var catBtns = document.querySelectorAll('.ws-cat-item');
    catBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            catBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            document.querySelectorAll('.ws-video-grid').forEach(function (g) { g.style.display = 'none'; });
            var grid = document.getElementById('wsGrid-' + btn.dataset.cat);
            if (grid) grid.style.display = 'grid';
        });
    });

    /* ===== Shop By Video: TikTok-style popup player ===== */
    var svModal = document.getElementById('svModal');
    if (svModal) {
        var svVideo = document.getElementById('svVideo');
        var svTitle = document.getElementById('svTitle');
        var svSubtitle = document.getElementById('svSubtitle');
        var svShopBtn = document.getElementById('svShopBtn');
        var svProgressBars = document.getElementById('svProgressBars');
        var svMuteBtn = document.getElementById('svMuteBtn');
        var svMuteIcon = document.getElementById('svMuteIcon');
        var currentList = [];
        var currentIndex = 0;

        function buildProgressBars(count) {
            svProgressBars.innerHTML = '';
            for (var i = 0; i < count; i++) {
                var seg = document.createElement('div');
                seg.className = 'seg';
                seg.innerHTML = '<div class="seg-fill"></div>';
                svProgressBars.appendChild(seg);
            }
        }
        function updateProgressBars() {
            var segs = svProgressBars.querySelectorAll('.seg-fill');
            segs.forEach(function (s, i) {
                s.style.width = i < currentIndex ? '100%' : '0%';
            });
        }
        function loadCard(card) {
            var cat = card.dataset.cat;
            currentList = Array.prototype.slice.call(document.querySelectorAll('.ws-video-card[data-cat="' + cat + '"]'));
            currentIndex = currentList.indexOf(card);
            buildProgressBars(currentList.length);
            playCurrent();
        }
        function playCurrent() {
            var card = currentList[currentIndex];
            if (!card) return;
            svVideo.src = card.dataset.video;
            svVideo.muted = false;
            if (svMuteIcon) svMuteIcon.className = 'fa-solid fa-volume-high';
            svTitle.textContent = card.dataset.title;
            svSubtitle.textContent = card.dataset.price;
            if (card.dataset.url) {
                svShopBtn.href = card.dataset.url;
                svShopBtn.style.display = 'inline-flex';
            } else {
                svShopBtn.style.display = 'none';
            }
            svVideo.currentTime = 0;
            svVideo.play().catch(function () {});
            updateProgressBars();
        }

        document.querySelectorAll('.ws-video-card').forEach(function (card) {
            card.addEventListener('click', function () {
                loadCard(card);
                svModal.classList.add('open');
                document.body.classList.add('modal-open');
            });
        });

        document.getElementById('svClose').addEventListener('click', function () {
            svModal.classList.remove('open');
            document.body.classList.remove('modal-open');
            svVideo.pause();
        });
        document.getElementById('svPrev').addEventListener('click', function () {
            if (currentIndex > 0) { currentIndex--; playCurrent(); }
        });
        document.getElementById('svNext').addEventListener('click', function () {
            if (currentIndex < currentList.length - 1) { currentIndex++; playCurrent(); }
        });
        if (svMuteBtn) svMuteBtn.addEventListener('click', function () {
            svVideo.muted = !svVideo.muted;
            svMuteIcon.className = svVideo.muted ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-volume-high';
        });
        svVideo.addEventListener('timeupdate', function () {
            var segs = svProgressBars.querySelectorAll('.seg-fill');
            var seg = segs[currentIndex];
            if (seg) seg.style.width = ((svVideo.currentTime / (svVideo.duration || 1)) * 100) + '%';
        });
        svVideo.addEventListener('ended', function () {
            if (currentIndex < currentList.length - 1) { currentIndex++; playCurrent(); }
            else { svModal.classList.remove('open'); document.body.classList.remove('modal-open'); }
        });
        /* Escape key closes the popup */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && svModal.classList.contains('open')) {
                svModal.classList.remove('open');
                document.body.classList.remove('modal-open');
                svVideo.pause();
            }
        });
    }

    /* ===== Reviews slider (auto-advancing) ===== */
    var revSlides = document.querySelectorAll('.rev-slide');
    if (revSlides.length) {
        var revIndex = 0;
        var revDots = document.getElementById('revDots');
        revSlides.forEach(function (_, i) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'rev-dot' + (i === 0 ? ' active' : '');
            dot.addEventListener('click', function () { showRev(i); resetRevTimer(); });
            revDots.appendChild(dot);
        });
        function showRev(i) {
            revSlides.forEach(function (s) { s.classList.remove('active'); });
            revDots.querySelectorAll('.rev-dot').forEach(function (d) { d.classList.remove('active'); });
            revIndex = (i + revSlides.length) % revSlides.length;
            revSlides[revIndex].classList.add('active');
            revDots.children[revIndex].classList.add('active');
        }
        var revTimer = setInterval(function () { showRev(revIndex + 1); }, 5000);
        function resetRevTimer() { clearInterval(revTimer); revTimer = setInterval(function () { showRev(revIndex + 1); }, 5000); }
        document.getElementById('revPrev').addEventListener('click', function () { showRev(revIndex - 1); resetRevTimer(); });
        document.getElementById('revNext').addEventListener('click', function () { showRev(revIndex + 1); resetRevTimer(); });
    }
})();
</script>
