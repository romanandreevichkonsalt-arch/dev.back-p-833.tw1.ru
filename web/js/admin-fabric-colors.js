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

    function buildSwatchHtml(mediaId, previewUrl) {
        if (previewUrl) {
            return '<img class="admin-fabric-colors__thumb" src="' + previewUrl.replace(/"/g, '&quot;') + '" alt="">';
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
        } else {
            if (title) title.textContent = 'Добавить цвет';
            if (editKey) editKey.value = '';
            if (editId) editId.value = '';
            if (codeInput) codeInput.value = '';
            if (colorSelect) colorSelect.value = '';
            if (descriptionInput) descriptionInput.value = '';
            setPickerValue(modal, '', '');
        }

        modal.hidden = false;
        document.body.classList.add('admin-modal-open');
    }

    function closeModal(section) {
        var modal = section.querySelector('[data-fabric-color-modal]');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('admin-modal-open');
    }

    function upsertRow(section, payload) {
        var tbody = section.querySelector('[data-fabric-color-rows]');
        var template = document.getElementById('admin-fabric-color-row-template');
        var colorSelect = section.querySelector('[data-fabric-color-catalog-color]');
        if (!tbody || !template || !colorSelect) {
            return;
        }

        var existing = payload.rowKey ? tbody.querySelector('[data-row-key="' + payload.rowKey + '"]') : null;
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
            .replace(/__POSITION_NUMBER__/g, positionNumber.replace(/"/g, '&quot;'));

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

        if (existing) {
            existing.replaceWith(newRow);
        } else {
            tbody.appendChild(newRow);
        }

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
                var recommendedCheckbox = editRow?.querySelector('[name*="[is_recommended_fabric]"][type="checkbox"]');
                var positionInput = editRow?.querySelector('[name*="[position_number]"]');
                var description = modal.querySelector('[data-fabric-color-description]')?.value || '';

                upsertRow(section, {
                    rowKey: rowKey,
                    linkId: modal.querySelector('[data-fabric-color-edit-id]')?.value || '',
                    designCode: designCode,
                    colorId: colorId,
                    swatchMediaId: swatchMediaId,
                    swatchPreviewUrl: swatchPreviewUrl,
                    description: description,
                    isRecommendedFabric: recommendedCheckbox ? recommendedCheckbox.checked : false,
                    positionNumber: positionInput ? positionInput.value : '',
                });

                closeModal(section);
            });
        }

        updateEmptyState(section);
    }

    document.querySelectorAll('[data-fabric-color-manager]').forEach(initSection);
})();
