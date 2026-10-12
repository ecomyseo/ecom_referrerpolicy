/**
 * Ecom Referrer Policy
 *
 * Pliega y despliega la ayuda de la pantalla de configuración.
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
(function () {
    'use strict';

    // Por delegación: funciona aunque el bloque se pinte después de cargar este fichero.
    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var toggle = target.closest('.erp-help-toggle');
        if (!toggle) {
            return;
        }

        event.preventDefault();

        var body = document.getElementById(toggle.getAttribute('data-erp-target'));
        if (!body) {
            return;
        }

        var open = body.classList.toggle('erp-help-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();
