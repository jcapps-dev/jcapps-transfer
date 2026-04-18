function copyLink(btn) {
    var url = btn.getAttribute('data-url');
    if (!url) return;
    navigator.clipboard.writeText(url).then(function() {
        var orig = btn.textContent;
        btn.textContent = '\u2713 Copied';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.textContent = orig;
            btn.classList.remove('copied');
        }, 2000);
    }).catch(function() {
        btn.textContent = '\u2717 Error';
        setTimeout(function() { btn.textContent = '\uD83D\uDCCB Link'; }, 2000);
    });
}

function inlineConfirm(btn) {
    var form    = btn.closest('form');
    var actions = btn.closest('.td-actions');
    var msg     = btn.getAttribute('data-confirm') || 'Are you sure?';

    Array.from(actions.children).forEach(function(el) { el.style.display = 'none'; });

    var conf = document.createElement('span');
    conf.className = 'inline-confirm';
    conf.innerHTML = '<span class="inline-confirm-text">' + msg + '</span>';

    var yesBtn = document.createElement('button');
    yesBtn.type = 'button';
    yesBtn.className = 'btn btn-sm btn-danger';
    yesBtn.textContent = 'Yes';
    yesBtn.onclick = function() { form.submit(); };

    var noBtn = document.createElement('button');
    noBtn.type = 'button';
    noBtn.className = 'btn btn-sm btn-ghost';
    noBtn.textContent = 'Cancel';
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

    var updateBtn = document.getElementById('do-update-btn');
    if (updateBtn) {
        updateBtn.addEventListener('click', function() {
            if (!confirm('Update the app now? The page will reload afterwards.')) return;

            var status = document.getElementById('update-status');
            updateBtn.disabled = true;
            updateBtn.textContent = 'Updating…';
            status.style.display = 'none';

            var csrf = document.querySelector('meta[name="csrf-token"]');

            fetch('do_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(csrf ? csrf.content : '')
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    status.style.display = 'inline';
                    status.textContent = '✓ Updated to v' + data.version + ' — reloading…';
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    updateBtn.disabled = false;
                    updateBtn.textContent = 'Update now';
                    status.style.display = 'inline';
                    status.style.color = '#991b1b';
                    status.textContent = '✗ ' + (data.error || 'Unknown error');
                }
            })
            .catch(function() {
                updateBtn.disabled = false;
                updateBtn.textContent = 'Update now';
                status.style.display = 'inline';
                status.style.color = '#991b1b';
                status.textContent = '✗ Network error — please try again';
            });
        });
    }
});
