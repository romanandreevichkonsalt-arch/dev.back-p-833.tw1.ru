(function () {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function initGallery(root) {
        if (root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        var productId = parseInt(root.dataset.productId || '0', 10);
        var items = JSON.parse(root.dataset.items || '[]');
        var galleryDeleteUrl = root.dataset.galleryDeleteUrl;
        var galleryAddUrl = root.dataset.galleryAddUrl;
        var galleryReorderUrl = root.dataset.galleryReorderUrl;
        var purpose = root.dataset.purpose || '';
        var pendingInputName = root.dataset.pendingInputName || 'gallery_media_ids[]';
        var csrfParam = root.dataset.csrfParam;
        var csrfToken = root.dataset.csrfToken;
        var listingTileEnabled = root.dataset.listingTileEnabled === '1';
        var listingTileLoadUrl = root.dataset.listingTileLoadUrl || '';
        var listingTileSaveUrl = root.dataset.listingTileSaveUrl || '';
        var grid = root.querySelector('.admin-product-gallery__grid');
        var pending = root.querySelector('.admin-product-gallery__pending');
        var errorEl = root.querySelector('.admin-product-gallery__error');
        var draggedIndex = null;

        function setError(message) {
            if (!errorEl) {
                return;
            }
            errorEl.hidden = !message;
            errorEl.textContent = message || '';
        }

        function hasMediaId(mediaId) {
            return items.some(function (existing) {
                return String(existing.mediaId) === String(mediaId);
            });
        }

        var listingTileIconSvg =
            '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<path d="M6 2v4H2"></path><path d="M18 22v-4h4"></path><path d="M22 6h-4V2"></path><path d="M2 18h4v4"></path>' +
                '<rect x="7" y="7" width="10" height="10" rx="1"></rect>' +
            '</svg>';

        function renderItem(item, index) {
            var card = document.createElement('div');
            card.className = 'admin-product-gallery__item';
            card.draggable = true;
            card.dataset.linkId = item.linkId ? String(item.linkId) : '';
            card.dataset.mediaId = String(item.mediaId);
            card.dataset.index = String(index);
            card.innerHTML =
                '<div class="admin-product-gallery__handle" aria-hidden="true">' +
                    '<svg class="admin-icon" viewBox="0 0 24 24" fill="currentColor">' +
                        '<circle cx="9" cy="7" r="1.5"></circle><circle cx="15" cy="7" r="1.5"></circle>' +
                        '<circle cx="9" cy="12" r="1.5"></circle><circle cx="15" cy="12" r="1.5"></circle>' +
                        '<circle cx="9" cy="17" r="1.5"></circle><circle cx="15" cy="17" r="1.5"></circle>' +
                    '</svg>' +
                '</div>' +
                '<div class="admin-product-gallery__preview">' +
                    '<img src="' + escapeHtml(item.url) + '" alt="' + escapeHtml(item.alt || item.filename || '') + '" draggable="false">' +
                    (index === 0 ? '<span class="admin-product-gallery__badge">Основное</span>' : '') +
                    (listingTileEnabled && index === 0
                        ? '<button type="button" class="admin-icon-btn admin-product-gallery__tile-btn" title="Кадр каталога" aria-label="Кадр каталога">' +
                            listingTileIconSvg +
                        '</button>'
                        : '') +
                '</div>' +
                '<div class="admin-product-gallery__meta">' + escapeHtml(window.adminTruncateFilename(item.filename || '')) + '</div>' +
                '<button type="button" class="admin-icon-btn admin-icon-btn--danger admin-product-gallery__delete" title="Удалить" aria-label="Удалить">' +
                    '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                        '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>' +
                    '</svg>' +
                '</button>';

            card.querySelector('.admin-product-gallery__delete').addEventListener('click', function () {
                removeItem(item);
            });

            var tileBtn = card.querySelector('.admin-product-gallery__tile-btn');
            if (tileBtn && window.AdminListingTileEditor) {
                tileBtn.addEventListener('mousedown', function (event) {
                    event.stopPropagation();
                });
                tileBtn.addEventListener('click', function (event) {
                    event.stopPropagation();
                    window.AdminListingTileEditor.open({
                        mediaId: item.mediaId,
                        loadUrl: listingTileLoadUrl,
                        saveUrl: listingTileSaveUrl,
                        csrfParam: csrfParam,
                        csrfToken: csrfToken,
                        onSaved: function (payload) {
                            if (payload.previewUrl) {
                                item.url = payload.previewUrl;
                                renderAll();
                            }
                        },
                    });
                });
            }

            card.addEventListener('dragstart', function (event) {
                draggedIndex = index;
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(index));
            });

            card.addEventListener('dragend', function () {
                draggedIndex = null;
                grid.querySelectorAll('.admin-product-gallery__item').forEach(function (node) {
                    node.classList.remove('is-dragging', 'is-drag-over');
                });
            });

            card.addEventListener('dragover', function (event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                card.classList.add('is-drag-over');
            });

            card.addEventListener('dragleave', function () {
                card.classList.remove('is-drag-over');
            });

            card.addEventListener('drop', function (event) {
                event.preventDefault();
                card.classList.remove('is-drag-over');
                var fromIndex = draggedIndex;
                var toIndex = index;
                if (fromIndex === null || fromIndex === toIndex) {
                    return;
                }
                moveItem(fromIndex, toIndex);
            });

            return card;
        }

        function renderAll() {
            grid.innerHTML = '';
            pending.innerHTML = '';
            items.forEach(function (item, index) {
                grid.appendChild(renderItem(item, index));
                if (!productId) {
                    appendPendingInput(item.mediaId);
                }
            });

            if (items.length === 0) {
                grid.innerHTML = '<div class="admin-product-gallery__empty">Фото пока не добавлены</div>';
                return;
            }

            grid.querySelectorAll('.admin-product-gallery__item').forEach(function (card) {
                card.draggable = items.length > 1;
            });
        }

        function appendPendingInput(mediaId) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = pendingInputName;
            input.value = String(mediaId);
            input.dataset.mediaId = String(mediaId);
            pending.appendChild(input);
        }

        function removePendingInput(mediaId) {
            pending.querySelectorAll('input[data-media-id="' + mediaId + '"]').forEach(function (node) {
                node.remove();
            });
        }

        function syncPendingInputsOrder() {
            pending.innerHTML = '';
            items.forEach(function (item) {
                appendPendingInput(item.mediaId);
            });
        }

        function saveOrder() {
            if (!productId) {
                syncPendingInputsOrder();
                return Promise.resolve();
            }
            if (!galleryReorderUrl) {
                return Promise.resolve();
            }
            var formData = new FormData();
            items.forEach(function (item) {
                if (item.linkId) {
                    formData.append('linkIds[]', String(item.linkId));
                }
            });
            formData.append(csrfParam, csrfToken);
            if (purpose) {
                formData.append('purpose', purpose);
            }
            return fetch(galleryReorderUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Не удалось сохранить порядок.');
                    }
                    return data;
                });
            });
        }

        function moveItem(fromIndex, toIndex) {
            var moved = items.splice(fromIndex, 1)[0];
            items.splice(toIndex, 0, moved);
            renderAll();
            setError('');
            saveOrder().catch(function (error) {
                setError(error.message || 'Не удалось сохранить порядок.');
            });
        }

        function addItem(item) {
            if (hasMediaId(item.mediaId)) {
                return Promise.resolve();
            }
            items.push(item);
            renderAll();
            return Promise.resolve();
        }

        function removeItem(item) {
            if (productId && item.linkId) {
                var formData = new FormData();
                formData.append('linkId', String(item.linkId));
                formData.append(csrfParam, csrfToken);
                fetch(galleryDeleteUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok) {
                                throw new Error(data.message || 'Не удалось удалить фото.');
                            }
                            return data;
                        });
                    })
                    .then(function () {
                        items = items.filter(function (existing) {
                            return String(existing.mediaId) !== String(item.mediaId);
                        });
                        renderAll();
                        setError('');
                    })
                    .catch(function (error) {
                        setError(error.message || 'Не удалось удалить фото.');
                    });
                return;
            }
            items = items.filter(function (existing) {
                return String(existing.mediaId) !== String(item.mediaId);
            });
            removePendingInput(item.mediaId);
            renderAll();
            setError('');
        }

        function attachToProduct(mediaItem) {
            if (!productId) {
                return addItem({
                    linkId: null,
                    mediaId: mediaItem.id,
                    url: mediaItem.urls && mediaItem.urls.medium ? mediaItem.urls.medium : mediaItem.url,
                    alt: mediaItem.alt,
                    filename: mediaItem.filename,
                });
            }
            if (hasMediaId(mediaItem.id)) {
                return Promise.resolve();
            }
            var formData = new FormData();
            formData.append('mediaId', String(mediaItem.id));
            if (purpose) {
                formData.append('purpose', purpose);
            }
            formData.append(csrfParam, csrfToken);
            return fetch(galleryAddUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Не удалось добавить фото.');
                    }
                    return addItem(data);
                });
            });
        }

        renderAll();

        if (window.AdminMediaLibrary) {
            window.AdminMediaLibrary.init(root, {
                mode: 'multi',
                defaultFolder: root.dataset.defaultFolder || '',
                openButton: root.querySelector('.admin-media-library__open-btn'),
                getLinkedIds: function () {
                    return items.map(function (item) { return item.mediaId; });
                },
                onApply: function (selectedItems) {
                    return Promise.all(selectedItems.map(function (mediaItem) {
                        return attachToProduct(mediaItem);
                    }));
                },
                onUploaded: function (mediaItem) {
                    return attachToProduct(mediaItem);
                },
                onDeleted: function (item) {
                    if (hasMediaId(item.id)) {
                        items = items.filter(function (existing) {
                            return String(existing.mediaId) !== String(item.id);
                        });
                        renderAll();
                    }
                },
                onError: setError,
            });
        }
    }

    document.querySelectorAll('.admin-product-gallery').forEach(initGallery);
})();
