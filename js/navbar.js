// navbar.js — Legacy compatibility shim.
// All navbar burger / dropdown behaviour now lives in /js/aetia-ui.js.
// This file is kept as a no-op so any straggler <script src="..."> references
// don't 404. Aetia.rebind() may be called if the DOM is mutated dynamically.
(function () {
    if (window.Aetia && typeof window.Aetia.rebind === 'function') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', window.Aetia.rebind);
        } else {
            window.Aetia.rebind();
        }
    }
})();
