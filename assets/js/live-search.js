(function () {
    function normalize(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function ensureResults(input) {
        const host = input.closest('.kc-live-search, .pos-modern-search, .inventory-search-wrap, .menu-admin-search-wrap, .orders-search-field') || input.parentElement;
        if (!host) {
            return null;
        }
        host.classList.add('kc-live-search');
        let results = host.querySelector('.kc-live-search-results');
        if (!results) {
            results = document.createElement('div');
            results.className = 'list-group kc-live-search-results';
            results.hidden = true;
            host.appendChild(results);
        }
        return results;
    }

    function rowLabel(row) {
        const named = row.getAttribute('data-name') || row.getAttribute('data-live-search-text');
        if (named) {
            return named;
        }
        const strong = row.querySelector('strong, .inventory-name-cell strong, h6, td');
        return strong ? strong.textContent : row.textContent;
    }

    function bindInput(input) {
        if (!input || input.dataset.liveSearchBound === '1') {
            return;
        }
        input.dataset.liveSearchBound = '1';
        input.setAttribute('autocomplete', 'off');
        const results = ensureResults(input);
        const targetSel = input.getAttribute('data-live-search-target');
        const minChars = Math.max(1, Number(input.getAttribute('data-live-search-min') || 1));

        function items() {
            if (targetSel) {
                return Array.from(document.querySelectorAll(targetSel));
            }
            return [];
        }

        function hideResults() {
            if (results) {
                results.hidden = true;
                results.innerHTML = '';
            }
        }

        function apply() {
            const query = normalize(input.value);
            const rows = items();
            const matches = [];
            rows.forEach((row) => {
                const hay = normalize(rowLabel(row) + ' ' + (row.textContent || ''));
                const show = !query || hay.indexOf(query) !== -1;
                const filterRows = input.getAttribute('data-live-search-filter') !== 'off';
                if (filterRows && row.matches('tr, .menu-item-card, .menu-admin-section, [data-live-search-item]')) {
                    if (!row.hasAttribute('data-live-search-keep-display')) {
                        row.style.display = show ? '' : 'none';
                    }
                }
                if (query.length >= minChars && show) {
                    matches.push(row);
                }
            });

            if (!results) {
                return;
            }
            if (query.length < minChars || !matches.length) {
                hideResults();
                return;
            }

            results.innerHTML = matches.slice(0, 8).map((row, index) => {
                const label = String(rowLabel(row) || 'Result').replace(/\s+/g, ' ').trim();
                return '<button type="button" class="list-group-item list-group-item-action kc-live-search-hit" data-index="' + index + '">' + label.replace(/</g, '&lt;') + '</button>';
            }).join('');
            results.hidden = false;
            results.querySelectorAll('.kc-live-search-hit').forEach((btn) => {
                btn.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    const row = matches[Number(btn.getAttribute('data-index'))];
                    if (!row) {
                        return;
                    }
                    input.value = String(rowLabel(row) || '').replace(/\s+/g, ' ').trim();
                    apply();
                    if (typeof row.scrollIntoView === 'function') {
                        row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                    row.classList.add('kc-live-search-flash');
                    setTimeout(() => row.classList.remove('kc-live-search-flash'), 1200);
                    hideResults();
                });
            });
        }

        input.addEventListener('input', apply);
        input.addEventListener('focus', apply);
        input.addEventListener('blur', function () {
            setTimeout(hideResults, 180);
        });
    }

    function init() {
        document.querySelectorAll('[data-live-search-target]').forEach(bindInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.KinLiveSearch = { init: init };
})();
