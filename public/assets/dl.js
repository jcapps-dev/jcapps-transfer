document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.dl-form');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = document.getElementById('dl-btn');
            if (!btn) return;
            var label = btn.querySelector('span') || btn;
            var orig  = label.textContent;
            btn.disabled = true;
            label.textContent = btn.getAttribute('data-loading') || orig;
            // Page stays open after the download — re-enable the button
            setTimeout(function() {
                btn.disabled = false;
                label.textContent = orig;
            }, 4000);
        });
    }
});
