var selectedFiles = [];
var maxFiles = 10;
var maxBytes = 200 * 1048576;

function updateFileList() {
    var list = document.getElementById('file-list');
    if (!list) return;
    list.innerHTML = '';
    var total = 0;
    selectedFiles.forEach(function(f, i) {
        total += f.size;
        var item = document.createElement('div');
        item.className = 'file-item';

        var icon = document.createElement('span');
        icon.style.fontSize = '1rem';
        icon.textContent = '\uD83D\uDCC4';

        var name = document.createElement('span');
        name.className = 'file-item-name';
        name.textContent = f.name;

        var size = document.createElement('span');
        size.className = 'file-item-size';
        size.textContent = formatSize(f.size);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'file-item-remove';
        removeBtn.title = 'Entfernen';
        removeBtn.textContent = '\u2715';
        (function(idx) {
            removeBtn.addEventListener('click', function() { removeFile(idx); });
        })(i);

        item.appendChild(icon);
        item.appendChild(name);
        item.appendChild(size);
        item.appendChild(removeBtn);
        list.appendChild(item);
    });

    var sb = document.getElementById('upload-size');
    if (sb) {
        if (selectedFiles.length > 0) {
            sb.textContent = selectedFiles.length + ' Datei(en) \u2014 ' + formatSize(total);
            sb.style.color = total > maxBytes ? '#FCA5A5' : 'rgba(255,255,255,0.7)';
        } else {
            sb.textContent = '';
        }
    }
}

function removeFile(idx) {
    selectedFiles.splice(idx, 1);
    syncFileInput();
    updateFileList();
}

function syncFileInput() {
    var fileInput = document.getElementById('file-input');
    if (!fileInput) return;
    var dt = new DataTransfer();
    selectedFiles.forEach(function(f) { dt.items.add(f); });
    fileInput.files = dt.files;
}

function generatePassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    var pw = '';
    var arr = new Uint8Array(16);
    crypto.getRandomValues(arr);
    arr.forEach(function(b) { pw += chars[b % chars.length]; });
    var field = document.getElementById('pw-field');
    if (field) { field.value = pw; field.type = 'text'; }
}

function flashBtn(btn, msg) {
    var orig = btn.textContent;
    btn.textContent = msg;
    btn.classList.add('copied');
    setTimeout(function() { btn.textContent = orig; btn.classList.remove('copied'); }, 2000);
}

function copyUrl() {
    var btn = document.getElementById('copy-url-btn');
    var urlEl = document.getElementById('dl-url');
    if (!btn || !urlEl) return;
    navigator.clipboard.writeText(urlEl.textContent.trim())
        .then(function() { flashBtn(btn, '\u2713 Copied'); })
        .catch(function() { flashBtn(btn, '\u2717 Error'); });
}

function copyPw() {
    var btn = document.getElementById('copy-pw-btn');
    if (!btn) return;
    var resultEl = document.getElementById('upload-result');
    var pw = resultEl ? JSON.parse(resultEl.textContent).password : null;
    if (!pw) return;
    navigator.clipboard.writeText(pw)
        .then(function() { flashBtn(btn, '\u2713 Copied'); })
        .catch(function() { flashBtn(btn, '\u2717 Error'); });
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(2) + ' GB';
}

document.addEventListener('DOMContentLoaded', function() {
    // Konfiguration aus Meta-Tags lesen
    var metaFiles = document.querySelector('meta[name="max-files"]');
    var metaMb    = document.querySelector('meta[name="max-mb"]');
    if (metaFiles) maxFiles = parseInt(metaFiles.content, 10);
    if (metaMb)    maxBytes = parseInt(metaMb.content, 10) * 1048576;

    // Copy-Buttons (Ergebnis-Seite)
    var copyUrlBtn = document.getElementById('copy-url-btn');
    if (copyUrlBtn) copyUrlBtn.addEventListener('click', copyUrl);

    var copyPwBtn = document.getElementById('copy-pw-btn');
    if (copyPwBtn) copyPwBtn.addEventListener('click', copyPw);

    // Upload-Formular
    var fileInput = document.getElementById('file-input');
    if (!fileInput) return;

    fileInput.addEventListener('change', function(e) {
        var newFiles = Array.from(e.target.files);
        newFiles.forEach(function(f) {
            if (selectedFiles.length < maxFiles) selectedFiles.push(f);
        });
        syncFileInput();
        updateFileList();
    });

    var area = document.getElementById('upload-area');
    if (area) {
        area.addEventListener('click', function() { fileInput.click(); });
        area.addEventListener('dragover', function(e) { e.preventDefault(); area.classList.add('dragover'); });
        area.addEventListener('dragleave', function() { area.classList.remove('dragover'); });
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            area.classList.remove('dragover');
            var newFiles = Array.from(e.dataTransfer.files);
            newFiles.forEach(function(f) {
                if (selectedFiles.length < maxFiles) selectedFiles.push(f);
            });
            syncFileInput();
            updateFileList();
        });
    }

    var generateBtn = document.getElementById('generate-pw-btn');
    if (generateBtn) generateBtn.addEventListener('click', generatePassword);
});
