(function () {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fetchFolders(url) {
        if (!url) {
            return Promise.resolve([]);
        }

        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Не удалось загрузить папки.');
                    }
                    return data.items || [];
                });
            });
    }

    function renderFolderTabs(container, folders, activeSlug, onSelect) {
        if (!container) {
            return;
        }

        container.innerHTML = '';
        if (!folders || folders.length === 0) {
            return;
        }

        folders.forEach(function (folder) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-page-tabs__link' + (folder.slug === activeSlug ? ' admin-page-tabs__link--active' : '');
            button.textContent = folder.label;
            button.addEventListener('click', function () {
                onSelect(folder);
            });
            container.appendChild(button);
        });
    }

    window.adminMediaFolders = {
        escapeHtml: escapeHtml,
        fetchFolders: fetchFolders,
        renderFolderTabs: renderFolderTabs,
    };
})();
