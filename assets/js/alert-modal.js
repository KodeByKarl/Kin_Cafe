(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.KinAlertModal = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    let active = null;

    function getFocusable(rootEl) {
        if (!rootEl) return [];
        const selectors = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(',');
        return Array.from(rootEl.querySelectorAll(selectors))
            .filter(el => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function close(result) {
        if (!active) return;
        const { overlay, resolve, lastFocus, keyHandler } = active;
        document.removeEventListener('keydown', keyHandler, true);
        overlay.classList.remove('kc-open');
        setTimeout(() => {
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
            active = null;
            resolve(result);
        }, 200);
    }

    function show(opts) {
        if (active) {
            close(false);
        }

        const title = String(opts && opts.title ? opts.title : 'Notice');
        const message = String(opts && opts.message ? opts.message : '');
        const confirmText = String(opts && opts.confirmText ? opts.confirmText : 'OK');
        const cancelText = String(opts && opts.cancelText ? opts.cancelText : 'Cancel');
        const mode = (opts && opts.mode) === 'confirm' ? 'confirm' : 'alert';

        const overlay = document.createElement('div');
        overlay.className = 'kc-alert-overlay';
        overlay.setAttribute('data-kc-alert', '1');

        const dialog = document.createElement('div');
        dialog.className = 'kc-alert-dialog';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('tabindex', '-1');

        const titleId = 'kc_alert_title_' + Math.random().toString(16).slice(2);
        const bodyId = 'kc_alert_body_' + Math.random().toString(16).slice(2);
        dialog.setAttribute('aria-labelledby', titleId);
        dialog.setAttribute('aria-describedby', bodyId);

        const header = document.createElement('div');
        header.className = 'kc-alert-header d-flex justify-content-between align-items-center';
        const h = document.createElement('div');
        h.id = titleId;
        h.textContent = title;
        const x = document.createElement('button');
        x.type = 'button';
        x.className = 'kc-alert-close';
        x.setAttribute('aria-label', 'Close dialog');
        x.textContent = '×';
        header.appendChild(h);
        header.appendChild(x);

        const body = document.createElement('div');
        body.className = 'kc-alert-body';
        body.id = bodyId;
        body.textContent = message;

        const footer = document.createElement('div');
        footer.className = 'kc-alert-footer';
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-outline-secondary kc-alert-btn';
        cancelBtn.textContent = cancelText;
        const okBtn = document.createElement('button');
        okBtn.type = 'button';
        okBtn.className = 'btn btn-primary kc-alert-btn';
        okBtn.textContent = confirmText;

        if (mode === 'confirm') {
            footer.appendChild(cancelBtn);
        }
        footer.appendChild(okBtn);

        dialog.appendChild(header);
        dialog.appendChild(body);
        dialog.appendChild(footer);
        overlay.appendChild(dialog);

        const lastFocus = document.activeElement;
        const keyHandler = (e) => {
            if (!active) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close(false);
                return;
            }
            if (e.key === 'Tab') {
                const focusables = getFocusable(dialog);
                if (!focusables.length) {
                    e.preventDefault();
                    dialog.focus();
                    return;
                }
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first || document.activeElement === dialog) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }
            if (e.key === 'Enter' && document.activeElement === okBtn) {
                e.preventDefault();
                close(true);
            }
        };

        return new Promise((resolve) => {
            active = { overlay, resolve, lastFocus, keyHandler };
            document.body.appendChild(overlay);
            document.addEventListener('keydown', keyHandler, true);
            x.addEventListener('click', () => close(false));
            overlay.addEventListener('click', (ev) => {
                if (ev.target === overlay) {
                    close(false);
                }
            });
            cancelBtn.addEventListener('click', () => close(false));
            okBtn.addEventListener('click', () => close(true));

            requestAnimationFrame(() => {
                overlay.classList.add('kc-open');
                okBtn.focus();
            });
        });
    }

    function alert(message, title = 'Notice', confirmText = 'OK') {
        return show({ mode: 'alert', title, message, confirmText });
    }

    function confirm(message, title = 'Confirm', confirmText = 'OK', cancelText = 'Cancel') {
        return show({ mode: 'confirm', title, message, confirmText, cancelText });
    }

    return { show, alert, confirm };
}));

