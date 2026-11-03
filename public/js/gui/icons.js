/* Generated icon registry; update keys with tools/build-icons.py. No remote icon service. */
(function () {
    'use strict';
    const keys = new Set(["arrow-down-to-line", "arrow-up-from-bracket", "arrow-up-right-from-square", "up-right-from-square", "arrow-rotate-left", "arrow-rotate-right", "arrow-turn-down-right", "arrows-left-right-to-line", "arrows-rotate", "arrows-repeat", "bars", "box-archive", "broom", "calendar", "chart-simple", "check-circle", "circle-exclamation", "circle-half-stroke", "circle-info", "circle-question", "circle-xmark", "clock-rotate-left", "cloud-arrow-up", "code-merge", "crop-simple", "crosshairs", "diamond-turn-right", "display-slash", "edit", "ellipsis", "ellipsis-v", "envelope", "expand", "file-arrow-down", "file-contract", "file-import", "file-lines", "files", "filter-circle-xmark", "flask", "folder-minus", "folder-plus", "frame", "gear", "grid-2", "grip-dots-vertical", "grip-lines", "id-card", "input-numeric", "input-password", "key-skeleton", "laptop-mobile", "laptop-slash", "layer-group", "layer-plus", "link-slash", "list-check", "list-ul", "lock", "magnifying-glass", "minus-circle", "mobile", "mobile-screen", "mobile-screen-button", "mobile-slash", "paper-plane", "pen", "pen-to-square", "phone-laptop", "photo-film", "plus-circle", "qrcode", "question-circle", "reflect-horizontal", "right-from-bracket", "right-from-line", "right-to-bracket", "rotate", "rotate-left", "rotate-right", "shield-check", "shield-halved", "sitemap", "sliders", "spinner", "square", "stop", "swatchbook", "table-columns", "table-rows", "tablet-screen", "tablet-screen-button", "times", "times-circle", "trash-slash", "trash-xmark", "triangle-exclamation", "tv", "user-clock", "user-hat-tie-magnifying-glass", "user-key", "user-lock", "user-shield", "user-tag", "user-viewfinder", "xmark", "xmark-large", "forward", "play", "circle-play", "desktop", "laptop", "images", "image", "volume-slash", "brackets-curly", "text-slash", "up-down-left-right", "paragraph", "table-layout", "fingerprint", "scan-face", "success-unlock", "arrow-left", "arrow-right", "bolt", "bug", "building", "camera", "check", "chevron-down", "chevron-left", "chevron-right", "chevron-up", "circle-check", "compass", "copy", "ghost-3", "cube", "database", "eraser", "eye", "eye-slash", "file", "file-pdf", "file-text", "film", "file-lock", "folder", "folder-open", "globe", "hammer", "hashtag", "heading", "key", "keyboard", "link", "list", "minus", "moon", "music", "plus", "repeat", "route", "server", "sun", "tag", "tags", "trash", "user", "user-plus", "users", "wrench", "file-image", "vector-polygon", "file-zipper", "file-word", "file-excel", "file-powerpoint", "file-csv", "address-card", "bold", "italic", "underline", "strikethrough", "table", "arrow-right-from-bracket", "image-slash", "video-slash", "file-slash", "video", "gif", "clock", "calendar-days", "calendar-arrow-down", "calendar-check", "calendar-day", "calendar-xmark", "address-book", "rectangle-history", "rectangle-history-circle-plus", "file-circle-plus", "location-dot", "map-location-dot", "briefcase-medical", "island-tropical", "buildings", "comment-sms", "download", "phone", "cpu", "calendar-circle-plus", "palette", "droplet-half", "contrast", "droplet-half-2", "text-select", "editor-undo", "aspect-ratio", "lock-open"]), duotoneKeys = new Set(["lock", "gear", "users", "sitemap", "box-archive", "magnifying-glass", "circle-question", "files", "wrench", "calendar-days", "address-book", "user", "display"]);
    const warned = new Set();
    function classes(value, duotone) {
        let key = '';
        String(value || '').split(/\s+/).forEach(token => {
            if (token === 'cmsIcon-duotone') duotone = true;
            const name = token.replace(/^cmsIcon-/, '');
            if (keys.has(name)) key = name;
        });
        if (!key) {
            if (!warned.has(value)) { warned.add(value); console.warn('Fluxo: unknown icon key; use a registered key from Ui/icon-map.json'); }
            key = 'circle-question';
        }
        return 'cmsIcon cmsIcon-' + key + (duotone && duotoneKeys.has(key) ? ' cmsIcon-duotone' : '');
    }
    window.cmsIcons = Object.freeze({classes, html: (key, duotone) => '<i class="' + classes(key, duotone) + '" aria-hidden="true"></i>'});
    // Accessibility and registration checks for native cmsIcon markup.
    // Use the action's own wording for tooltips and accessible names; never guess from its glyph.
    function nameControls(node) {
        const selector='a,button,label:has(input[type="file"]),input[type="file"]';
        const controls=[...(node.matches(selector)?[node]:[]),...node.querySelectorAll(selector)];
        controls.forEach(control=>{
            if(control.textContent.trim() || control.hasAttribute('aria-labelledby')) return;
            if(!control.querySelector('.cmsIcon') && !control.matches('input[type="file"]')) return;
            const owner=control.closest('li[title],label[title]');
            const name=control.getAttribute('aria-label') || control.getAttribute('title') || owner?.getAttribute('title');
            if(!name) return;
            if(!control.hasAttribute('aria-label')) control.setAttribute('aria-label',name);
            if(!control.hasAttribute('title')) control.setAttribute('title',name);
        });
    }
    function upgrade(node) {
        if (!(node instanceof Element)) return;
        nameControls(node);
        const nodes = [node, ...node.querySelectorAll('i[class]')];
        nodes.forEach(el => {
            if (el.tagName !== 'I') return;
            if (!el.classList.contains('cmsIcon')) return;
            if (!el.hasAttribute('aria-label') && !el.hasAttribute('role') && !el.matches('[data-on_click],[tabindex]')) el.setAttribute('aria-hidden', 'true');
            const key = [...el.classList].find(t => t.startsWith('cmsIcon-') && keys.has(t.slice(8)));
            if (!key && !warned.has(el.className)) { warned.add(el.className); console.warn('Fluxo: unregistered dynamic icon key'); }
        });
    }
    function start() {
        upgrade(document.documentElement);
        new MutationObserver(records => records.forEach(record => {
            if (record.type === 'attributes') upgrade(record.target);
            else record.addedNodes.forEach(upgrade);
        })).observe(document.documentElement, {subtree:true, childList:true, attributes:true, attributeFilter:['class']});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once:true}); else start();
})();
