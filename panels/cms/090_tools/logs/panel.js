// Register with the existing panel loader; behavior below uses shared DOM events.
if (window.sys?.lib?.add) {
    const setup = {route: 'panels.cms.tools.logs', extends: 'panels.cms'};
    sys.lib.add(setup, function () { sys.lib.applyParentConstructor(this, setup, []); });
}
(function () {
    if (window.cmsLogFiltersInstalled) return;
    window.cmsLogFiltersInstalled = true;
    function filter(card) {
        const controls = card.querySelector('.logFilters') || card.querySelector('.logFilterMenu')?.splitList;
        if (!controls) return;
        const query = (card.querySelector('.listSearch')?.value || '').toLowerCase().trim();
        const user = controls.querySelector('[data-log-user]');
        const userQuery = user.value.toLowerCase().trim();
        const from = controls.querySelector('[data-log-from]').value;
        const until = controls.querySelector('[data-log-until]').value;
        const result = controls.querySelector('[data-log-result]').value;
        const start = from ? new Date(from + 'T00:00:00').getTime()/1000 : -Infinity;
        const end = until ? new Date(until + 'T23:59:59').getTime()/1000 : Infinity;
        const rows = [...card.querySelectorAll('tbody tr[data-log-time]')];
        user.disabled = !rows.some(row => row.dataset.logUser);
        user.placeholder = user.disabled ? 'No user attribution in this log' : 'Name or email';
        for (const row of rows) {
            const time = row.dataset.logTime === '' ? NaN : Number(row.dataset.logTime);
            row.hidden = (query && !row.textContent.toLowerCase().includes(query)) || (userQuery && !user.disabled && !row.dataset.logUser.toLowerCase().includes(userQuery)) || (result && row.dataset.logResult !== result) || ((from || until) && (!Number.isFinite(time) || time < start || time > end));
        }
        controls.querySelector('[data-log-count]').textContent = rows.filter(row=>!row.hidden).length + ' / ' + rows.length + ' entries';
    }
    function update() {document.querySelectorAll('.logList').forEach(card=>{const menu=card.querySelector('.logFilterMenu');const header=card.querySelector(':scope > header');if(menu&&header&&menu.parentElement!==header)header.append(menu);const controls=menu&&(menu.querySelector('.logFilters')||menu.splitList);if(controls)controls.logOwner=card;filter(card);});}
    document.addEventListener('input',e=>{const card=e.target.closest('.logList') || e.target.closest('.logFilters')?.logOwner;if(card)filter(card);});
    document.addEventListener('change',e=>{const card=e.target.closest('.logList') || e.target.closest('.logFilters')?.logOwner;if(card)filter(card);});
    document.addEventListener('click',e=>{const reset=e.target.closest('[data-log-reset]');if(!reset)return;const controls=reset.closest('.logFilters');const card=controls?.logOwner||reset.closest('.logList');if(!card)return;controls.querySelectorAll('input,select').forEach(el=>el.value='');const search=card.querySelector('.listSearch');if(search)search.value='';filter(card);},true);
    document.addEventListener('cms:rendered',update);
    update();
})();
