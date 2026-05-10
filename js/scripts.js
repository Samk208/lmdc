/* LNMC homepage cleanup — content-level fixes that CSS can't reach.
   Runs once on DOMContentLoaded. */
(function () {
    'use strict';

    function init() {
        cleanMenuLabels();
        flagStaleEvents();
        warmHomepageMiddleImages();
        hideAddressPlaceholder();
    }

    /**
     * Convert WordPress slug-style menu labels into proper Title Case.
     * "Community-Partners" -> "Community Partners"
     * "collaboration-opportunities" -> "Collaboration Opportunities"
     */
    function cleanMenuLabels() {
        var links = document.querySelectorAll('#top-menu a, .et_mobile_menu a, #et-secondary-nav a');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            // Only touch the leaf text node, not nested icons / spans with classes
            var text = a.textContent ? a.textContent.trim() : '';
            if (!text) continue;
            // Heuristic: label looks like a slug if it contains a hyphen between word chars
            // and no spaces. Also catch all-lowercase hyphenated labels.
            var looksLikeSlug = /^[a-z0-9-]+$/i.test(text) && text.indexOf('-') !== -1;
            if (!looksLikeSlug) continue;
            var cleaned = text.replace(/-/g, ' ').replace(/\s+/g, ' ').trim();
            cleaned = cleaned.replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            // Replace only the visible text, preserve any child elements
            replaceLeafText(a, cleaned);
        }
    }

    function replaceLeafText(el, newText) {
        // Walk children and replace the first non-empty text node
        for (var i = 0; i < el.childNodes.length; i++) {
            var n = el.childNodes[i];
            if (n.nodeType === 3 && n.nodeValue.trim()) {
                n.nodeValue = newText;
                return;
            }
        }
        // Fallback: no text node found (icon-only label) — set textContent
        el.textContent = newText;
    }

    /**
     * Tag event cards as stale when their visible date is before the
     * current calendar year. Adds .lnmc-event-stale so CSS can dim them
     * and overlay a "Past Event" badge.
     */
    function flagStaleEvents() {
        var section = document.querySelector('body.home .et_pb_section_4');
        if (!section) return;
        var thisYear = new Date().getFullYear();
        var cards = section.querySelectorAll('.et_pb_column');
        var stale = 0, total = 0;
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            var text = (card.textContent || '').trim();
            // Find the latest 4-digit year in the card text
            var matches = text.match(/\b(20\d{2})\b/g);
            if (!matches) continue;
            var newest = 0;
            for (var j = 0; j < matches.length; j++) {
                var y = parseInt(matches[j], 10);
                if (y > newest) newest = y;
            }
            if (newest > 0 && newest < thisYear) {
                card.classList.add('lnmc-event-stale');
                stale++;
            }
            total++;
        }
        // If MOST events are past, make the section title honest instead of
        // appending the awkward "Upcoming ... (Past)" label.
        if (total > 0 && stale / total >= 0.5) {
            var title = section.querySelector('h2.et_pb_module_heading, h2');
            if (title) {
                title.classList.add('lnmc-events-stale-section');
                var current = (title.textContent || '').trim();
                if (/^upcoming\s+/i.test(current)) {
                    title.textContent = current.replace(/^Upcoming\s+/i, 'Recent ');
                }
            }
        }
    }

    /**
     * Hide the "[Address to be provided]" placeholder. ONLY tags the leaf
     * blurb/text module that contains the placeholder — never an entire
     * column (which would create the kind of dead-space bug we just had).
     * If the placeholder is in a Divi blurb, only the blurb description
     * gets hidden so the icon and column structure stay intact.
     */
    function hideAddressPlaceholder() {
        var modules = document.querySelectorAll(
            '.et_pb_blurb_description, .et_pb_blurb_content, .et_pb_text_inner'
        );
        var pattern = /(\[address[^\]]*\]|address[^.]{0,40}to be provided|to be provided)/i;
        for (var i = 0; i < modules.length; i++) {
            var el = modules[i];
            var text = (el.textContent || '').trim();
            if (text && pattern.test(text) && text.length < 200) {
                // Replace inner text with nothing so layout collapses naturally
                // rather than leaving a hidden ghost element with min-height.
                el.textContent = '';
                el.setAttribute('aria-hidden', 'true');
            }
        }
    }

    /**
     * Divi lazy images inside below-fold homepage cards can stay blank after
     * the theme reveal layer changes opacity/transform. The images are small
     * and local, so load these card thumbnails eagerly once the homepage JS
     * initializes.
     */
    function warmHomepageMiddleImages() {
        if (!document.body.classList.contains('home')) return;
        var imgs = document.querySelectorAll(
            '.et_pb_section_3 img[loading="lazy"], .et_pb_section_4 img[loading="lazy"]'
        );
        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];
            var src = img.getAttribute('src');
            if (!src) continue;
            img.removeAttribute('loading');
            img.setAttribute('decoding', 'async');
            img.setAttribute('src', src);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
