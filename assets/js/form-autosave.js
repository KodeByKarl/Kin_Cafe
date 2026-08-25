(function () {
    function storageAvailable() {
        try {
            const key = '__kc_autosave_test__';
            window.localStorage.setItem(key, '1');
            window.localStorage.removeItem(key);
            return true;
        } catch (error) {
            return false;
        }
    }

    function shouldPersistField(field) {
        if (!field || !field.name || field.disabled) {
            return false;
        }

        if (field.closest('[data-no-autosave="true"]')) {
            return false;
        }

        const tagName = (field.tagName || '').toLowerCase();
        const type = String(field.type || '').toLowerCase();

        if (type === 'password' || type === 'hidden' || type === 'file' || type === 'submit' || type === 'button' || type === 'reset' || type === 'image') {
            return false;
        }

        if (type === 'search') {
            return false;
        }

        return tagName === 'input' || tagName === 'select' || tagName === 'textarea';
    }

    function getFormKey(form, index) {
        const pageKey = window.location.origin + window.location.pathname;
        const formKey = form.getAttribute('id') || form.getAttribute('name') || form.getAttribute('action') || ('form-' + index);
        return 'kc:autosave:' + pageKey + ':' + formKey;
    }

    function readFormState(form) {
        const state = {};
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (!shouldPersistField(field)) {
                return;
            }

            if (field.type === 'checkbox') {
                state[field.name] = Boolean(field.checked);
                return;
            }

            if (field.type === 'radio') {
                if (field.checked) {
                    state[field.name] = field.value;
                }
                return;
            }

            state[field.name] = field.value;
        });
        return state;
    }

    function writeFormState(form, state) {
        if (!state || typeof state !== 'object') {
            return;
        }

        form.querySelectorAll('input, select, textarea').forEach(function (field) {
            if (!shouldPersistField(field)) {
                return;
            }

            if (!(field.name in state)) {
                return;
            }

            if (field.type === 'checkbox') {
                field.checked = Boolean(state[field.name]);
                return;
            }

            if (field.type === 'radio') {
                field.checked = String(field.value) === String(state[field.name]);
                return;
            }

            field.value = state[field.name] == null ? '' : String(state[field.name]);
        });
    }

    function bindAutosave(form, index) {
        if (form.dataset.noAutosave === 'true') {
            return;
        }

        const storageKey = getFormKey(form, index);

        try {
            const saved = window.localStorage.getItem(storageKey);
            if (saved) {
                writeFormState(form, JSON.parse(saved));
            }
        } catch (error) {
            return;
        }

        const persist = function () {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(readFormState(form)));
            } catch (error) {
            }
        };

        form.addEventListener('input', function (event) {
            if (shouldPersistField(event.target)) {
                persist();
            }
        });

        form.addEventListener('change', function (event) {
            if (shouldPersistField(event.target)) {
                persist();
            }
        });

        form.addEventListener('submit', function () {
            if (form.dataset.keepAutosave === 'true') {
                persist();
                return;
            }
            window.localStorage.removeItem(storageKey);
        });

        form.addEventListener('reset', function () {
            window.localStorage.removeItem(storageKey);
        });
    }

    function initFormAutosave() {
        if (!window.localStorage || !storageAvailable()) {
            return;
        }

        document.querySelectorAll('form').forEach(function (form, index) {
            bindAutosave(form, index);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFormAutosave);
    } else {
        initFormAutosave();
    }
})();