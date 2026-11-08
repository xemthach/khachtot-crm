(function () {
    'use strict';
    function showStep(step) {
        var number = String(step);
        document.querySelectorAll('[data-misa-step-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-misa-step-panel') !== number;
        });
        document.querySelectorAll('[data-misa-step]').forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-misa-step') === number);
            button.setAttribute('aria-current', button.getAttribute('data-misa-step') === number ? 'step' : 'false');
        });
        var active = document.querySelector('[data-misa-step-panel="' + number + '"]');
        if (active) { active.setAttribute('tabindex', '-1'); active.focus({ preventScroll: true }); }
    }
    document.addEventListener('click', function (event) {
        var target = event.target.closest('[data-misa-step], [data-misa-next], [data-misa-prev], [data-misa-config-check]');
        if (!target) return;
        if (target.hasAttribute('data-misa-config-check')) {
            var form = target.closest('form');
            if (form && !form.checkValidity()) { form.reportValidity(); return; }
            target.classList.remove('btn-info'); target.classList.add('btn-success'); target.textContent = 'Cấu hình hợp lệ trong phạm vi mock';
            return;
        }
        var step = target.getAttribute('data-misa-step') || target.getAttribute('data-misa-next') || target.getAttribute('data-misa-prev');
        if (step) { event.preventDefault(); showStep(step); }
    });
    function initializeMisaPage() { if (document.querySelector('.kt-misa-admin')) { document.documentElement.style.overflowX = 'hidden'; if (document.body) document.body.style.overflowX = 'hidden'; } if (document.querySelector('[data-misa-wizard]')) showStep(1); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeMisaPage); else initializeMisaPage();
}());
