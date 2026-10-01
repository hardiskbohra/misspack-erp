/* ==========================================================================
   LEADS-PUBLIC.JS — Standalone public lead pages
   --------------------------------------------------------------------------
   - public-create.blade.php: submit confirmation + flash toasts.
     The page supplies the flash state just before this file:
         <script>window.leadPublicFlash = @json([...]);</script>
   - public-show.blade.php: product media gallery (image/video).
     The page supplies the media list just before this file:
         <script>window.leadMediaItems = @json($media);</script>
   Both sections are guarded, so the same file loads on either page.
   ========================================================================== */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* ------------------------------------------------------------------
       Public create page — flash toasts + submit confirmation
       ------------------------------------------------------------------ */
    function initCreatePage() {
        var flash = window.leadPublicFlash;

        if (flash && window.MasterAlert) {
            if (flash.success) {
                MasterAlert.toast(flash.success, 'success', { title: 'Requirement Submitted' });
            }
            if (flash.error) {
                MasterAlert.alert(flash.error, { title: 'Please check the form', type: 'error', danger: true });
            }
        }

        var form = document.getElementById('publicLeadForm');
        if (!form) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (typeof window.MasterAlert === 'undefined') {
                form.submit();
                return;
            }
            MasterAlert.confirm(
                'Please confirm that your product requirement details are correct.',
                { title: 'Submit requirement?', confirmText: 'Yes, Submit', cancelText: 'Review Again', danger: false }
            ).then(function (ok) {
                if (ok) form.submit();
            });
        });
    }

    /* ------------------------------------------------------------------
       Public show page — media gallery
       ------------------------------------------------------------------ */
    function initShowPage() {
        var mediaItems = window.leadMediaItems || [];
        if (!mediaItems.length) return;

        var currentMediaIndex = 0;

        function renderMedia(index) {
            var main = document.getElementById('mainMedia');
            var item = mediaItems[index];

            if (!main || !item) return;

            if (item.type === 'video') {
                main.innerHTML = '<video src="' + item.url + '" controls playsinline></video>';
            } else {
                main.innerHTML = '<img src="' + item.url + '" alt="' + (item.title || 'Product Media') + '">';
            }

            if (mediaItems.length > 1) {
                main.insertAdjacentHTML('beforeend',
                    '<button type="button" class="gallery-arrow left" onclick="previousMedia()">‹</button>' +
                    '<button type="button" class="gallery-arrow right" onclick="nextMedia()">›</button>');
            }

            document.querySelectorAll('.thumb').forEach(function (thumb, thumbIndex) {
                thumb.classList.toggle('active', thumbIndex === index);
            });
        }

        function nextMedia() {
            if (!mediaItems.length) return;
            currentMediaIndex = (currentMediaIndex + 1) % mediaItems.length;
            renderMedia(currentMediaIndex);
        }

        function previousMedia() {
            if (!mediaItems.length) return;
            currentMediaIndex = (currentMediaIndex - 1 + mediaItems.length) % mediaItems.length;
            renderMedia(currentMediaIndex);
        }

        /* The arrows are injected with inline onclick handlers, so they
           need to resolve from the global scope. */
        window.nextMedia = nextMedia;
        window.previousMedia = previousMedia;

        renderMedia(0);

        var thumbs = document.getElementById('thumbs');
        if (thumbs) {
            mediaItems.forEach(function (item, index) {
                var thumb = document.createElement(item.type === 'image' ? 'img' : 'button');
                thumb.className = 'thumb' + (index === 0 ? ' active' : '');

                if (item.type === 'image') {
                    thumb.src = item.url;
                    thumb.alt = item.title || 'Image';
                } else {
                    thumb.type = 'button';
                    thumb.textContent = '▶';
                    thumb.title = item.title || 'Video';
                }

                thumb.addEventListener('click', function () {
                    currentMediaIndex = index;
                    renderMedia(index);
                });

                thumbs.appendChild(thumb);
            });
        }
    }

    onReady(function () {
        initCreatePage();
        initShowPage();
    });
})();
