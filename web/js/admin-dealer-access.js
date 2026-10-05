(function () {
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        return Promise.resolve();
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy-dealer-access]');
        if (!button) {
            return;
        }

        event.preventDefault();
        var text = button.getAttribute('data-copy-dealer-access') || '';
        if (text === '') {
            return;
        }

        copyText(text).then(function () {
            var original = button.textContent;
            button.textContent = 'Скопировано';
            button.classList.add('admin-btn--success');
            window.setTimeout(function () {
                button.textContent = original;
                button.classList.remove('admin-btn--success');
            }, 2000);
        });
    });
})();
