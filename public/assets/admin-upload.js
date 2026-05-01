var selectedFiles = [];
var maxFiles = 10;
var maxBytes = 200 * 1048576;
var CHUNK_SIZE = 4 * 1024 * 1024; // 4 MB per chunk

// ── File list rendering ───────────────────────────────────────────────────────

function updateFileList() {
    var list = document.getElementById('file-list');
    if (!list) return;
    list.innerHTML = '';
    var total = 0;

    selectedFiles.forEach(function(f, i) {
        total += f.size;

        var item = document.createElement('div');
        item.className = 'file-item';
        item.id = 'file-item-' + i;

        var row = document.createElement('div');
        row.className = 'file-item-row';

        var icon = document.createElement('span');
        icon.style.cssText = 'flex-shrink:0;color:var(--color-gray-400);line-height:0';
        icon.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';

        var name = document.createElement('span');
        name.className = 'file-item-name';
        name.textContent = f.name;

        var size = document.createElement('span');
        size.className = 'file-item-size';
        size.textContent = formatSize(f.size);

        var status = document.createElement('span');
        status.className = 'file-item-status';
        status.id = 'file-status-' + i;

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'file-item-remove';
        removeBtn.title = 'Remove';
        removeBtn.innerHTML = '×';
        (function(idx) {
            removeBtn.addEventListener('click', function() { removeFile(idx); });
        })(i);

        row.appendChild(icon);
        row.appendChild(name);
        row.appendChild(size);
        row.appendChild(status);
        row.appendChild(removeBtn);

        var progressWrap = document.createElement('div');
        progressWrap.className = 'file-item-progress';
        progressWrap.id = 'file-progress-' + i;
        progressWrap.style.display = 'none';

        var fill = document.createElement('div');
        fill.className = 'file-item-progress-fill';
        fill.id = 'file-progress-fill-' + i;
        progressWrap.appendChild(fill);

        item.appendChild(row);
        item.appendChild(progressWrap);
        list.appendChild(item);
    });

    var sb = document.getElementById('upload-size');
    if (sb) {
        if (selectedFiles.length > 0) {
            sb.textContent = selectedFiles.length + ' file(s) — ' + formatSize(total);
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

// ── Helper functions ──────────────────────────────────────────────────────────

function generatePassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    var pw = '';
    var arr = new Uint8Array(16);
    crypto.getRandomValues(arr);
    arr.forEach(function(b) { pw += chars[b % chars.length]; });
    var field = document.getElementById('pw-field');
    if (field) { field.value = pw; field.type = 'text'; }
}

function generateHex(bytes) {
    var arr = new Uint8Array(bytes);
    crypto.getRandomValues(arr);
    return Array.from(arr, function(b) { return b.toString(16).padStart(2, '0'); }).join('');
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(2) + ' GB';
}

function flashBtn(btn, msg) {
    var orig = btn.innerHTML;
    btn.textContent = msg;
    btn.classList.add('copied');
    setTimeout(function() { btn.innerHTML = orig; btn.classList.remove('copied'); }, 2000);
}

function copyUrl() {
    var btn = document.getElementById('copy-url-btn');
    var urlEl = document.getElementById('dl-url');
    if (!btn || !urlEl) return;
    navigator.clipboard.writeText(urlEl.textContent.trim())
        .then(function() { flashBtn(btn, '✓ Copied'); })
        .catch(function() { flashBtn(btn, '✗ Error'); });
}

function copyPw() {
    var btn = document.getElementById('copy-pw-btn');
    if (!btn) return;
    var resultEl = document.getElementById('upload-result');
    var pw = resultEl ? JSON.parse(resultEl.textContent).password : null;
    if (!pw) return;
    navigator.clipboard.writeText(pw)
        .then(function() { flashBtn(btn, '✓ Copied'); })
        .catch(function() { flashBtn(btn, '✗ Error'); });
}

function showError(msg) {
    var el = document.getElementById('error-msg');
    if (!el) return;
    el.textContent = msg;
    el.style.display = '';
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showResult(result) {
    var dlUrl = document.getElementById('dl-url');
    if (dlUrl) dlUrl.textContent = result.url;

    if (result.password) {
        var pwDisplay = document.getElementById('pw-display');
        if (pwDisplay) pwDisplay.textContent = result.password;
        var pwBox = document.getElementById('pw-box');
        if (pwBox) pwBox.style.display = '';
        var resultData = document.getElementById('upload-result');
        if (resultData) resultData.textContent = JSON.stringify({ password: result.password });
    }

    document.getElementById('form-section').style.display = 'none';
    document.getElementById('result-section').style.display = '';

    var copyUrlBtn = document.getElementById('copy-url-btn');
    if (copyUrlBtn) copyUrlBtn.addEventListener('click', copyUrl);
    var copyPwBtn = document.getElementById('copy-pw-btn');
    if (copyPwBtn) copyPwBtn.addEventListener('click', copyPw);
}

// ── Chunked Upload ────────────────────────────────────────────────────────────

async function uploadChunked(form) {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) { showError('CSRF token missing – please reload the page.'); return; }
    var csrf      = csrfMeta.content;
    var sessionId = generateHex(16); // 32 hex chars
    var submitBtn = document.getElementById('submit-btn');
    var errorEl   = document.getElementById('error-msg');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading…';
    if (errorEl) errorEl.style.display = 'none';

    var filesMeta = [];

    for (var i = 0; i < selectedFiles.length; i++) {
        var file         = selectedFiles[i];
        var totalChunks  = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
        var progressEl   = document.getElementById('file-progress-' + i);
        var fillEl       = document.getElementById('file-progress-fill-' + i);
        var statusEl     = document.getElementById('file-status-' + i);

        if (progressEl) progressEl.style.display = '';
        if (statusEl)   statusEl.textContent = '0%';

        for (var c = 0; c < totalChunks; c++) {
            var start = c * CHUNK_SIZE;
            var chunk = file.slice(start, Math.min(start + CHUNK_SIZE, file.size));

            var fd = new FormData();
            fd.append('csrf_token',   csrf);
            fd.append('session_id',   sessionId);
            fd.append('file_index',   i);
            fd.append('chunk_index',  c);
            fd.append('total_chunks', totalChunks);
            fd.append('chunk',        chunk, file.name);

            var resp, data;
            try {
                resp = await fetch('upload_chunk.php', { method: 'POST', body: fd });
                data = await resp.json();
            } catch (e) {
                showError('Network error uploading "' + file.name + '".');
                submitBtn.disabled = false;
                return;
            }

            if (!data.ok) {
                showError('Upload error: ' + (data.error || 'Unknown error'));
                submitBtn.disabled = false;
                return;
            }

            var pct = Math.round((c + 1) / totalChunks * 100);
            if (fillEl)   fillEl.style.width = pct + '%';
            if (statusEl) statusEl.textContent = pct + '%';
        }

        if (statusEl) {
            statusEl.textContent = '✓';
            statusEl.className = 'file-item-status done';
        }

        filesMeta.push({ file_index: i, name: file.name, total_chunks: totalChunks });
    }

    // Finalize
    var fd = new FormData();
    fd.append('csrf_token',    csrf);
    fd.append('session_id',    sessionId);
    fd.append('password',      form.querySelector('[name="password"]').value);
    fd.append('max_downloads', form.querySelector('[name="max_downloads"]').value);
    fd.append('lifetime_days', form.querySelector('[name="lifetime_days"]').value);
    filesMeta.forEach(function(f, idx) {
        fd.append('files[' + idx + '][file_index]',   f.file_index);
        fd.append('files[' + idx + '][name]',         f.name);
        fd.append('files[' + idx + '][total_chunks]', f.total_chunks);
    });

    var resp, result;
    try {
        resp   = await fetch('upload_finalize.php', { method: 'POST', body: fd });
        result = await resp.json();
    } catch (e) {
        showError('Network error finalizing transfer.');
        submitBtn.disabled = false;
        return;
    }

    if (!result.ok) {
        showError('Error: ' + (result.error || 'Unknown error'));
        submitBtn.disabled = false;
        return;
    }

    showResult(result);
}

// ── Initialization ────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
    var metaFiles = document.querySelector('meta[name="max-files"]');
    var metaMb    = document.querySelector('meta[name="max-mb"]');
    if (metaFiles) maxFiles = parseInt(metaFiles.content, 10);
    if (metaMb)    maxBytes = parseInt(metaMb.content, 10) * 1048576;

    var copyUrlBtn = document.getElementById('copy-url-btn');
    if (copyUrlBtn) copyUrlBtn.addEventListener('click', copyUrl);
    var copyPwBtn = document.getElementById('copy-pw-btn');
    if (copyPwBtn) copyPwBtn.addEventListener('click', copyPw);

    var fileInput = document.getElementById('file-input');
    if (!fileInput) return;

    fileInput.addEventListener('change', function(e) {
        Array.from(e.target.files).forEach(function(f) {
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
            Array.from(e.dataTransfer.files).forEach(function(f) {
                if (selectedFiles.length < maxFiles) selectedFiles.push(f);
            });
            syncFileInput();
            updateFileList();
        });
    }

    var generateBtn = document.getElementById('generate-pw-btn');
    if (generateBtn) generateBtn.addEventListener('click', generatePassword);

    var uploadForm = document.getElementById('upload-form');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (selectedFiles.length === 0) {
                showError('Please select at least one file.');
                return;
            }
            uploadChunked(uploadForm).catch(function(err) {
                showError('Unexpected error: ' + (err.message || err));
                var btn = document.getElementById('submit-btn');
                if (btn) btn.disabled = false;
            });
        });
    }
});
