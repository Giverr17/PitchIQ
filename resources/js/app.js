/**
 * PitchIQ — Global Application JS
 * Handles: Mobile nav, scroll animations, counter animation, FAQ accordion
 */

// ─── Material Symbols font-ready reveal ──────────────────────────────────────
// Inline CSS in <head> sets visibility:hidden on .material-symbols-outlined so
// raw ligature text (sports_soccer, expand_more…) never shows on slow networks.
// Once the font is ready, add icons-ready to <html> to make them visible.
;(function () {
    var reveal = function () { document.documentElement.classList.add('icons-ready'); };
    if ('fonts' in document) {
        document.fonts.load('1em "Material Symbols Outlined"').then(reveal, reveal);
    } else {
        reveal();
    }
})();

// ─── SortableJS (self-hosted, works offline) ─────────────────────────────────
import Sortable from 'sortablejs';
window.Sortable = Sortable;

// ─── Table → PDF (self-hosted, works offline) ────────────────────────────────
// Builds a real vector PDF from the table's TEXT (jsPDF + autotable). No DOM
// rasterization → unaffected by mobile canvas limits, oklch/color-mix or CSP,
// and long tables paginate automatically. This is the reliable cross-device path.
// jsPDF is heavy, so it's loaded ON DEMAND (dynamic import) — only when the user
// actually clicks Download — keeping every page's initial bundle small.
window.exportTableAsPdf = async function (tableSelector, filename = 'pitchiq.pdf', title = '') {
    const table = document.querySelector(tableSelector);
    if (!table) { window.showAppError?.('Nothing to export yet.'); return; }
    try {
        const [{ jsPDF }, { default: autoTable }] = await Promise.all([
            import('jspdf'),
            import('jspdf-autotable'),
        ]);

        const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
        let y = 44;

        if (title) {
            doc.setFontSize(15);
            doc.setTextColor(17, 17, 17);
            doc.text(title, 40, y);
            y += 12;
        }
        doc.setFontSize(9);
        doc.setTextColor(130, 130, 130);
        doc.text(new Date().toLocaleString(), 40, y);

        autoTable(doc, {
            html: table,
            startY: y + 12,
            styles: { fontSize: 9, cellPadding: 5, overflow: 'linebreak' },
            headStyles: { fillColor: [0, 230, 118], textColor: [0, 0, 0], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [245, 247, 245] },
            theme: 'striped',
            margin: { top: 40, right: 40, bottom: 40, left: 40 },
        });

        doc.save(filename);
    } catch (e) {
        console.error('PDF export failed:', e);
        window.showAppError?.('Could not generate the PDF. Please try again.');
    }
};

