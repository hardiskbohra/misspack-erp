/* Photo first, initials while loading/when missing or unreadable. No inline handlers. */
(function () {
    'use strict';
    function initials(name) {
        return String(name || 'User').trim().split(/\s+/).slice(0, 2)
            .map(function (word) { return Array.from(word)[0] || ''; }).join('').toUpperCase() || 'U';
    }
    function loaded(image) {
        var avatar = image.parentElement;
        if (!avatar || !avatar.hasAttribute('data-user-avatar')) return;
        var good = image.naturalWidth > 0;
        image.hidden = !good;
        avatar.classList.toggle('has-photo', good);
    }
    // Capture events also covers photos created inside an edit dialog later.
    document.addEventListener('load', function (e) {
        if (e.target.matches && e.target.matches('[data-user-avatar-image]')) loaded(e.target);
    }, true);
    document.addEventListener('error', function (e) {
        if (e.target.matches && e.target.matches('[data-user-avatar-image]')) loaded(e.target);
    }, true);
    function sync() {
        document.querySelectorAll('[data-user-avatar-image]').forEach(function (image) {
            if (image.complete) loaded(image);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync);
    else sync();

    function safeUrl(url) {
        if (!url) return '';
        if (/^data:image\/(png|jpeg|gif|webp);base64,[a-z0-9+/=]+$/i.test(url)) return url;
        try {
            var parsed = new URL(url, location.href);
            return ['http:', 'https:'].indexOf(parsed.protocol) !== -1 ? parsed.href : '';
        } catch (_) { return ''; }
    }
    window.UserAvatars = {
        render: function (avatar, url, name) {
            avatar.classList.add('user-avatar');
            avatar.classList.remove('has-photo');
            avatar.setAttribute('data-user-avatar', '');
            avatar.dataset.avatarName = name || 'User';
            var fallback = document.createElement('span');
            fallback.setAttribute('data-avatar-initials', '');
            fallback.textContent = initials(name);
            avatar.replaceChildren(fallback);
            url = safeUrl(url);
            if (!url) return;
            var image = document.createElement('img');
            image.setAttribute('data-user-avatar-image', '');
            image.alt = '';
            image.decoding = 'async';
            avatar.appendChild(image);
            image.src = url;
            if (image.complete) loaded(image);
        }
    };
})();
