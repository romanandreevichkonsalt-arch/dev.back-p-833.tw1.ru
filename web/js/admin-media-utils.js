(function () {
    function decodeFilename(value) {
        var text = String(value || '');
        try {
            return decodeURIComponent(text.replace(/\+/g, ' '));
        } catch (error) {
            return text;
        }
    }

    window.adminFormatFilename = function (filename) {
        return decodeFilename(filename);
    };

    window.adminTruncateFilename = function (filename) {
        return decodeFilename(filename);
    };

    window.adminMediaDeleteButtonHtml = function () {
        return '<button type="button" class="admin-icon-btn admin-icon-btn--danger admin-media-library__delete" title="Удалить из медиатеки" aria-label="Удалить из медиатеки">' +
            '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>' +
            '</svg>' +
        '</button>';
    };
})();
