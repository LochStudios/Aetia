/* aetia-ui.js — Global UI helpers for the Aetia site.
   - SweetAlert2 wrappers (Aetia.alert, Aetia.confirm, Aetia.prompt, etc.)
   - Auto-dismiss for .notification flash messages
   - Mobile navbar burger toggle
*/
(function (window) {
    'use strict';

    var Swal = window.Swal;

    var brandPalette = {
        bg: '#11151f',
        text: '#e6ecf5',
        accent: '#19d3ff',
        accentText: '#001218',
        danger: '#ff5d6e',
        dangerText: '#ffffff',
        success: '#2dd4a4',
        warning: '#ffc857'
    };

    var commonOpts = {
        background: brandPalette.bg,
        color: brandPalette.text,
        confirmButtonColor: brandPalette.accent,
        cancelButtonColor: '#16202b',
        denyButtonColor: brandPalette.danger,
        buttonsStyling: true,
        focusConfirm: true
    };

    function alertBox(opts) {
        if (!Swal) { window.alert((opts && (opts.title || opts.text)) || ''); return Promise.resolve({isConfirmed:true}); }
        if (typeof opts === 'string') opts = { title: opts };
        return Swal.fire(Object.assign({}, commonOpts, opts || {}));
    }

    function success(title, text) {
        return alertBox({ icon: 'success', title: title || 'Success', text: text || '', confirmButtonText: 'OK' });
    }
    function error(title, text) {
        return alertBox({ icon: 'error', title: title || 'Error', text: text || '', confirmButtonText: 'OK' });
    }
    function info(title, text) {
        return alertBox({ icon: 'info', title: title || '', text: text || '', confirmButtonText: 'OK' });
    }
    function warning(title, text) {
        return alertBox({ icon: 'warning', title: title || '', text: text || '', confirmButtonText: 'OK' });
    }

    function confirm(title, text, opts) {
        opts = opts || {};
        return alertBox(Object.assign({
            icon: opts.icon || 'question',
            title: title || 'Are you sure?',
            text: text || '',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Confirm',
            cancelButtonText: opts.cancelText || 'Cancel',
            reverseButtons: true,
            focusCancel: !!opts.dangerous
        }, opts.swal || {}));
    }

    function dangerConfirm(title, text, opts) {
        opts = opts || {};
        opts.dangerous = true;
        return confirm(title, text, Object.assign({
            confirmText: opts.confirmText || 'Yes, delete'
        }, opts, {
            swal: {
                icon: 'warning',
                confirmButtonColor: brandPalette.danger
            }
        }));
    }

    function prompt(title, opts) {
        if (!Swal) { var v = window.prompt(title || ''); return Promise.resolve({isConfirmed: v !== null, value: v}); }
        opts = opts || {};
        return Swal.fire(Object.assign({}, commonOpts, {
            title: title || '',
            input: opts.input || 'text',
            inputLabel: opts.label || '',
            inputPlaceholder: opts.placeholder || '',
            inputValue: opts.value || '',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'OK',
            cancelButtonText: opts.cancelText || 'Cancel',
            inputValidator: opts.validator,
            inputAttributes: opts.inputAttributes || {}
        }, opts.swal || {}));
    }

    function toast(message, icon) {
        if (!Swal) { console.log('[Aetia]', message); return Promise.resolve(); }
        return Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: brandPalette.bg,
            color: brandPalette.text
        }).fire({
            icon: icon || 'success',
            title: message || ''
        });
    }

    /* Confirm a form before it submits.
       Usage: <form data-confirm="Are you sure?" data-confirm-text="Yes" ...>
              or <button data-confirm="Are you sure?" type="submit">
    */
    function bindConfirmForms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            if (form.dataset.aetiaConfirmBound) return;
            form.dataset.aetiaConfirmBound = '1';
            form.addEventListener('submit', function (e) {
                if (form.dataset.aetiaConfirmed === '1') return;
                e.preventDefault();
                confirm(form.dataset.confirm || 'Are you sure?', form.dataset.confirmDetail || '', {
                    icon: form.dataset.confirmIcon || 'question',
                    confirmText: form.dataset.confirmText || 'Confirm',
                    cancelText: form.dataset.cancelText || 'Cancel',
                    dangerous: form.dataset.confirmDanger === '1'
                }).then(function (res) {
                    if (res.isConfirmed) {
                        form.dataset.aetiaConfirmed = '1';
                        if (typeof form.requestSubmit === 'function') form.requestSubmit();
                        else form.submit();
                    }
                });
            }, true);
        });

        document.querySelectorAll('button[data-confirm]').forEach(function (btn) {
            if (btn.dataset.aetiaConfirmBound) return;
            btn.dataset.aetiaConfirmBound = '1';
            btn.addEventListener('click', function (e) {
                if (btn.dataset.aetiaConfirmed === '1') return;
                e.preventDefault();
                e.stopPropagation();
                confirm(btn.dataset.confirm || 'Are you sure?', btn.dataset.confirmDetail || '', {
                    icon: btn.dataset.confirmIcon || 'question',
                    confirmText: btn.dataset.confirmText || 'Confirm',
                    cancelText: btn.dataset.cancelText || 'Cancel',
                    dangerous: btn.dataset.confirmDanger === '1'
                }).then(function (res) {
                    if (res.isConfirmed) {
                        btn.dataset.aetiaConfirmed = '1';
                        // Re-dispatch the click to actually submit
                        var form = btn.form;
                        if (form) {
                            if (typeof form.requestSubmit === 'function') form.requestSubmit(btn);
                            else form.submit();
                        } else {
                            btn.click();
                        }
                    }
                });
            }, true);
        });
    }

    /* Notification close button */
    function bindNotificationDismiss() {
        document.querySelectorAll('.notification .delete').forEach(function (btn) {
            if (btn.dataset.aetiaDismissBound) return;
            btn.dataset.aetiaDismissBound = '1';
            btn.addEventListener('click', function () {
                var n = btn.closest('.notification');
                if (n) {
                    n.style.transition = 'opacity 0.2s ease';
                    n.style.opacity = '0';
                    setTimeout(function () { n.remove(); }, 200);
                }
            });
        });
    }

    /* Navbar burger menu toggle */
    function bindNavbarBurger() {
        document.querySelectorAll('.navbar-burger').forEach(function (burger) {
            if (burger.dataset.aetiaBurgerBound) return;
            burger.dataset.aetiaBurgerBound = '1';
            burger.addEventListener('click', function () {
                var target = burger.getAttribute('data-target');
                var menu = target ? document.getElementById(target) : burger.closest('.navbar').querySelector('.navbar-menu');
                if (!menu) return;
                burger.classList.toggle('is-active');
                menu.classList.toggle('is-active');
                burger.setAttribute('aria-expanded', burger.classList.contains('is-active') ? 'true' : 'false');
            });
        });
    }

    /* Dropdown click toggle (for tap-to-open on mobile) */
    function bindDropdownClicks() {
        document.querySelectorAll('.dropdown:not(.is-hoverable) .dropdown-trigger').forEach(function (trig) {
            if (trig.dataset.aetiaDropdownBound) return;
            trig.dataset.aetiaDropdownBound = '1';
            trig.addEventListener('click', function (e) {
                e.stopPropagation();
                var dropdown = trig.closest('.dropdown');
                if (dropdown) dropdown.classList.toggle('is-active');
            });
        });
        document.addEventListener('click', function () {
            document.querySelectorAll('.dropdown.is-active').forEach(function (d) { d.classList.remove('is-active'); });
        });
    }

    /* Auto-dismiss flash notifications after N seconds (data-auto-dismiss="5000") */
    function bindAutoDismiss() {
        document.querySelectorAll('.notification[data-auto-dismiss]').forEach(function (n) {
            if (n.dataset.aetiaAutoDismissBound) return;
            n.dataset.aetiaAutoDismissBound = '1';
            var ms = parseInt(n.dataset.autoDismiss, 10) || 5000;
            setTimeout(function () {
                n.style.transition = 'opacity 0.4s ease';
                n.style.opacity = '0';
                setTimeout(function () { n.remove(); }, 400);
            }, ms);
        });
    }

    function init() {
        bindNotificationDismiss();
        bindNavbarBurger();
        bindDropdownClicks();
        bindAutoDismiss();
        bindConfirmForms();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // MutationObserver to bind newly inserted elements
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(function () { init(); })
            .observe(document.documentElement, { childList: true, subtree: true });
    }

    window.Aetia = {
        alert: alertBox,
        success: success,
        error: error,
        info: info,
        warning: warning,
        confirm: confirm,
        dangerConfirm: dangerConfirm,
        prompt: prompt,
        toast: toast,
        rebind: init
    };
})(window);
