/* ==========================================================================
   SETTINGS — the module's own behaviour, and only that.

   One thing, honestly: on a narrow screen the rail is a horizontal strip, and
   the strip starts at its first item. A reader who opens Briefings from the
   search box, or reloads a page three areas along, lands on a menu that looks
   like it begins at Organisation — the item that says where they are is off the
   right edge.

   So the strip is scrolled to its current item on load. It uses the strip's own
   `scrollLeft` rather than `scrollIntoView`, because `scrollIntoView` walks up
   every scrollable ancestor: on this page that moves the *window* as well, and a
   page that jumps down to its menu on load reads as broken.

   Nothing else lives here — no dialog, no row navigation, no table. The settings
   screens are forms on their own pages, and the shell's scripts already own
   everything they do.
   ========================================================================== */
(function () {
    'use strict';

    function centreTheCurrentItem() {
        var strip = document.querySelector('.set-nav-list');
        var current = strip ? strip.querySelector('.set-nav-link[aria-current="page"]') : null;

        if (!strip || !current) return;

        /* Only the strip scrolls; if it does not (a desktop column, or a strip
           that happens to fit), there is nothing to do. */
        if (strip.scrollWidth <= strip.clientWidth + 1) return;

        strip.scrollLeft = current.offsetLeft - (strip.clientWidth / 2) + (current.offsetWidth / 2);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', centreTheCurrentItem);
    } else {
        centreTheCurrentItem();
    }
})();
