(function () {
    const eyeOpen = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.6-6.5 9.5-6.5S21.5 12 21.5 12 17.9 18.5 12 18.5 2.5 12 2.5 12Z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.7"/></svg>';
    const eyeOff = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5.5 19.5 22" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M9.2 9.4A3.2 3.2 0 0 0 12 15.2c.6 0 1.1-.1 1.6-.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M4 8.2C5.7 6.4 8.5 4.8 12 4.8c4.6 0 7.7 3.2 9.2 5.2-.6.8-1.5 1.9-2.7 2.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';

    function wrapInput(input) {
        if (!input || input.dataset.passwordToggle === '1' || input.closest('.kc-password-wrap')) {
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'kc-password-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.dataset.passwordToggle = '1';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'kc-password-toggle';
        btn.setAttribute('aria-label', 'Show password');
        btn.setAttribute('title', 'Show password');
        btn.innerHTML = eyeOpen;
        wrap.appendChild(btn);

        btn.addEventListener('click', function () {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.setAttribute('title', show ? 'Hide password' : 'Show password');
            btn.innerHTML = show ? eyeOff : eyeOpen;
        });
    }

    function init() {
        document.querySelectorAll('input[type="password"]:not([data-no-password-toggle])').forEach(wrapInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
