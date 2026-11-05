/** Keep ordinary lightboxes at their natural size and soften content changes.
 * Opening, dragging, expanded sheets and the mobile keyboard retain their own motion. */
(function () {
    if (typeof ResizeObserver === 'undefined') return;
    var sizes = new WeakMap();
    var reduced = matchMedia('(prefers-reduced-motion: reduce)');
    var observer = new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
            var section = entry.target;
            var rect = section.getBoundingClientRect();
            var prior = sizes.get(section);
            if (!prior) { sizes.set(section, {height: rect.height, width: rect.width}); return; }
            if (!rect.height || !rect.width) {
                if (prior.animation) prior.animation.cancel();
                prior.height = 0; prior.width = 0; prior.ready = false; return;
            }
            if (prior.animation) return;
            var from = prior.height;
            if (!from) {
                prior.ready = false;
                requestAnimationFrame(function () { requestAnimationFrame(function () {
                    if (section.isConnected && section.getClientRects().length) prior.ready = true;
                }); });
            }
            var widthChanged = Math.abs(prior.width - rect.width) > 1;
            prior.height = rect.height; prior.width = rect.width;
            // Opening has its own CSS/slide animation. Only resize an already settled box.
            var opening = section.getAnimations && section.getAnimations().some(function (motion) {
                return motion !== prior.animation && motion.playState === 'running';
            });
            if (!from || !prior.ready || opening || widthChanged || Math.abs(from - rect.height) < 1 || reduced.matches ||
                document.documentElement.classList.contains('cms-keyboard') ||
                document.body.classList.contains('dragging') || section.classList.contains('is-expanded') ||
                section.style.transform || !section.animate) return;
            var animation = section.animate([{height: from + 'px'}, {height: rect.height + 'px'}], {
                duration: 280, easing: 'cubic-bezier(.22,.8,.3,1)'
            });
            prior.animation = animation;
            animation.onfinish = function () {
                prior.animation = null;
                animation.cancel();
                prior.height = section.getBoundingClientRect().height;
            };
            animation.oncancel = function () { if (prior.animation === animation) prior.animation = null; };
        });
    });
    function sections(node, fn) {
        if (node.nodeType !== 1) return;
        if (node.matches('.cmslightbox > section')) fn(node);
        node.querySelectorAll('.cmslightbox > section').forEach(fn);
    }
    function observe(section) { if (!sizes.has(section)) { sizes.set(section, {height:0,width:0}); observer.observe(section); } }
    document.querySelectorAll('.cmslightbox > section').forEach(observe);
    new MutationObserver(function (changes) {
        changes.forEach(function (change) {
            change.removedNodes.forEach(function (node) { sections(node, function (section) { if (!section.isConnected) { observer.unobserve(section); sizes.delete(section); } }); });
            change.addedNodes.forEach(function (node) { sections(node, observe); });
        });
    }).observe(document.documentElement, {childList:true, subtree:true});
})();
