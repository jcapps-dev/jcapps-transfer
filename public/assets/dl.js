document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = document.getElementById('dl-btn');
            if (btn) { btn.disabled = true; btn.textContent = 'Wird heruntergeladen\u2026'; }
        });
    }
});
