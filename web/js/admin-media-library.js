(function () {
    var LARGE_FILE_WARNING_BYTES = 20 * 1024 * 1024;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            var context = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' Б';
        }
        if (bytes < 1048576) {
            return (bytes / 1024).toFixed(1) + ' КБ';
        }
        return (bytes / 1048576).toFixed(1) + ' МБ';
    }

    function renderThumb(item, isVideo, isDocument) {
        if (item.previewOk === false) {
            return '<span class="admin-media-library__broken-thumb">Нет превью</span>';
        }

        var url = item.url || (item.urls && item.urls.mini) || '';
        if (isVideo || item.kind === 'video' || (item.mime && item.mime.indexOf('video/') === 0)) {
            return '<video src="' + escapeHtml(url) + '" muted preload="metadata"></video>';
        }
        if (isDocument || item.kind === 'document' || item.mime === 'application/pdf' || item.mime === 'application/zip') {
            var label = item.mime === 'application/zip' || (item.filename && item.filename.slice(-4) === '.zip')
                ? 'ZIP'
                : 'PDF';
            return '<span class="admin-media-library__doc-thumb">' + label + '</span>';
        }
        return '<img src="' + escapeHtml(url) + '" alt="" loading="lazy" onerror="this.style.display=\'none\';this.parentElement.classList.add(\'is-broken\');">';
    }

    function formatItemFilename(item) {
        if (window.adminFormatFilename) {
            return window.adminFormatFilename(item.displayFilename || item.filename || '');
        }

        return item.displayFilename || item.filename || '';
    }

    /**
     * @param {HTMLElement} root — .admin-media-picker или .admin-product-gallery
     * @param {object} options
     */
    function init(root, options) {
        if (root.dataset.mediaLibraryInitialized === '1') {
            return;
        }
        root.dataset.mediaLibraryInitialized = '1';

        var modal = root.querySelector('.admin-media-library__modal');
        if (!modal) {
            return;
        }

        var kind = options.kind || root.dataset.kind || 'image';
        var isVideo = kind === 'video';
        var isDocument = kind === 'document';
        var mode = options.mode || 'single';
        var defaultFolder = options.defaultFolder || root.dataset.defaultFolder || '';
        var uploadUrl = options.uploadUrl || root.dataset.uploadUrl;
        var mediaDeleteUrl = options.mediaDeleteUrl || root.dataset.mediaDeleteUrl || '';
        var libraryListUrl = options.libraryListUrl || root.dataset.libraryListUrl;
        var foldersListUrl = options.foldersListUrl || root.dataset.foldersListUrl || '';
        var csrfParam = options.csrfParam || root.dataset.csrfParam;
        var csrfToken = options.csrfToken || root.dataset.csrfToken;
        var getLinkedIds = options.getLinkedIds || function () { return []; };
        var onSelect = options.onSelect || null;
        var onApply = options.onApply || null;
        var onError = options.onError || function () {};
        var openButton = options.openButton || root.querySelector('.admin-media-library__open-btn');

        var selectedFolderSlug = defaultFolder || '';
        var folderContainer = modal.querySelector('.admin-media-library__folders');
        var searchInput = modal.querySelector('.admin-media-library__search');
        var gridLinked = modal.querySelector('.admin-media-library__grid--linked');
        var gridLibrary = modal.querySelector('.admin-media-library__grid--library');
        var sectionLinked = modal.querySelector('.admin-media-library__section--linked');
        var sectionLibraryTitle = modal.querySelector('.admin-media-library__section-title--library');
        var uploadBtn = modal.querySelector('.admin-media-library__upload-btn');
        var fileInput = modal.querySelector('.admin-media-library__file');
        var uploadWarning = modal.querySelector('.admin-media-library__upload-warning');
        var applyBtn = modal.querySelector('.admin-media-library__apply-btn');
        var folderPickModal = modal.querySelector('.admin-media-library__folder-pick');
        var folderPickSelect = modal.querySelector('.admin-media-library__folder-pick-select');
        var folderPickConfirm = modal.querySelector('.admin-media-library__folder-pick-confirm');
        var mediaFolders = window.adminMediaFolders || null;
        var modalItems = [];
        var foldersCache = [];
        var pendingUploadFiles = null;

        function setUploadWarning(message) {
            if (!uploadWarning) {
                return;
            }
            uploadWarning.hidden = !message;
            uploadWarning.textContent = message || '';
        }

        function closeModal() {
            modal.hidden = true;
            document.body.classList.remove('admin-modal-open');
            setUploadWarning('');
        }

        function openModal() {
            modal.hidden = false;
            document.body.classList.add('admin-modal-open');
            if (searchInput) {
                searchInput.value = '';
            }
            loadModalItems('');
            if (searchInput) {
                searchInput.focus();
            }
        }

        function deleteMediaFromLibrary(item, event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (!mediaDeleteUrl) {
                return;
            }
            var name = item.filename || 'файл';
            if (!window.confirm('Удалить «' + name + '» из медиатеки?')) {
                return;
            }

            var formData = new FormData();
            formData.append('id', String(item.id));
            formData.append(csrfParam, csrfToken);

            fetch(mediaDeleteUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Не удалось удалить файл.');
                        }
                        return data;
                    });
                })
                .then(function () {
                    if (options.onDeleted) {
                        options.onDeleted(item);
                    }
                    loadModalItems(searchInput ? searchInput.value.trim() : '');
                })
                .catch(function (error) {
                    onError(error.message || 'Не удалось удалить файл.');
                });
        }

        function buildCard(item, linked) {
            var card = document.createElement('div');
            card.className = 'admin-media-library__card' + (linked ? ' is-linked' : '');
            card.dataset.mediaId = String(item.id);

            if (mode === 'multi') {
                var checked = linked;
                var disabled = linked;
                card.innerHTML =
                    '<input type="checkbox" class="admin-product-gallery__modal-check"' +
                        (checked ? ' checked' : '') + (disabled ? ' disabled' : '') +
                        ' value="' + escapeHtml(String(item.id)) + '">' +
                    (window.adminMediaDeleteButtonHtml ? window.adminMediaDeleteButtonHtml() : '') +
                    '<span class="admin-product-gallery__modal-thumb">' + renderThumb(item, isVideo, isDocument) + '</span>' +
                    '<span class="admin-product-gallery__modal-name">' + escapeHtml(formatItemFilename(item)) + '</span>' +
                    (linked ? '<span class="admin-product-gallery__modal-tag">Уже добавлено</span>' : '');

                if (!disabled) {
                    card.addEventListener('click', function (event) {
                        if (event.target.closest('.admin-media-library__delete')) {
                            return;
                        }
                        if (event.target.tagName === 'INPUT') {
                            return;
                        }
                        var checkbox = card.querySelector('.admin-product-gallery__modal-check');
                        if (checkbox) {
                            checkbox.checked = !checkbox.checked;
                            card.classList.toggle('is-selected', checkbox.checked);
                        }
                    });
                    var checkbox = card.querySelector('.admin-product-gallery__modal-check');
                    if (checkbox) {
                        checkbox.addEventListener('change', function () {
                            card.classList.toggle('is-selected', checkbox.checked);
                        });
                    }
                }
            } else {
                card.setAttribute('role', 'button');
                card.tabIndex = 0;
                card.innerHTML =
                    (window.adminMediaDeleteButtonHtml ? window.adminMediaDeleteButtonHtml() : '') +
                    '<span class="admin-product-gallery__modal-thumb">' + renderThumb(item, isVideo, isDocument) + '</span>' +
                    '<span class="admin-product-gallery__modal-name">' + escapeHtml(formatItemFilename(item)) + '</span>';

                card.addEventListener('click', function (event) {
                    if (event.target.closest('.admin-media-library__delete')) {
                        return;
                    }
                    if (onSelect) {
                        onSelect(item);
                    }
                    closeModal();
                });
                card.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        if (onSelect) {
                            onSelect(item);
                        }
                        closeModal();
                    }
                });
            }

            var deleteBtn = card.querySelector('.admin-media-library__delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function (event) {
                    deleteMediaFromLibrary(item, event);
                });
            }

            return card;
        }

        function renderModalItems() {
            var linkedIds = getLinkedIds().map(String);
            var linkedSet = {};
            linkedIds.forEach(function (id) {
                linkedSet[id] = true;
            });

            var linkedItems = [];
            var libraryItems = [];
            modalItems.forEach(function (item) {
                if (linkedSet[String(item.id)] || item.isLinked) {
                    linkedItems.push(item);
                } else {
                    libraryItems.push(item);
                }
            });

            if (sectionLinked) {
                var showLinked = mode === 'multi' && linkedItems.length > 0;
                sectionLinked.hidden = !showLinked;
                if (gridLinked) {
                    gridLinked.innerHTML = '';
                    linkedItems.forEach(function (item) {
                        gridLinked.appendChild(buildCard(item, true));
                    });
                }
            }

            if (sectionLibraryTitle) {
                sectionLibraryTitle.hidden = !(mode === 'multi' && linkedItems.length > 0);
            }

            if (!gridLibrary) {
                return;
            }

            if (libraryItems.length === 0 && linkedItems.length === 0) {
                gridLibrary.innerHTML = '<p class="admin-muted admin-product-gallery__modal-empty">' +
                    (isVideo ? 'Видео не найдены.' : (isDocument ? 'Документы не найдены.' : 'Изображения не найдены.')) + '</p>';
                return;
            }

            gridLibrary.innerHTML = '';
            libraryItems.forEach(function (item) {
                gridLibrary.appendChild(buildCard(item, false));
            });
        }

        function loadModalItems(query) {
            if (!libraryListUrl || !gridLibrary) {
                return Promise.resolve();
            }

            gridLibrary.innerHTML = '<p class="admin-muted admin-product-gallery__modal-empty">Загрузка...</p>';

            var url = libraryListUrl;
            var linkedIds = getLinkedIds();
            if (selectedFolderSlug) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'folder=' + encodeURIComponent(selectedFolderSlug);
            }
            if (linkedIds.length > 0) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'linked_ids=' + encodeURIComponent(linkedIds.join(','));
            }
            if (query) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(query);
            }

            return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Не удалось загрузить медиатеку.');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    modalItems = data.items || [];
                    renderModalItems();
                })
                .catch(function (error) {
                    gridLibrary.innerHTML = '<p class="admin-muted admin-product-gallery__modal-empty">' +
                        escapeHtml(error.message || 'Не удалось загрузить медиатеку.') + '</p>';
                });
        }

        function uploadFile(file, folderSlug) {
            var formData = new FormData();
            formData.append('file', file);
            formData.append('kind', kind);
            if (folderSlug) {
                formData.append('folder', folderSlug);
            }
            formData.append(csrfParam, csrfToken);

            return fetch(uploadUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error(data.message || 'Не удалось загрузить файл.');
                    }
                    return data;
                });
            });
        }

        function closeFolderPick() {
            if (folderPickModal) {
                folderPickModal.hidden = true;
            }
            pendingUploadFiles = null;
        }

        function openFolderPick(files) {
            if (!folderPickModal || !folderPickSelect) {
                return;
            }
            pendingUploadFiles = files;
            folderPickSelect.innerHTML = '';
            foldersCache.forEach(function (folder) {
                var option = document.createElement('option');
                option.value = folder.slug;
                option.textContent = folder.label;
                folderPickSelect.appendChild(option);
            });
            folderPickModal.hidden = false;
        }

        function resolveFolderSlugForUpload() {
            if (selectedFolderSlug) {
                return selectedFolderSlug;
            }
            if (defaultFolder) {
                return defaultFolder;
            }
            return '';
        }

        function processUploadFiles(files) {
            if (!files || !files.length) {
                return;
            }

            var folderSlug = resolveFolderSlugForUpload();
            if (!folderSlug && foldersCache.length > 0) {
                openFolderPick(files);
                return;
            }
            if (!folderSlug) {
                onError('Выберите папку для загрузки.');
                return;
            }

            var largeFiles = Array.from(files).filter(function (f) { return f.size > LARGE_FILE_WARNING_BYTES; });
            if (largeFiles.length > 0) {
                setUploadWarning('Файл большой (' + formatFileSize(largeFiles[0].size) + '). Загрузка может занять время.');
            } else {
                setUploadWarning('');
            }

            uploadBtn.disabled = true;
            var uploads = Array.from(files).map(function (file) {
                return uploadFile(file, folderSlug);
            });

            Promise.all(uploads)
                .then(function (items) {
                    if (options.onUploaded) {
                        items.forEach(function (item) {
                            options.onUploaded(item);
                        });
                    }
                    if (mode === 'single' && items.length === 1 && onSelect) {
                        onSelect(items[0]);
                        closeModal();
                    } else {
                        loadModalItems(searchInput ? searchInput.value.trim() : '');
                    }
                    if (fileInput) {
                        fileInput.value = '';
                    }
                })
                .catch(function (error) {
                    onError(error.message || 'Не удалось загрузить файл.');
                })
                .finally(function () {
                    uploadBtn.disabled = false;
                });
        }

        if (uploadBtn && fileInput) {
            uploadBtn.addEventListener('click', function () {
                fileInput.click();
            });
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length) {
                    processUploadFiles(fileInput.files);
                }
            });
        }

        if (folderPickConfirm) {
            folderPickConfirm.addEventListener('click', function () {
                if (!pendingUploadFiles || !folderPickSelect) {
                    closeFolderPick();
                    return;
                }
                selectedFolderSlug = folderPickSelect.value;
                var files = pendingUploadFiles;
                closeFolderPick();
                processUploadFiles(files);
            });
        }

        modal.querySelectorAll('[data-folder-pick-close]').forEach(function (node) {
            node.addEventListener('click', closeFolderPick);
        });

        if (applyBtn && onApply) {
            applyBtn.addEventListener('click', function () {
                var selected = Array.from(modal.querySelectorAll('.admin-media-library__grid--library .admin-product-gallery__modal-check:checked'));
                if (selected.length === 0) {
                    onError('Отметьте хотя бы один файл в медиатеке.');
                    return;
                }
                applyBtn.disabled = true;
                var items = selected.map(function (checkbox) {
                    var id = parseInt(checkbox.value, 10);
                    return modalItems.find(function (item) { return parseInt(item.id, 10) === id; });
                }).filter(Boolean);
                Promise.resolve(onApply(items))
                    .then(function () {
                        closeModal();
                        loadModalItems('');
                    })
                    .catch(function (error) {
                        onError(error.message || 'Не удалось добавить выбранные файлы.');
                    })
                    .finally(function () {
                        applyBtn.disabled = false;
                    });
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', debounce(function () {
                loadModalItems(searchInput.value.trim());
            }, 300));
        }

        modal.querySelectorAll('[data-modal-close]').forEach(function (node) {
            node.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                if (folderPickModal && !folderPickModal.hidden) {
                    closeFolderPick();
                } else {
                    closeModal();
                }
            }
        });

        if (openButton) {
            openButton.addEventListener('click', openModal);
        }

        if (mediaFolders && foldersListUrl && folderContainer) {
            mediaFolders.fetchFolders(foldersListUrl).then(function (folders) {
                if (isDocument) {
                    folders = folders.filter(function (folder) {
                        return folder.slug === 'documents';
                    });
                }
                foldersCache = folders;
                if (!selectedFolderSlug && defaultFolder) {
                    selectedFolderSlug = defaultFolder;
                }
                if (!selectedFolderSlug && folders.length > 0) {
                    selectedFolderSlug = folders[0].slug;
                }
                mediaFolders.renderFolderTabs(folderContainer, folders, selectedFolderSlug, function (folder) {
                    selectedFolderSlug = folder.slug;
                    loadModalItems(searchInput ? searchInput.value.trim() : '');
                });
            });
        }

        return {
            open: openModal,
            close: closeModal,
            reload: function () { loadModalItems(searchInput ? searchInput.value.trim() : ''); },
        };
    }

    window.AdminMediaLibrary = {
        init: init,
        escapeHtml: escapeHtml,
    };
})();
