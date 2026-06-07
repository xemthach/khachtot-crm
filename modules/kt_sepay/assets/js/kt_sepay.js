(function() {
    document.querySelectorAll('[data-copy-target]').forEach(function(button) {
        button.addEventListener('click', function() {
            var node = document.querySelector(button.getAttribute('data-copy-target'));
            if (!node) return;
            navigator.clipboard.writeText(node.textContent || '');
        });
    });

    var healthButtons = document.querySelectorAll('.kt-sepay-health-btn');
    if (!healthButtons.length) {
        return;
    }

    var resultBox = document.getElementById('kt-sepay-health-result');
    var liveTable = document.getElementById('kt-sepay-health-live-table');
    var qrPreview = document.getElementById('kt-sepay-health-qr-preview');
    var qrLink = document.getElementById('kt-sepay-health-qr-link');
    var qrImage = document.getElementById('kt-sepay-health-qr-image');

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function badgeClass(status) {
        if (status === 'success') return 'success';
        if (status === 'warning') return 'warning';
        if (status === 'error') return 'danger';
        return 'info';
    }

    function setResult(message, level) {
        if (!resultBox) return;
        resultBox.className = 'alert alert-' + (level || 'info');
        resultBox.textContent = message || '';
        resultBox.classList.remove('hide');
    }

    function setQrPreview(qrUrl) {
        if (!qrPreview || !qrLink || !qrImage) return;
        if (!qrUrl) {
            qrPreview.classList.add('hide');
            qrLink.textContent = '';
            qrLink.removeAttribute('href');
            qrImage.removeAttribute('src');
            return;
        }

        qrLink.textContent = qrUrl;
        qrLink.href = qrUrl;
        qrImage.src = qrUrl;
        qrPreview.classList.remove('hide');
    }

    function renderRow(label, payload) {
        if (!liveTable) return;
        var detail = payload && payload.detail ? JSON.stringify(payload.detail, null, 2) : '';
        liveTable.innerHTML =
            '<tr>' +
            '<td>' + escapeHtml(label) + '</td>' +
            '<td><span class="label label-' + badgeClass(payload.status || (payload.success ? 'success' : 'error')) + '">' + escapeHtml(payload.status || (payload.success ? 'success' : 'error')) + '</span></td>' +
            '<td>' + escapeHtml(payload.message || '') + '</td>' +
            '<td>' + escapeHtml(payload.latency_ms || 0) + ' ms</td>' +
            '<td><pre style="white-space:pre-wrap;word-break:break-word;margin:0;">' + escapeHtml(detail) + '</pre></td>' +
            '</tr>';
    }

    healthButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var endpoint = button.getAttribute('data-endpoint');
            var label = button.getAttribute('data-test-label') || 'Kiểm tra kết nối';
            var formData = new FormData();
            if (typeof csrfData !== 'undefined' && csrfData.token_name && csrfData.hash) {
                formData.append(csrfData.token_name, csrfData.hash);
            }

            button.disabled = true;
            setResult('Đang chạy ' + label + '...', 'info');
            setQrPreview('');

            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function(response) {
                return response.json().then(function(payload) {
                    return { ok: response.ok, payload: payload };
                });
            }).then(function(result) {
                if (typeof csrfData !== 'undefined' && result.payload && result.payload.csrf_hash) {
                    csrfData.hash = result.payload.csrf_hash;
                }
                renderRow(label, result.payload || {});
                setResult((result.payload && result.payload.message) || 'Đã hoàn tất kiểm tra.', result.ok ? 'success' : 'warning');
                setQrPreview(result.payload && result.payload.qr_url ? result.payload.qr_url : '');
            }).catch(function(error) {
                renderRow(label, {
                    success: false,
                    status: 'error',
                    message: error && error.message ? error.message : 'Yêu cầu thất bại',
                    latency_ms: 0,
                    detail: {}
                });
                setResult('Kiểm tra kết nối thất bại.', 'danger');
            }).finally(function() {
                button.disabled = false;
            });
        });
    });
})();
