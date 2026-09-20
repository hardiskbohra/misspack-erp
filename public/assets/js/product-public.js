/**
 * =====================================================================
 * MissPack ERP - Public Product Page Scripts
 * ---------------------------------------------------------------------
 * Gallery + lightbox for resources/views/products/public.blade.php.
 *
 * The page supplies its media data just before this file:
 *     <script>window.productMediaItems = @json($mediaItems);</script>
 *
 * Load at the end of <body>, after the gallery / lightbox markup.
 * =====================================================================
 */
(function () {
    'use strict';

    const items = window.productMediaItems || [];
    let current = 0;

    function render(i) {
        const main = document.getElementById('mainMedia');
        const item = items[i];
        if (!item) return;
        main.innerHTML = item.type === 'video' ? `<video src="${item.url}" controls playsinline></video>` :
            `<img src="${item.url}" alt="${item.title || 'Product'}" onclick="openLightbox()" style="cursor:zoom-in;">`;
        if (items.length > 1) main.insertAdjacentHTML('beforeend',
            '<button class="arrow left" onclick="prev()">‹</button><button class="arrow right" onclick="next()">›</button>'
        );
        document.querySelectorAll('.thumb').forEach((t, idx) => t.classList.toggle('active', idx === i));
    }

    function next() {
        current = (current + 1) % items.length;
        render(current);
    }

    function prev() {
        current = (current - 1 + items.length) % items.length;
        render(current);
    }

    function openLightbox() {
        if (items[current].type !== 'image') return;

        document.getElementById('lightboxImage').src = items[current].url;
        document.getElementById('lightbox').classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
        document.body.style.overflow = '';
    }

    function lightboxNext() {
        next();
        document.getElementById('lightboxImage').src = items[current].url;
    }

    function lightboxPrev() {
        prev();
        document.getElementById('lightboxImage').src = items[current].url;
    }

    /* Global hooks used by inline onclick attributes (including the
       arrows injected by render()). */
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.lightboxNext = lightboxNext;
    window.lightboxPrev = lightboxPrev;
    window.next = next;
    window.prev = prev;

    document.addEventListener('DOMContentLoaded', () => {
        if (items.length) render(0);
        const t = document.getElementById('thumbs');
        if (t) {
            items.forEach((item, i) => {
                const el = document.createElement(item.type === 'image' ? 'img' : 'button');
                el.className = 'thumb' + (i === 0 ? ' active' : '');
                if (item.type === 'image') {
                    el.src = item.url;
                } else {
                    el.textContent = '▶';
                }
                el.onclick = () => {
                    current = i;
                    render(i);
                };
                t.appendChild(el);
            });
        }
    });

    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        const lb = document.getElementById('lightbox');
        if (!lb || !lb.classList.contains('open')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowRight') lightboxNext();
        if (e.key === 'ArrowLeft') lightboxPrev();
    });
})();