// ─── Element → downloadable PNG (html2canvas-pro, lazy-loaded) ───────────────
// html2canvas-pro rasterizes the DOM directly (no SVG `data:` <img> step), so it
// works on older mobile where html-to-image's SVG→<img> step failed. The "pro"
// fork natively understands Tailwind v4 oklch() / color-mix(), so NO colour hacks
// are needed. Loaded on demand so it never bloats the initial page bundle.
window.exportElementAsImage = async function (elId, filename = 'pitchiq.png') {
    const node = document.getElementById(elId);
    if (!node) return;
    try {
        const { default: html2canvas } = await import('html2canvas-pro');

        // Bound the raster so a big leaderboard stays within mobile canvas limits.
        const longest = Math.max(node.offsetWidth, node.offsetHeight, 1);
        const scale = Math.max(1, Math.min(2, 2000 / longest));

        const canvas = await html2canvas(node, {
            backgroundColor: '#0d110f', // solid dark bg (no transparency)
            scale,
            useCORS: true,
            logging: false,
        });

        // toBlob is gentler on mobile memory than a giant data: URL.
        canvas.toBlob((blob) => {
            if (!blob) { window.showAppError?.('Could not generate the image.'); return; }
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.download = filename;
            link.href = url;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, 'image/png');
    } catch (e) {
        console.error('Image export failed:', e);
        const reason = e && (e.name || e.message) ? ` (${e.name || ''}: ${e.message || ''})` : '';
        window.showAppError?.('Could not generate the image' + reason);
    }
};

// ─── Squad Builder drag-and-drop ─────────────────────────────────────────────
// Reinit Sortable only when the draggable item count changes (search/filter
// added or removed rows). With wire:key on player rows, Livewire morphs them
// in-place, so Sortable bindings survive picked/unpicked class changes without
// a full destroy-recreate on every server round-trip.
let _pitchSortable = null;
let _listSortable  = null;
let _listItemCount = 0;

function initSquadSortable() {
    const pitchEl = document.getElementById('pitch-surface');
    const listEl  = document.getElementById('player-list-sortable');
    if (!pitchEl || !listEl) return;

    // On touch-only devices (phones/tablets) the pitch and player list stack
    // vertically, so drag-to-pitch is not useful. More importantly, SortableJS
    // applies its `sortable-chosen` class on every touchstart — even during the
    // delay window — causing every player row to highlight while the user scrolls.
    // The +/- tap buttons handle add/remove perfectly on touch devices.
    if (!window.matchMedia('(min-width: 768px)').matches) return;

    if (_pitchSortable) { _pitchSortable.destroy(); _pitchSortable = null; }
    if (_listSortable)  { _listSortable.destroy();  _listSortable  = null; }

    _listItemCount = listEl.querySelectorAll('[data-player-id]').length;

    _listSortable = new Sortable(listEl, {
        group:     { name: 'squad', pull: 'clone', put: false },
        sort:      false,
        animation: 150,
        draggable: '[data-player-id]',
        onStart: (evt) => {
            pitchEl.setAttribute('data-dragging', evt.item.getAttribute('data-position') ?? '');
        },
        onEnd: () => pitchEl.removeAttribute('data-dragging'),
    });

    _pitchSortable = new Sortable(pitchEl, {
        group:     { name: 'squad', put: true },
        sort:      false,
        animation: 150,
        onAdd: (evt) => {
            const id = parseInt(evt.item.getAttribute('data-player-id'));
            evt.item.remove();

            if (!id) return;

            // Instant feedback: dim the source row while the Livewire request
            // travels through the server. Livewire's morph will restore it
            // with the correct picked state once the response arrives.
            const row = listEl.querySelector(`[data-player-id="${id}"]`);
            if (row) {
                row.style.opacity       = '0.35';
                row.style.pointerEvents = 'none';
            }

            window.dispatchEvent(new CustomEvent('squad-player-dropped', { detail: { id } }));
        },
    });
}

// ─── Friendly error toast (replaces Livewire's full-screen error modal) ───────
// Shown when a Livewire request fails (timeout, 500, network drop) so users see a
// short message instead of a raw "Maximum execution time exceeded" page popup.
function showAppError(message) {
    let el = document.getElementById('app-error-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'app-error-toast';
        el.style.cssText =
            'position:fixed;z-index:99999;left:16px;right:16px;bottom:16px;margin:0 auto;' +
            'max-width:420px;padding:14px 18px;border-radius:14px;' +
            'background:#1a0d0d;border:1px solid rgba(239,68,68,0.45);color:#fca5a5;' +
            'font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;font-weight:600;' +
            'line-height:1.45;box-shadow:0 12px 40px rgba(0,0,0,.55);display:none;';
        document.body.appendChild(el);
    }
    el.textContent = message;
    el.style.display = 'block';
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(() => { el.style.display = 'none'; }, 6000);
}
window.showAppError = showAppError;

document.addEventListener('livewire:initialized', () => {
    initSquadSortable();

    // Intercept failed Livewire requests: suppress the default error modal and
    // surface a clear, friendly message instead.
    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            preventDefault(); // stop Livewire from rendering its error-page modal

            let msg;
            if (status === 0)        msg = 'Network problem — check your connection and try again.';
            else if (status === 419) msg = 'Your session expired. Please refresh the page.';
            else if (status === 429) msg = 'You’re going a bit fast — wait a moment and try again.';
            else if (status >= 500)  msg = 'Something went wrong on our end. Please try again.';
            else                     msg = 'That action couldn’t be completed. Please try again.';

            showAppError(msg);
        });
    });

    Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
        succeed(({ snapshot, effect }) => {
            const listEl = document.getElementById('player-list-sortable');
            if (!listEl) return;

            // Only reinit when rows are added or removed (position filter, search,
            // fixture step change). Pure class changes — picked highlight toggling —
            // don't need a reinit because wire:key keeps DOM node identity stable.
            const newCount = listEl.querySelectorAll('[data-player-id]').length;
            if (newCount !== _listItemCount) {
                setTimeout(initSquadSortable, 0);
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {

    // ─────────────────────────────────────────────────────────
    // 1. MOBILE NAV TOGGLE
    // ─────────────────────────────────────────────────────────
    const navToggle   = document.getElementById('nav-toggle');
    const mobileMenu  = document.getElementById('mobile-menu');
    const navOverlay  = document.getElementById('nav-overlay');

    function openMobileMenu() {
        mobileMenu?.classList.remove('-translate-x-full');
        mobileMenu?.classList.add('translate-x-0');
        navOverlay?.classList.remove('hidden');
        requestAnimationFrame(() => navOverlay?.classList.remove('opacity-0'));
        document.body.style.overflow = 'hidden';
        navToggle?.setAttribute('aria-expanded', 'true');
        navToggle?.classList.add('open');
    }

    function closeMobileMenu() {
        mobileMenu?.classList.add('-translate-x-full');
        mobileMenu?.classList.remove('translate-x-0');
        navOverlay?.classList.add('opacity-0');
        setTimeout(() => navOverlay?.classList.add('hidden'), 250);
        document.body.style.overflow = '';
        navToggle?.setAttribute('aria-expanded', 'false');
        navToggle?.classList.remove('open');
    }

    window.closeMobileMenu = closeMobileMenu;

    navToggle?.addEventListener('click', () => {
        const isOpen = mobileMenu?.classList.contains('translate-x-0');
        isOpen ? closeMobileMenu() : openMobileMenu();
    });

    navOverlay?.addEventListener('click', closeMobileMenu);

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMobileMenu();
    });

    // ─────────────────────────────────────────────────────────
    // 2. NAVBAR SCROLL EFFECT
    // ─────────────────────────────────────────────────────────
    const navbar = document.getElementById('navbar');
    if (navbar) {
        const handleScroll = () => {
            navbar.classList.toggle('nav-scrolled', window.scrollY > 24);
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll(); // Run on load
    }

    // ─────────────────────────────────────────────────────────
    // 3. HIGHLIGHT ACTIVE NAV LINK
    // ─────────────────────────────────────────────────────────
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath || (href !== '/' && currentPath.startsWith(href))) {
            link.classList.add('active');
        }
    });

    // ─────────────────────────────────────────────────────────
    // 4. SCROLL ANIMATIONS (IntersectionObserver)
    // ─────────────────────────────────────────────────────────
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const animEls = document.querySelectorAll('.anim-on-scroll');

    if (!prefersReducedMotion && animEls.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -32px 0px' });

        animEls.forEach(el => observer.observe(el));
    } else {
        animEls.forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
    }

    // ─────────────────────────────────────────────────────────
    // 5. COUNTER ANIMATION
    // ─────────────────────────────────────────────────────────
    const counters = document.querySelectorAll('[data-count]');
    if (counters.length) {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const el     = entry.target;
                const target = parseFloat(el.getAttribute('data-count'));
                const suffix = el.getAttribute('data-suffix') || '';
                const prefix = el.getAttribute('data-prefix') || '';
                const decimals = (target % 1 !== 0) ? 1 : 0;
                const duration = 1600;
                const steps = 60;
                let current = 0;
                const increment = target / steps;
                let step = 0;

                const timer = setInterval(() => {
                    step++;
                    current = Math.min(target, increment * step);
                    el.textContent = prefix + current.toFixed(decimals) + suffix;
                    if (current >= target) clearInterval(timer);
                }, duration / steps);

                counterObserver.unobserve(el);
            });
        }, { threshold: 0.5 });

        counters.forEach(el => counterObserver.observe(el));
    }

    // ─────────────────────────────────────────────────────────
    // 6. FAQ ACCORDION
    // ─────────────────────────────────────────────────────────
    document.querySelectorAll('[data-faq-trigger]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const targetId = trigger.getAttribute('data-faq-trigger');
            const content  = document.getElementById(targetId);
            const icon     = trigger.querySelector('[data-faq-icon]');
            const isOpen   = content && !content.classList.contains('max-h-0');

            // Close all
            document.querySelectorAll('[data-faq-content]').forEach(c => {
                c.classList.add('max-h-0', 'opacity-0');
                c.classList.remove('max-h-96', 'opacity-100');
            });
            document.querySelectorAll('[data-faq-icon]').forEach(i => {
                i.style.transform = 'rotate(0deg)';
            });

            // Open clicked if it was closed
            if (!isOpen && content) {
                content.classList.remove('max-h-0', 'opacity-0');
                content.classList.add('max-h-96', 'opacity-100');
                if (icon) icon.style.transform = 'rotate(45deg)';
            }
        });
    });

    // ─────────────────────────────────────────────────────────
    // 7. FILTER TABS (Games / Events pages)
    // ─────────────────────────────────────────────────────────
    document.querySelectorAll('[data-filter-group]').forEach(group => {
        const groupName = group.getAttribute('data-filter-group');
        group.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                const filter = btn.getAttribute('data-filter');

                // Update button states
                group.querySelectorAll('[data-filter]').forEach(b => {
                    b.classList.remove('bg-primary-container', 'text-on-primary', 'border-primary-container');
                    b.classList.add('text-on-surface-variant', 'border-outline-variant');
                });
                btn.classList.add('bg-primary-container', 'text-on-primary', 'border-primary-container');
                btn.classList.remove('text-on-surface-variant', 'border-outline-variant');

                // Show/hide cards
                document.querySelectorAll(`[data-filter-target="${groupName}"]`).forEach(card => {
                    const cardFilter = card.getAttribute('data-filter-value');
                    const show = filter === 'all' || cardFilter === filter;
                    card.style.display = show ? '' : 'none';
                });
            });
        });
    });

    // ─────────────────────────────────────────────────────────
    // 8. SQUAD BUILDER — Formation switcher
    // ─────────────────────────────────────────────────────────
    document.querySelectorAll('[data-formation]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-formation]').forEach(b =>
                b.classList.remove('bg-primary-container', 'text-background')
            );
            btn.classList.add('bg-primary-container', 'text-background');
        });
    });
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
