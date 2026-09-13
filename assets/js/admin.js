// ============================================================
// Admin Panel JS - Sidebar toggle, dark mode, notifications, modals
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

    const sidebar = document.getElementById('adminSidebar');
    const mainContent = document.getElementById('adminMain');
    const mobileToggle = document.getElementById('topbarMobileToggle');
    const desktopToggle = document.getElementById('sidebarToggle');

    /* ---------- Mobile slide-in sidebar (hamburger button, ≤992px only) ---------- */
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.add('open');
        document.body.classList.add('no-scroll');
        if (mobileToggle) {
            mobileToggle.classList.add('is-active');
            mobileToggle.setAttribute('aria-expanded', 'true');
        }
    }
    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('open');
        document.body.classList.remove('no-scroll');
        if (mobileToggle) {
            mobileToggle.classList.remove('is-active');
            mobileToggle.setAttribute('aria-expanded', 'false');
        }
    }
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function () {
            if (sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeMobileSidebar);
    }
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 992 && sidebar && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && e.target !== mobileToggle) {
                closeMobileSidebar();
            }
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMobileSidebar();
    });

    /* ---------- Desktop collapse (icon-only rail), pinned chevron on the sidebar edge, >992px only ---------- */
    function setCollapsed(collapsed, persist) {
        if (sidebar) sidebar.classList.toggle('collapsed', collapsed);
        if (mainContent) mainContent.classList.toggle('collapsed', collapsed);
        if (desktopToggle) {
            desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            desktopToggle.setAttribute('title', label);
            desktopToggle.setAttribute('aria-label', label);
        }
        if (persist !== false) localStorage.setItem('adminSidebarCollapsed', collapsed ? '1' : '0');
    }
    // Restore the last collapsed state on desktop only — never on mobile, and never persisted from a mobile resize.
    if (window.innerWidth > 992 && localStorage.getItem('adminSidebarCollapsed') === '1') {
        setCollapsed(true, false);
    }
    if (desktopToggle) {
        desktopToggle.addEventListener('click', function () {
            setCollapsed(!sidebar.classList.contains('collapsed'), true);
            // Nudge any charts (or other responsive canvases) on the page to
            // recompute their size once the collapse/expand transition settles,
            // so nothing is left the wrong width or clipped after toggling.
            setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 220);
        });
    }

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 992) {
                closeMobileSidebar();
            } else {
                setCollapsed(false, false);
            }
        }, 120);
    });

    /* ---------- Dark mode toggle — persisted across pages ---------- */
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const next = isDark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('adminTheme', next);
            document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
        });
    }

    /* ---------- Admin user profile dropdown ---------- */
    const adminUserBtn = document.getElementById('adminUserBtn');
    const adminUserWrap = adminUserBtn ? adminUserBtn.closest('.admin-user-wrap') : null;
    const notifPanelEl = document.getElementById('notifPanel');
    if (adminUserBtn && adminUserWrap) {
        adminUserBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (notifPanelEl) notifPanelEl.classList.remove('open');
            adminUserWrap.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!adminUserWrap.contains(e.target)) {
                adminUserWrap.classList.remove('open');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') adminUserWrap.classList.remove('open');
        });
    }

    /* ---------- Notification dropdown ---------- */
    const notifBtn = document.getElementById('notifBtn');
    const notifPanel = document.getElementById('notifPanel');
    if (notifBtn && notifPanel) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (adminUserWrap) adminUserWrap.classList.remove('open');
            notifPanel.classList.toggle('open');
        });
        document.addEventListener('click', function (e) {
            if (!notifPanel.contains(e.target) && e.target !== notifBtn) {
                notifPanel.classList.remove('open');
            }
        });
        const markAllBtn = document.getElementById('notifMarkAll');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                fetch('notifications-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_all'
                }).then(function () {
                    notifPanel.querySelectorAll('.notif-item.unread').forEach(function (item) {
                        item.classList.remove('unread');
                    });
                    const dot = document.getElementById('notifDot');
                    if (dot) dot.remove();
                    const countBadge = document.getElementById('notifCount');
                    if (countBadge) countBadge.remove();
                });
            });
        }
        notifPanel.querySelectorAll('.notif-item[data-notif-id]').forEach(function (item) {
            item.addEventListener('click', function () {
                if (!item.classList.contains('unread')) return;
                const id = item.dataset.notifId;
                fetch('notifications-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_one&id=' + encodeURIComponent(id)
                });
                item.classList.remove('unread');
            });
        });
    }

    /* Expandable nav groups (e.g. Reports submenu) */
    document.querySelectorAll('.nav-parent').forEach(function (parent) {
        parent.addEventListener('click', function () {
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('nav-submenu')) {
                submenu.classList.toggle('open');
                this.classList.toggle('open');
            }
        });
    });

    /* Modal open/close */
    document.querySelectorAll('[data-modal-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = document.getElementById(this.dataset.modalTarget);
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            this.closest('.modal-overlay').classList.remove('open');
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    /* Confirm delete */
    document.querySelectorAll('.confirm-delete').forEach(function (link) {
        link.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    /* Auto-hide alerts */
    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 4000);
    });
});
