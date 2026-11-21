(() => {
    const protocol = document.querySelector('#protocol');
    const natFields = document.querySelector('#nat-fields');
    const service = document.querySelector('#internal_port');
    const customPortField = document.querySelector('#custom-port-field');
    const customPort = document.querySelector('#custom_internal_port');
    const manualCommentField = document.querySelector('#manual-comment-field');
    const manualComment = document.querySelector('#comment');
    const commentPreview = document.querySelector('#auto-comment-preview');
    const username = document.querySelector('input[name="username"]');

    const suffixes = {
        '8291': 'winbox',
        '8728': 'mikrotik-api',
        '80': 'webfig',
    };

    if (protocol && natFields && service && customPortField && customPort
        && manualCommentField && manualComment && commentPreview && username) {
        function updateFields() {
            const natEnabled = protocol.value !== '';
            const manual = natEnabled && service.value === 'custom';

            natFields.hidden = !natEnabled;
            service.disabled = !natEnabled;
            service.required = natEnabled;
            customPortField.hidden = !manual;
            customPort.disabled = !manual;
            customPort.required = manual;
            manualCommentField.hidden = !manual;
            manualComment.disabled = !manual;
            manualComment.required = manual;

            if (!manual) {
                customPort.value = '';
                manualComment.value = '';
            }

            const suffix = suffixes[service.value];
            commentPreview.hidden = !natEnabled || !suffix;
            if (suffix) {
                const name = username.value.trim();
                commentPreview.textContent = name
                    ? `Comment otomatis: ${name}-${suffix}`
                    : `Comment otomatis menggunakan username-${suffix}`;
            }
        }

        protocol.addEventListener('change', updateFields);
        service.addEventListener('change', updateFields);
        username.addEventListener('input', updateFields);
        updateFields();
    }

    document.querySelectorAll('[data-copy-text], [data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = button.dataset.copyTarget
                ? document.getElementById(button.dataset.copyTarget)
                : null;
            const text = button.dataset.copyText
                ?? target?.value
                ?? target?.textContent
                ?? '';
            const status = button.parentElement?.querySelector('.copy-status');

            try {
                await navigator.clipboard.writeText(text);
                if (status) {
                    status.textContent = 'Berhasil disalin.';
                }
            } catch {
                if (status) {
                    status.textContent = 'Gagal menyalin. Salin teks secara manual.';
                }
            }
        });
    });

    document.querySelectorAll('[data-download-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.downloadTarget);
            if (!target) {
                return;
            }

            const blob = new Blob([target.value ?? target.textContent ?? ''], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = button.dataset.downloadName || 'routeros-script.rsc';
            link.click();
            URL.revokeObjectURL(url);
        });
    });

    const successDialog = document.querySelector('#success-actions');
    if (successDialog && typeof successDialog.showModal === 'function') {
        successDialog.showModal();
    }
})();
