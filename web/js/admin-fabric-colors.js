(function () {
    function normalizeName(value) {
        return String(value || '').trim();
    }

    function getColorLabel(select, colorId) {
        if (!colorId) {
            return '—';
        }
        var option = select.querySelector('option[value="' + colorId + '"]');
        return option ? option.textContent.trim() : '—';
    }

    function getPickerValue(modal) {
        var input = modal.querySelector('[data-fabric-color-modal-picker] .admin-media-picker__value');
        return input ? input.value : '';
    }

    function setPickerValue(modal, value, previewUrl) {
        var picker = modal.querySelector('[data-fabric-color-modal-picker] .admin-media-picker');
        if (!picker) {
            return;
        }
        var input = picker.querySelector('.admin-media-picker__value');
        var preview = picker.querySelector('.admin-media-picker__preview');
        if (input) {
            input.value = value || '';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (preview) {
            preview.classList.toggle('is-empty', !previewUrl);
            if (!previewUrl) {
                preview.innerHTML = '<span class="admin-media-picker__placeholder">Фото не выбрано</span>';
                return;
            }
            preview.innerHTML = '<img src="' + previewUrl.replace(/"/g, '&quot;') + '" alt="">';
        }
    }

    function getModalLibraryGallery(section) {
        var modal = section.querySelector('[data-fabric-color-modal]');
        if (!modal) {
            return null;
        }
        return modal.querySelector('#fabric-color-modal-library-gallery');
    }

    function ensureLibraryGalleryReady(section) {
        var gallery = getModalLibraryGallery(section);
        if (!gallery) {
            return null;
        }
        if (!gallery.__galleryApi && window.AdminProductGallery) {
            window.AdminProductGallery.init(gallery);
        }
        return gallery;
    }

    function closeNestedMediaLibraryModal(section) {
        var gallery = getModalLibraryGallery(section);
        if (!gallery) {
            return;
        }
        var mediaModal = document.getElementById('fabric-color-modal-library-gallery-modal')
            || gallery.querySelector('.admin-media-library__modal');
        if (mediaModal) {
            mediaModal.hidden = true;
        }
    }

    function parseLibraryPhotos(raw) {
        if (!raw) {
            return [];
        }
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function readLibraryPhotosFromRow(row) {
        if (!row) {
            return [];
        }
        var store = row.querySelector('[data-fabric-color-library-photos-store]');
        if (store && store.value) {
            return parseLibraryPhotos(store.value);
        }
        return parseLibraryPhotos(row.getAttribute('data-library-photos') || '[]');
    }

    function buildLibraryPhotosIndicatorHtml(count) {
        var total = parseInt(String(count || 0), 10);
        if (!total || total < 1) {
            return '<span class="admin-muted">—</span>';
        }
        return '<span class="admin-fabric-colors__library-flag" title="Доп. фото для library-fabrics: '
            + total + '">' + total + '</span>';
    }

    function updateLibraryPhotosIndicator(row, items) {
        if (!row) {
            return;
        }
        var cell = row.querySelector('[data-fabric-color-display-library-photos]');
        if (!cell) {
            return;
        }
        cell.innerHTML = buildLibraryPhotosIndicatorHtml((items || []).length);
    }

    function writeLibraryPhotosToRow(row, rowKey, items) {
        if (!row) {
            return;
        }
        var normalized = (items || []).map(function (item) {
            return {
                linkId: item.linkId || null,
                mediaId: item.mediaId,
                url: item.url,
                alt: item.alt,
                filename: item.filename,
            };
        });
        var json = JSON.stringify(normalized);
        row.setAttribute('data-library-photos', json);
        var store = row.querySelector('[data-fabric-color-library-photos-store]');
        if (store) {
            store.value = json;
        }
        var inputsHost = row.querySelector('[data-fabric-color-library-photo-inputs]');
        if (!inputsHost) {
            return;
        }
        inputsHost.innerHTML = '';
        normalized.forEach(function (item) {
            if (!item.mediaId) {
                return;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'fabric_color_links[' + rowKey + '][library_photo_media_ids][]';
            input.value = String(item.mediaId);
            inputsHost.appendChild(input);
        });
        updateLibraryPhotosIndicator(row, normalized);
    }

    function setModalLibraryPhotos(section, items) {
        var gallery = ensureLibraryGalleryReady(section);
        if (gallery && gallery.__galleryApi) {
            gallery.__galleryApi.setItems(items || []);
        }
    }

    function getModalLibraryPhotos(section) {
        var gallery = ensureLibraryGalleryReady(section);
        if (gallery && gallery.__galleryApi) {
            return gallery.__galleryApi.getItems();
        }
        return [];
    }

    function buildSwatchHtml(mediaId, previewUrl) {
        if (previewUrl) {
            return '<img class="admin-fabric-colors__thumb" src="' + previewUrl.replace(/"/g, '&quot;') + '" alt="" loading="lazy" decoding="async">';
        }
        return mediaId ? '<span class="admin-muted">#'.concat(mediaId, '</span>') : '<span class="admin-muted">—</span>';
    }

    function updateEmptyState(section) {
        var empty = section.querySelector('[data-fabric-color-empty]');
        var rows = section.querySelectorAll('[data-fabric-color-row]');
        if (empty) {
            empty.classList.toggle('hidden', rows.length > 0);
        }
    }

    function openModal(section, row) {
        var modal = section.querySelector('[data-fabric-color-modal]');
        if (!modal) {
            return;
        }

        var title = modal.querySelector('[data-fabric-color-modal-title]');
        var editKey = modal.querySelector('[data-fabric-color-edit-key]');
        var editId = modal.querySelector('[data-fabric-color-edit-id]');
        var codeInput = modal.querySelector('[data-fabric-color-design-code]');
        var colorSelect = modal.querySelector('[data-fabric-color-catalog-color]');
        var descriptionInput = modal.querySelector('[data-fabric-color-description]');

        if (row) {
            if (title) title.textContent = 'Редактировать цвет';
            if (editKey) editKey.value = row.getAttribute('data-row-key') || '';
            if (editId) editId.value = row.getAttribute('data-link-id') || '';
            if (codeInput) codeInput.value = row.getAttribute('data-design-code') || '';
            if (colorSelect) colorSelect.value = row.getAttribute('data-color-id') || '';
            if (descriptionInput) {
                var storedDescription = row.querySelector('[name*="[description]"]');
                descriptionInput.value = storedDescription ? storedDescription.value : '';
            }
            setPickerValue(modal, row.getAttribute('data-swatch-media-id') || '', row.getAttribute('data-swatch-url') || '');
            setModalLibraryPhotos(section, readLibraryPhotosFromRow(row));
        } else {
            if (title) title.textContent = 'Добавить цвет';
            if (editKey) editKey.value = '';
            if (editId) editId.value = '';
            if (codeInput) codeInput.value = '';
            if (colorSelect) colorSelect.value = '';
            if (descriptionInput) descriptionInput.value = '';
            setPickerValue(modal, '', '');
            setModalLibraryPhotos(section, []);
        }

        ensureLibraryGalleryReady(section);
        modal.hidden = false;
        document.body.classList.add('admin-modal-open');
    }

    function closeModal(section) {
        var modal = section.querySelector('[data-fabric-color-modal]');
        if (!modal) {
            return;
        }
        closeNestedMediaLibraryModal(section);
        modal.hidden = true;
        if (!document.querySelector('.admin-modal:not([hidden])')) {
            document.body.classList.remove('admin-modal-open');
        }
    }

    function updateExistingRow(existing, payload, colorSelect) {
        var colorLabel = getColorLabel(colorSelect, payload.colorId);
        var swatchHtml = buildSwatchHtml(payload.swatchMediaId, payload.swatchPreviewUrl);

        existing.setAttribute('data-design-code', payload.designCode);
        existing.setAttribute('data-color-id', payload.colorId || '');
        existing.setAttribute('data-swatch-media-id', payload.swatchMediaId || '');
        existing.setAttribute('data-swatch-url', payload.swatchPreviewUrl || '');

        var codeDisplay = existing.querySelector('[data-fabric-color-display-code]');
        if (codeDisplay) {
            codeDisplay.textContent = payload.designCode;
        }
        var designInput = existing.querySelector('[name*="[design_code]"]');
        if (designInput) {
            designInput.value = payload.designCode;
        }
        var colorInput = existing.querySelector('[name*="[color_id]"]');
        if (colorInput) {
            colorInput.value = payload.colorId || '';
        }
        var swatchInput = existing.querySelector('[name*="[swatch_media_id]"]');
        if (swatchInput) {
            swatchInput.value = payload.swatchMediaId || '';
        }
        var labelCell = existing.querySelector('[data-fabric-color-display-label]');
        if (labelCell) {
            labelCell.textContent = colorLabel;
        }
        var swatchCell = existing.querySelector('[data-fabric-color-display-swatch]');
        if (swatchCell) {
            swatchCell.innerHTML = swatchHtml;
        }
        var descriptionField = existing.querySelector('[name*="[description]"]');
        if (descriptionField) {
            descriptionField.value = payload.description || '';
        }
        writeLibraryPhotosToRow(existing, payload.rowKey || existing.getAttribute('data-row-key') || '', payload.libraryPhotos || []);
    }

    function upsertRow(section, payload) {
        var tbody = section.querySelector('[data-fabric-color-rows]');
        var template = document.getElementById('admin-fabric-color-row-template');
        var colorSelect = section.querySelector('[data-fabric-color-catalog-color]');
        if (!tbody || !template || !colorSelect) {
            return;
        }

        var existing = payload.rowKey ? tbody.querySelector('[data-row-key="' + payload.rowKey + '"]') : null;
        if (existing) {
            updateExistingRow(existing, payload, colorSelect);
            updateEmptyState(section);
            return;
        }

        var rowKey = payload.rowKey || ('new_' + Date.now());
        var colorLabel = getColorLabel(colorSelect, payload.colorId);
        var swatchHtml = buildSwatchHtml(payload.swatchMediaId, payload.swatchPreviewUrl);
        var isRecommended = payload.isRecommendedFabric === true;
        var positionNumber = payload.positionNumber !== undefined && payload.positionNumber !== null
            ? String(payload.positionNumber)
            : '';

        var html = template.innerHTML
            .replace(/__ROW_KEY__/g, rowKey)
            .replace(/__LINK_ID__/g, payload.linkId || '')
            .replace(/__DESIGN_CODE__/g, payload.designCode)
            .replace(/__COLOR_ID__/g, payload.colorId || '')
            .replace(/__COLOR_LABEL__/g, colorLabel)
            .replace(/__SWATCH_MEDIA_ID__/g, payload.swatchMediaId || '')
            .replace(/__SWATCH_HTML__/g, swatchHtml)
            .replace(/__IS_RECOMMENDED_CHECKED__/g, isRecommended ? 'checked' : '')
            .replace(/__POSITION_NUMBER__/g, positionNumber.replace(/"/g, '&quot;'))
            .replace(
                /__LIBRARY_PHOTOS_INDICATOR__/g,
                buildLibraryPhotosIndicatorHtml((payload.libraryPhotos || []).length)
            );

        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        var newRow = wrapper.firstElementChild;
        if (!newRow) {
            return;
        }

        newRow.setAttribute('data-row-key', rowKey);
        newRow.setAttribute('data-design-code', payload.designCode);
        newRow.setAttribute('data-color-id', payload.colorId || '');
        newRow.setAttribute('data-swatch-media-id', payload.swatchMediaId || '');
        newRow.setAttribute('data-swatch-url', payload.swatchPreviewUrl || '');
        newRow.setAttribute('data-link-id', payload.linkId || '');

        var descriptionField = newRow.querySelector('[name*="[description]"]');
        if (descriptionField) {
            descriptionField.value = payload.description || '';
        }
        writeLibraryPhotosToRow(newRow, rowKey, payload.libraryPhotos || []);

        tbody.appendChild(newRow);

        updateEmptyState(section);
    }

    function initSection(section) {
        if (section.dataset.initialized === '1') {
            return;
        }
        section.dataset.initialized = '1';

        section.addEventListener('click', function (event) {
            if (event.target.closest('[data-fabric-color-open-modal]')) {
                openModal(section, null);
                return;
            }
            if (event.target.closest('[data-fabric-color-edit]')) {
                var editRow = event.target.closest('[data-fabric-color-row]');
                if (editRow) {
                    openModal(section, editRow);
                }
                return;
            }
            if (event.target.closest('[data-fabric-color-remove]')) {
                var removeRow = event.target.closest('[data-fabric-color-row]');
                if (removeRow) {
                    var designCode = removeRow.getAttribute('data-design-code') || 'цвет';
                    var productCount = parseInt(removeRow.getAttribute('data-product-count') || '0', 10);
                    var confirmMessage = productCount > 0
                        ? 'Удалить цвет «' + designCode + '»? Будут удалены связанные товары (SKU): '
                            + productCount + ' шт. Сохраните форму коллекции, чтобы применить изменения.'
                        : 'Удалить цвет «' + designCode + '»? Сохраните форму коллекции, чтобы применить изменения.';
                    if (!window.confirm(confirmMessage)) {
                        return;
                    }
                    removeRow.remove();
                    updateEmptyState(section);
                }
                return;
            }
            if (event.target.closest('[data-fabric-color-modal-close]')) {
                closeModal(section);
            }
        });

        var saveBtn = section.querySelector('[data-fabric-color-modal-save]');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                var modal = section.querySelector('[data-fabric-color-modal]');
                if (!modal) {
                    return;
                }

                var designCode = normalizeName(modal.querySelector('[data-fabric-color-design-code]')?.value || '');
                if (!designCode) {
                    window.alert('Укажите название цвета в коллекции.');
                    return;
                }

                var rowKey = modal.querySelector('[data-fabric-color-edit-key]')?.value || '';
                var tbody = section.querySelector('[data-fabric-color-rows]');
                var duplicate = Array.from(tbody?.querySelectorAll('[data-fabric-color-row]') || []).find(function (row) {
                    return row.getAttribute('data-design-code') === designCode && row.getAttribute('data-row-key') !== rowKey;
                });
                if (duplicate) {
                    window.alert('Название «' + designCode + '» уже есть в этой коллекции.');
                    return;
                }

                var colorId = modal.querySelector('[data-fabric-color-catalog-color]')?.value || '';
                var swatchMediaId = getPickerValue(modal);
                var previewImg = modal.querySelector('[data-fabric-color-modal-picker] .admin-media-picker__preview img');
                var swatchPreviewUrl = previewImg ? previewImg.getAttribute('src') : '';
                var editRow = rowKey ? tbody?.querySelector('[data-row-key="' + rowKey + '"]') : null;
                var description = modal.querySelector('[data-fabric-color-description]')?.value || '';
                var payload = {
                    rowKey: rowKey,
                    linkId: modal.querySelector('[data-fabric-color-edit-id]')?.value || '',
                    designCode: designCode,
                    colorId: colorId,
                    swatchMediaId: swatchMediaId,
                    swatchPreviewUrl: swatchPreviewUrl,
                    description: description,
                    libraryPhotos: getModalLibraryPhotos(section),
                };

                if (editRow) {
                    updateExistingRow(editRow, payload, section.querySelector('[data-fabric-color-catalog-color]'));
                    updateEmptyState(section);
                } else {
                    upsertRow(section, Object.assign(payload, {
                        isRecommendedFabric: false,
                        positionNumber: '',
                    }));
                }

                closeModal(section);
            });
        }

        updateEmptyState(section);
    }

    document.querySelectorAll('[data-fabric-color-manager]').forEach(initSection);
})();
