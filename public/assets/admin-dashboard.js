function copyLink(btn) {
    var url = btn.getAttribute('data-url');
    if (!url) return;
    navigator.clipboard.writeText(url).then(function() {
        var orig = btn.textContent;
        btn.textContent = '\u2713 Kopiert';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = orig;
            btn.classList.remove('copied');
        }, 2000);
    }).catch(function() {
        btn.textContent = '\u2717 Fehler';
        setTimeout(function() { btn.textContent = '\uD83D\uDCCB Link'; }, 2000);
    });
}

function inlineConfirm(btn) {
    var form    = btn.closest('form');
    var actions = btn.closest('.td-actions');
    var msg     = btn.getAttribute('data-confirm') || 'Wirklich?';

    Array.from(actions.children).forEach(function(el) { el.style.display = 'none'; });

    var conf = document.createElement('span');
    conf.className = 'inline-confirm';
    conf.innerHTML = '<span class="inline-confirm-text">' + msg + '</span>';

    var yesBtn = document.createElement('button');
    yesBtn.type = 'button';
    yesBtn.className = 'btn btn-sm btn-danger';
    yesBtn.textContent = 'Ja';
    yesBtn.onclick = function() { form.submit(); };

    var noBtn = document.createElement('button');
    noBtn.type = 'button';
    noBtn.className = 'btn btn-sm btn-ghost';
    noBtn.textContent = 'Abbrechen';
    noBtn.onclick = function() {
        actions.removeChild(conf);
        Array.from(actions.children).forEach(function(el) { el.style.display = ''; });
    };

    conf.appendChild(yesBtn);
    conf.appendChild(noBtn);
    actions.appendChild(conf);
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.copy-btn');
        if (btn) { copyLink(btn); return; }

        var confirmBtn = e.target.closest('[data-confirm]');
        if (confirmBtn) { inlineConfirm(confirmBtn); }
    });
});
