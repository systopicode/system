// Register with the existing panel loader; behavior below uses shared DOM events.
if (window.sys?.lib?.add) {
    const setup = {route: 'panels.cms.settings.theme', extends: 'panels.cms'};
    sys.lib.add(setup, function () { sys.lib.applyParentConstructor(this, setup, []); });
}
(function () {
    function contrast() {
        const target = document.querySelector('[data-contrast-result]');
        if (!target) return;
        const probe = document.createElement('span');
        probe.style.cssText='position:absolute;visibility:hidden;color:var(--cmsTextColor);background-color:var(--cmsCanvas)';
        document.body.append(probe);
        const style=getComputedStyle(probe);
        const luminance = color => {
            const canvas=document.createElement('canvas');canvas.width=canvas.height=1;
            const context=canvas.getContext('2d');context.fillStyle=color;context.fillRect(0,0,1,1);
            const parts=[...context.getImageData(0,0,1,1).data].slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;});
            return .2126*parts[0]+.7152*parts[1]+.0722*parts[2];
        };
        const a=luminance(style.color),b=luminance(style.backgroundColor);probe.remove();
        const ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);
        const icon=document.createElement('i');icon.className='cmsIcon '+(ratio>=4.5?'is-success cmsIcon-circle-check':'is-warning cmsIcon-triangle-exclamation');
        target.replaceChildren(icon,document.createTextNode(' '));
        target.append(Number.isFinite(ratio)?ratio.toFixed(2)+':1 — '+(ratio>=4.5?'Sufficient for normal text':'Low contrast in the current appearance'):'Check the rendered colours manually.');
    }
    if (!window.cmsThemeContrastInstalled) {
        window.cmsThemeContrastInstalled=true;
        document.addEventListener('cms:rendered',contrast);
        new MutationObserver(contrast).observe(document.documentElement,{attributes:true,attributeFilter:['data-mode','style']});
    }
    contrast();
})();
