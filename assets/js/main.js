// ============================================================
// Stitch & Souls - Main JS
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Mobile Nav Toggle ---------- */
    const mobileToggle = document.getElementById('mobileToggle');
    const navLinks = document.getElementById('navLinks');
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function () {
            navLinks.classList.toggle('open');
        });
        // toggle dropdown on mobile tap
        document.querySelectorAll('.dropdown > a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                if (window.innerWidth <= 992) {
                    e.preventDefault();
                    this.parentElement.classList.toggle('open');
                }
            });
        });
    }

    /* ---------- Hero Slider ---------- */
    const slides = document.querySelectorAll('.hero-slide');
    const dotsWrap = document.getElementById('heroDots');
    let current = 0;
    let sliderInterval;

    function showSlide(index) {
        slides.forEach(function (s, i) {
            s.classList.toggle('active', i === index);
        });
        if (dotsWrap) {
            dotsWrap.querySelectorAll('button').forEach(function (d, i) {
                d.classList.toggle('active', i === index);
            });
        }
        // restart ken-burns animation
        const activeImg = slides[index] ? slides[index].querySelector('img') : null;
        if (activeImg) {
            activeImg.style.animation = 'none';
            void activeImg.offsetWidth;
            activeImg.style.animation = '';
        }
        current = index;
    }

    function nextSlide() { showSlide((current + 1) % slides.length); }
    function prevSlide() { showSlide((current - 1 + slides.length) % slides.length); }

    function startAutoSlide() {
        sliderInterval = setInterval(nextSlide, 5500);
    }

    if (slides.length > 0) {
        if (dotsWrap) {
            slides.forEach(function (_, i) {
                const btn = document.createElement('button');
                if (i === 0) btn.classList.add('active');
                btn.addEventListener('click', function () {
                    clearInterval(sliderInterval);
                    showSlide(i);
                    startAutoSlide();
                });
                dotsWrap.appendChild(btn);
            });
        }
        const nextBtn = document.getElementById('heroNext');
        const prevBtn = document.getElementById('heroPrev');
        if (nextBtn) nextBtn.addEventListener('click', function () { clearInterval(sliderInterval); nextSlide(); startAutoSlide(); });
        if (prevBtn) prevBtn.addEventListener('click', function () { clearInterval(sliderInterval); prevSlide(); startAutoSlide(); });

        startAutoSlide();
    }

    /* ---------- Quantity Input (product details / cart) ---------- */
    document.querySelectorAll('.qty-input').forEach(function (wrap) {
        const input = wrap.querySelector('input');
        const minus = wrap.querySelector('.qty-minus');
        const plus = wrap.querySelector('.qty-plus');
        if (minus) minus.addEventListener('click', function () {
            let v = parseInt(input.value || '1', 10);
            if (v > 1) input.value = v - 1;
        });
        if (plus) plus.addEventListener('click', function () {
            let v = parseInt(input.value || '1', 10);
            input.value = v + 1;
        });
    });

    /* ---------- Option pills (size/color) ---------- */
    document.querySelectorAll('.option-group').forEach(function (group) {
        group.querySelectorAll('span').forEach(function (label) {
            label.addEventListener('click', function () {
                group.querySelectorAll('span').forEach(function (s) { s.classList.remove('selected'); });
                this.classList.add('selected');
            });
        });
    });

    /* ---------- Payment method selection highlight ---------- */
    document.querySelectorAll('.payment-options input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.payment-options label').forEach(function (l) {
                l.style.borderColor = '';
            });
            this.closest('label').style.borderColor = 'var(--wine)';
        });
    });

    /* ---------- Auto-hide alerts ---------- */
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 4000);
    });

    /* ---------- Confirm delete links ---------- */
    document.querySelectorAll('.confirm-delete').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    /* ---------- Horizontal Scroll Carousel Arrows ---------- */
    document.querySelectorAll('.hscroll-arrow').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            if (!target) return;
            const scrollAmount = target.clientWidth * 0.8;
            target.scrollBy({
                left: this.classList.contains('left') ? -scrollAmount : scrollAmount,
                behavior: 'smooth'
            });
        });
    });

    /* ---------- Tab Switching (New Arrivals etc.) ---------- */
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const tabGroup = this.closest('.section') || document;
            tabGroup.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
            tabGroup.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });
            this.classList.add('active');
            const target = document.getElementById(this.dataset.tab);
            if (target) target.classList.add('active');
        });
    });

    /* ---------- Flash Sale Countdown ---------- */
    const countdownEl = document.getElementById('flashCountdown');
    if (countdownEl && countdownEl.dataset.end) {
        const endTime = new Date(countdownEl.dataset.end.replace(' ', 'T')).getTime();
        const dEl = document.getElementById('cd-days');
        const hEl = document.getElementById('cd-hours');
        const mEl = document.getElementById('cd-mins');
        const sEl = document.getElementById('cd-secs');

        function pad(n) { return n < 10 ? '0' + n : '' + n; }

        function tick() {
            const now = Date.now();
            let diff = Math.max(0, endTime - now);
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const mins = Math.floor((diff / (1000 * 60)) % 60);
            const secs = Math.floor((diff / 1000) % 60);
            if (dEl) dEl.textContent = pad(days);
            if (hEl) hEl.textContent = pad(hours);
            if (mEl) mEl.textContent = pad(mins);
            if (sEl) sEl.textContent = pad(secs);
            if (diff <= 0) clearInterval(countdownInterval);
        }
        tick();
        const countdownInterval = setInterval(tick, 1000);
    }

    /* ---------- Slide-Out Cart Drawer ---------- */
    const cartDrawer = document.getElementById('cartDrawer');
    const cartOverlay = document.getElementById('cartDrawerOverlay');
    const cartToggle = document.getElementById('cartDrawerToggle');
    const cartClose = document.getElementById('cartDrawerClose');
    const cartBody = document.getElementById('cartDrawerBody');
    const cartFooter = document.getElementById('cartDrawerFooter');
    const cartBadge = document.getElementById('cartBadge');
    const cartSubtotal = document.getElementById('drawerSubtotal');

    function openDrawer() {
        if (!cartDrawer) return;
        cartDrawer.classList.add('open');
        if (cartOverlay) cartOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        if (!cartDrawer) return;
        cartDrawer.classList.remove('open');
        if (cartOverlay) cartOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    if (cartToggle) {
        cartToggle.addEventListener('click', function (e) {
            e.preventDefault();
            openDrawer();
        });
    }
    if (cartClose) cartClose.addEventListener('click', closeDrawer);
    if (cartOverlay) cartOverlay.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDrawer();
    });

    function updateDrawer(data) {
        if (cartBody) cartBody.innerHTML = data.items_html;
        if (cartBadge) cartBadge.textContent = data.count;
        if (cartSubtotal) cartSubtotal.textContent = data.subtotal_formatted;
        if (cartFooter) cartFooter.style.display = data.empty ? 'none' : 'block';
    }

    function cartAjax(params) {
        return fetch('ajax/cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(params).toString()
        }).then(function (res) { return res.json(); });
    }

    // Intercept "Add to Cart" form submissions (skip Buy Now — that needs a real page navigation to checkout)
    document.querySelectorAll('.ajax-cart-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const submitter = e.submitter;
            if (submitter && submitter.name === 'buy_now') return; // let it submit normally

            e.preventDefault();
            const formData = new FormData(form);
            const params = { action: 'add' };
            formData.forEach(function (value, key) { if (key !== 'action') params[key] = value; });

            const btn = submitter || form.querySelector('button[type="submit"]');
            const originalHtml = btn ? btn.innerHTML : null;
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }

            cartAjax(params).then(function (data) {
                if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                if (data.success) {
                    updateDrawer(data);
                    openDrawer();
                } else {
                    alert(data.message || 'Could not add item to cart.');
                }
            }).catch(function () {
                if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                window.location.href = 'cart.php'; // fallback
            });
        });
    });

    // Delegated handlers for in-drawer qty +/- and remove (content is replaced dynamically)
    if (cartBody) {
        cartBody.addEventListener('click', function (e) {
            const qtyWrap = e.target.closest('.drawer-qty');
            const removeBtn = e.target.closest('.drawer-remove');

            if (qtyWrap && (e.target.classList.contains('drawer-qty-minus') || e.target.classList.contains('drawer-qty-plus'))) {
                const key = qtyWrap.dataset.key;
                const span = qtyWrap.querySelector('span');
                let qty = parseInt(span.textContent, 10);
                qty = e.target.classList.contains('drawer-qty-plus') ? qty + 1 : qty - 1;
                if (qty < 1) qty = 1;
                cartAjax({ action: 'update', key: key, quantity: qty }).then(updateDrawer);
            }

            if (removeBtn) {
                cartAjax({ action: 'remove', key: removeBtn.dataset.key }).then(updateDrawer);
            }
        });
    }
});
