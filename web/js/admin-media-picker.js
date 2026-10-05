(function () {
    function initPicker(root) {
        if (root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        var mode = root.dataset.mode;
        var kind = root.dataset.kind || 'image';
        var isVideo = kind === 'video';
        var isDocument = kind === 'document';
        var valueInput = root.querySelector('.admin-media-picker__value');
        var altInput = root.querySelector('.admin-media-picker__alt-input');
        var preview = root.querySelector('.admin-media-picker__preview');
        var clearBtn = root.querySelector('.admin-media-picker__clear-btn');
        var tileBtn = root.querySelector('.admin-media-picker__listing-tile-btn');
        var listingTileEnabled = root.dataset.listingTileEnabled === '1';
        var listingTileLoadUrl = root.dataset.listingTileLoadUrl || '';
        var listingTileSaveUrl = root.dataset.listingTileSaveUrl || '';
        var csrfParam = root.dataset.csrfParam || '';
        var csrfToken = root.dataset.csrfToken || '';
        var errorEl = root.querySelector('.admin-media-picker__error');
        var emptyLabel = isVideo ? 'Видео не выбрано' : (isDocument ? 'PDF не выбран' : 'Фото не выбрано');

        function documentPreviewHtml(url, filename) {
            var name = filename || (url ? url.split('/').pop() : '');
            return '<a class="admin-media-picker__document" href="' + window.AdminMediaLibrary.escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' +
                '<span class="admin-media-picker__document-icon">PDF</span>' +
                '<span class="admin-media-picker__document-name">' + window.AdminMediaLibrary.escapeHtml(name) + '</span>' +
                '</a>';
        }

        function setError(message) {
            if (!errorEl) {
                return;
            }
            errorEl.hidden = !message;
            errorEl.textContent = message || '';
        }

        function clearPreviewContent() {
            preview.querySelectorAll(
                ':scope > :not(.admin-media-picker__listing-tile-btn)'
            ).forEach(function (node) {
                node.remove();
            });
        }

        function appendPreviewContent(node) {
            var anchor = preview.querySelector('.admin-media-picker__listing-tile-btn');
            if (anchor) {
                preview.insertBefore(node, anchor);
            } else {
                preview.appendChild(node);
            }
        }

        function renderPreview(url, alt) {
            preview.classList.toggle('is-empty', !url);
            clearPreviewContent();
            if (!url) {
                var placeholder = document.createElement('span');
                placeholder.className = 'admin-media-picker__placeholder';
                placeholder.textContent = emptyLabel;
                appendPreviewContent(placeholder);
                return;
            }
            if (isVideo) {
                var video = document.createElement('video');
                video.src = url;
                video.controls = true;
                video.preload = 'metadata';
                appendPreviewContent(video);
                return;
            }
            if (isDocument) {
                var wrapper = document.createElement('div');
                wrapper.innerHTML = documentPreviewHtml(url, alt);
                appendPreviewContent(wrapper.firstElementChild);
                return;
            }
            var image = document.createElement('img');
            image.src = url;
            image.alt = alt || '';
            appendPreviewContent(image);
        }

        function applyMediaItem(item) {
            var displayUrl = isDocument
                ? item.url
                : (item.urls && item.urls.medium ? item.urls.medium : item.url);
            var displayName = isDocument ? (item.filename || '') : '';
            valueInput.value = mode === 'id' ? String(item.id) : displayUrl;
            if (altInput && item.alt) {
                altInput.value = item.alt;
            }
            renderPreview(displayUrl, displayName || (altInput ? altInput.value : (item.alt || '')));
            setError('');
            updateTileButton();
            valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function clearSelection() {
            valueInput.value = '';
            renderPreview('', '');
            setError('');
            updateTileButton();
            valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function updateTileButton() {
            if (!tileBtn) {
                return;
            }
            var mediaId = parseInt(valueInput.value || '0', 10);
            var visible = listingTileEnabled && mediaId > 0;
            tileBtn.hidden = !visible;
            if (visible) {
                tileBtn.removeAttribute('disabled');
            }
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', clearSelection);
        }

        if (tileBtn) {
            tileBtn.addEventListener('mousedown', function (event) {
                event.stopPropagation();
            });
            tileBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!window.AdminListingTileEditor) {
                    setError('Редактор кадра не загружен. Обновите страницу.');
                    return;
                }
                var mediaId = parseInt(valueInput.value || '0', 10);
                if (mediaId <= 0) {
                    setError('Сначала выберите фото.');
                    return;
                }
                window.AdminListingTileEditor.open({
                    mediaId: mediaId,
                    loadUrl: listingTileLoadUrl,
                    saveUrl: listingTileSaveUrl,
                    csrfParam: csrfParam,
                    csrfToken: csrfToken,
                    onSaved: function (payload) {
                        if (payload.previewUrl) {
                            renderPreview(payload.previewUrl, altInput ? altInput.value : '');
                            updateTileButton();
                        }
                    },
                });
            });
        }

        updateTileButton();

        if (!window.AdminMediaLibrary) {
            return;
        }

        window.AdminMediaLibrary.init(root, {
            mode: 'single',
            kind: kind,
            defaultFolder: root.dataset.defaultFolder || '',
            openButton: root.querySelector('.admin-media-library__open-btn'),
            onSelect: applyMediaItem,
            onError: setError,
            onDeleted: function (item) {
                if (mode === 'id' && String(valueInput.value) === String(item.id)) {
                    clearSelection();
                }
            },
            onUploaded: function (item) {
                if (mode === 'single') {
                    applyMediaItem(item);
                }
            },
        });
    }

    document.querySelectorAll('.admin-media-picker').forEach(initPicker);
    window.adminMediaPickerInit = initPicker;
})();
