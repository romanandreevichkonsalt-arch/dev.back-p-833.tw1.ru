(function () {
    function isGroupLinked(group) {
        return group.querySelector('[data-fabric-link-input]') !== null;
    }

    function setGroupLinked(group, linked) {
        group.classList.toggle('is-active', linked);

        group.querySelectorAll(
            '.admin-model-fabrics__body input, '
            + '.admin-model-fabrics__body select, '
            + '.admin-model-fabrics__body textarea, '
            + '.admin-model-fabrics__body button:not(.admin-media-picker__listing-tile-btn)'
        ).forEach(function (input) {
            if (linked) {
                input.removeAttribute('disabled');
            } else {
                input.setAttribute('disabled', 'disabled');
            }
        });
    }

    function setGroupExpanded(group, expanded) {
        group.classList.toggle('is-collapsed', !expanded);
        var toggle = group.querySelector('[data-fabric-toggle]');
        var body = group.querySelector('[data-fabric-body]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        if (body) {
            body.hidden = !expanded;
        }
    }

    function buildFabricBodyUrl(section, fabricId) {
        var base = section.getAttribute('data-fabric-body-url') || '';
        var modelId = section.getAttribute('data-model-id') || '0';
        if (!base || modelId === '0' || !fabricId) {
            return '';
        }
        var separator = base.indexOf('?') >= 0 ? '&' : '?';

        return base + separator + 'id=' + encodeURIComponent(modelId)
            + '&fabric_id=' + encodeURIComponent(String(fabricId));
    }

    function initLazyGroupContent(group, section) {
        setGroupLinked(group, isGroupLinked(group));
        group.querySelectorAll('.admin-media-picker').forEach(function (picker) {
            if (window.adminMediaPickerInit) {
                window.adminMediaPickerInit(picker);
            }
        });
        if (window.adminModelSearchPriorityResync) {
            window.adminModelSearchPriorityResync(section);
        }
        updateSkuPreviews(section);
    }

    function loadFabricGroupBody(group, section) {
        if (group.getAttribute('data-fabric-body-loaded') === '1') {
            return Promise.resolve();
        }
        if (group.getAttribute('data-fabric-lazy') !== '1') {
            return Promise.resolve();
        }

        var body = group.querySelector('[data-fabric-body]');
        var url = buildFabricBodyUrl(section, group.getAttribute('data-fabric-id'));
        if (!body || !url) {
            return Promise.resolve();
        }

        body.hidden = false;
        body.innerHTML = '<p class="admin-muted admin-model-fabrics__loading">Загрузка…</p>';

        return fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.message || 'Не удалось загрузить SKU.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                body.innerHTML = payload.html || '';
                group.setAttribute('data-fabric-body-loaded', '1');
                initLazyGroupContent(group, section);
            })
            .catch(function (error) {
                body.innerHTML = '<p class="admin-muted admin-model-fabrics__load-error">'
                    + String(error.message || 'Ошибка загрузки.') + '</p>';
            });
    }

    function toggleFabricGroup(group, section) {
        if (group.classList.contains('is-collapsed')) {
            setGroupExpanded(group, true);
            return loadFabricGroupBody(group, section);
        }

        setGroupExpanded(group, false);
        return Promise.resolve();
    }

    function buildSelectLabel(name, texture) {
        var label = String(name || '').trim();
        var textureValue = String(texture || '').trim();
        if (textureValue !== '') {
            label += ' (' + textureValue + ')';
        }

        return label;
    }

    function updateEmptyState(section) {
        var emptyState = section.querySelector('[data-fabric-empty-state]');
        var list = section.querySelector('[data-fabric-list]');
        if (!emptyState || !list) {
            return;
        }

        emptyState.hidden = list.children.length > 0;
    }

    function updateAddSelectState(section) {
        var select = section.querySelector('[data-fabric-add-select]');
        var addButton = section.querySelector('[data-fabric-add]');
        if (!select || !addButton) {
            return;
        }

        addButton.disabled = select.value === '';
    }

    function removeSelectOption(select, fabricId) {
        if (!select) {
            return;
        }

        var option = select.querySelector('option[value="' + fabricId + '"]');
        if (option) {
            option.remove();
        }

        select.value = '';
    }

    function addSelectOption(select, group) {
        if (!select) {
            return;
        }

        var fabricId = group.getAttribute('data-fabric-id');
        if (!fabricId || select.querySelector('option[value="' + fabricId + '"]')) {
            return;
        }

        var option = document.createElement('option');
        option.value = fabricId;
        option.textContent = buildSelectLabel(
            group.getAttribute('data-fabric-name'),
            group.getAttribute('data-fabric-texture')
        );
        select.appendChild(option);

        var options = Array.prototype.slice.call(select.options, 1);
        options.sort(function (a, b) {
            return a.textContent.localeCompare(b.textContent, 'ru');
        });
        options.forEach(function (sortedOption) {
            select.appendChild(sortedOption);
        });
    }

    function ensureRemoveButton(group) {
        var head = group.querySelector('.admin-model-fabrics__head');
        if (!head || head.querySelector('[data-fabric-remove]')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'admin-btn admin-btn--secondary admin-btn--sm';
        button.setAttribute('data-fabric-remove', '');
        button.textContent = 'Убрать';
        head.appendChild(button);
        button.addEventListener('click', function () {
            unlinkFabricGroup(group);
        });
    }

    function ensureLinkInput(group) {
        var head = group.querySelector('.admin-model-fabrics__head');
        if (!head) {
            return null;
        }

        var input = head.querySelector('[data-fabric-link-input]');
        if (input) {
            return input;
        }

        input = document.createElement('input');
        input.type = 'hidden';
        input.className = 'admin-model-fabrics__link-input';
        input.name = 'fabric_collection_ids[]';
        input.value = group.getAttribute('data-fabric-id') || '';
        input.setAttribute('data-fabric-link-input', '');
        head.insertBefore(input, head.querySelector('[data-fabric-remove]'));
        return input;
    }

    function unlinkFabricGroup(group) {
        var section = group.closest('[data-model-fabrics]');
        var list = section ? section.querySelector('[data-fabric-list]') : null;
        var pool = section ? section.querySelector('[data-fabric-pool]') : null;
        var select = section ? section.querySelector('[data-fabric-add-select]') : null;
        if (!section || !list || !pool) {
            return;
        }

        var linkInput = group.querySelector('[data-fabric-link-input]');
        if (linkInput) {
            linkInput.remove();
        }

        var removeButton = group.querySelector('[data-fabric-remove]');
        if (removeButton) {
            removeButton.remove();
        }

        setGroupLinked(group, false);
        if (group.getAttribute('data-fabric-lazy') === '1') {
            setGroupExpanded(group, false);
        }
        pool.appendChild(group);
        addSelectOption(select, group);
        updateEmptyState(section);
        updateAddSelectState(section);
        updateSkuPreviews(section);
    }

    function linkFabricGroup(group) {
        var section = group.closest('[data-model-fabrics]');
        var list = section ? section.querySelector('[data-fabric-list]') : null;
        var select = section ? section.querySelector('[data-fabric-add-select]') : null;
        if (!section || !list) {
            return;
        }

        ensureLinkInput(group);
        ensureRemoveButton(group);
        setGroupLinked(group, true);
        if (group.getAttribute('data-fabric-lazy') === '1') {
            setGroupExpanded(group, false);
        }
        list.appendChild(group);
        removeSelectOption(select, group.getAttribute('data-fabric-id'));
        updateEmptyState(section);
        updateAddSelectState(section);
        updateSkuPreviews(section);
    }

    function initRemoveButtons(section) {
        section.querySelectorAll('[data-fabric-remove]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var group = button.closest('[data-fabric-group]');
                if (group) {
                    unlinkFabricGroup(group);
                }
            });
        });
    }

    function initFabricToggles(section) {
        section.querySelectorAll('[data-fabric-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var group = button.closest('[data-fabric-group]');
                if (!group || button.disabled) {
                    return;
                }
                toggleFabricGroup(group, section);
            });
        });

        section.querySelectorAll('[data-fabric-group]').forEach(function (group) {
            if (group.getAttribute('data-fabric-lazy') === '1') {
                setGroupExpanded(group, !group.classList.contains('is-collapsed'));
                return;
            }
            setGroupExpanded(group, true);
        });
    }

    function initAddPanel(section) {
        var select = section.querySelector('[data-fabric-add-select]');
        var addButton = section.querySelector('[data-fabric-add]');
        var pool = section.querySelector('[data-fabric-pool]');
        if (!select || !addButton || !pool) {
            return;
        }

        select.addEventListener('change', function () {
            updateAddSelectState(section);
        });

        addButton.addEventListener('click', function () {
            var fabricId = select.value;
            if (fabricId === '') {
                return;
            }

            var group = pool.querySelector('[data-fabric-group][data-fabric-id="' + fabricId + '"]');
            if (group) {
                linkFabricGroup(group);
            }
        });

        updateAddSelectState(section);
    }

    function slugify(text) {
        if (window.adminSlugify) {
            return window.adminSlugify(text);
        }

        return String(text || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 64);
    }

    function buildProductTitle(typeLabel, collectionName, colorLabel, fabricCollectionName, designCode) {
        return [typeLabel, collectionName, colorLabel, fabricCollectionName, designCode]
            .map(function (part) {
                return String(part || '').trim();
            })
            .filter(function (part) {
                return part !== '';
            })
            .join(' ');
    }

    function buildProductSlug(typeLabel, collectionName, colorLabel, fabricCollectionName, designCode) {
        return slugify(buildProductTitle(typeLabel, collectionName, colorLabel, fabricCollectionName, designCode));
    }

    function getModelTitleInput(root) {
        var form = root.closest('form');
        if (!form) {
            return null;
        }

        return form.querySelector('[data-catalog-model-title]')
            || form.querySelector('[name="CatalogModel[title]"]');
    }

    function getModelSlugInput(root) {
        var form = root.closest('form');
        if (!form) {
            return null;
        }

        return form.querySelector('[name="CatalogModel[slug]"]');
    }

    function updateSkuPreviews(section) {
        if (!section) {
            return;
        }

        var titleInput = getModelTitleInput(section);
        var slugInput = getModelSlugInput(section);
        var typeLabel = section.getAttribute('data-type-label') || '';
        var collectionName = section.getAttribute('data-collection-name') || '';

        section.querySelectorAll('[data-fabric-color-row]').forEach(function (row) {
            var skuBlock = row.querySelector('[data-sku-preview]');
            if (!skuBlock) {
                return;
            }

            var group = row.closest('[data-fabric-group]');
            if (!group || !isGroupLinked(group)) {
                skuBlock.querySelector('.admin-model-fabrics__sku-title').textContent = '—';
                skuBlock.querySelector('.admin-model-fabrics__sku-slug').textContent = '—';
                return;
            }

            var colorLabel = row.getAttribute('data-color-label') || '';
            var fabricCollectionName = group.getAttribute('data-fabric-name') || '';
            var designCode = row.getAttribute('data-fabric-design-code') || '';
            var titleNode = skuBlock.querySelector('.admin-model-fabrics__sku-title');
            var slugNode = skuBlock.querySelector('.admin-model-fabrics__sku-slug');
            var title = buildProductTitle(typeLabel, collectionName, colorLabel, fabricCollectionName, designCode);
            var slug = buildProductSlug(typeLabel, collectionName, colorLabel, fabricCollectionName, designCode);

            titleNode.textContent = title !== '' ? title : '—';
            slugNode.textContent = slug !== '' ? slug : '—';
        });
    }

    function initSkuPreview(section) {
        var titleInput = getModelTitleInput(section);
        var slugInput = getModelSlugInput(section);
        var refresh = function () {
            updateSkuPreviews(section);
        };

        if (titleInput) {
            titleInput.addEventListener('input', refresh);
            titleInput.addEventListener('change', refresh);
        }

        if (slugInput) {
            slugInput.addEventListener('input', refresh);
            slugInput.addEventListener('change', refresh);
        }

        refresh();
    }

    function syncFabricGroupControlState(section) {
        section.querySelectorAll('[data-fabric-list] [data-fabric-group]').forEach(function (group) {
            setGroupLinked(group, isGroupLinked(group));
        });
        section.querySelectorAll('[data-fabric-pool] [data-fabric-group]').forEach(function (group) {
            setGroupLinked(group, false);
        });
    }

    function initSection(section) {
        initRemoveButtons(section);
        initFabricToggles(section);
        initAddPanel(section);
        initSkuPreview(section);
        syncFabricGroupControlState(section);
        updateEmptyState(section);
    }

    document.querySelectorAll('[data-model-fabrics]').forEach(initSection);
})();
