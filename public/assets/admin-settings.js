document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('logo-delete-modal');
    if (!modal) return;

    // Overlay-Klick schließt Modal
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.classList.remove('is-open');
    });

    // Escape schließt Modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') modal.classList.remove('is-open');
    });

    // "Logo löschen"-Button öffnet Modal
    document.addEventListener('click', function(e) {
        var openBtn = e.target.closest('[data-open-modal="logo-delete-modal"]');
        if (openBtn) { modal.classList.add('is-open'); return; }

        var closeBtn = e.target.closest('[data-close-modal="logo-delete-modal"]');
        if (closeBtn) { modal.classList.remove('is-open'); return; }

        var confirmBtn = e.target.closest('[data-submit-form="logo-delete-form"]');
        if (confirmBtn) { document.getElementById('logo-delete-form').submit(); }
    });
});
