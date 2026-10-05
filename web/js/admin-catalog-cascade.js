(function () {
    function fillSelect(select, items, selectedId) {
        if (!select) {
            return;
        }
        var placeholder = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (placeholder) {
            select.appendChild(placeholder);
        } else {
            var empty = document.createElement('option');
            empty.value = '';
            empty.textContent = '—';
            select.appendChild(empty);
        }
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.label;
            if (item.name) {
                option.dataset.name = item.name;
            }
            if (item.label) {
                option.dataset.label = item.label;
            }
            if (selectedId && String(selectedId) === String(item.id)) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    function fetchOptions(url, params) {
        var query = new URLSearchParams(params).toString();
        return fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + query, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (response) {
            return response.json();
        });
    }

    function getSelectedMeta(select) {
        var option = select && select.selectedOptions ? select.selectedOptions[0] : null;
        if (!option || !option.value) {
            return null;
        }

        return {
            name: option.dataset.name || '',
            label: option.dataset.label || option.textContent || '',
        };
    }

    function syncSlugFromTitle(titleInput) {
        if (!titleInput || !titleInput.form) {
            return;
        }
        var slugInput = titleInput.form.elements['CatalogModel[slug]'];
        if (!slugInput) {
            return;
        }
        titleInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function initCascade(root) {
        if (root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        var url = root.getAttribute('data-cascade-url');
        var collectionSelect = root.querySelector('[data-catalog-collection]');
        var categorySelect = root.querySelector('[data-catalog-category]');
        var subcategorySelect = root.querySelector('[data-catalog-subcategory]');
        var titleInput = root.querySelector('[data-catalog-model-title]');

        if (!url || !collectionSelect || !categorySelect || !subcategorySelect) {
            return;
        }

        var titleTouched = titleInput ? titleInput.value.trim() !== '' : false;

        function updateTitleFromHierarchy() {
            var collection = getSelectedMeta(collectionSelect);
            var subcategory = getSelectedMeta(subcategorySelect);

            if (titleInput && !titleTouched && collection && subcategory) {
                var title = (subcategory.label + ' ' + collection.name).trim();
                if (title !== '') {
                    titleInput.value = title;
                    syncSlugFromTitle(titleInput);
                }
            }
        }

        if (titleInput) {
            titleInput.addEventListener('input', function () {
                titleTouched = titleInput.value.trim() !== '';
            });
        }

        collectionSelect.addEventListener('change', updateTitleFromHierarchy);
        subcategorySelect.addEventListener('change', updateTitleFromHierarchy);

        categorySelect.addEventListener('change', function () {
            var categoryId = categorySelect.value;
            fillSelect(subcategorySelect, [], null);
            if (!categoryId) {
                updateTitleFromHierarchy();
                return;
            }
            fetchOptions(url, { category_id: categoryId }).then(function (data) {
                fillSelect(subcategorySelect, data.subcategories || [], null);
                updateTitleFromHierarchy();
            });
        });

        updateTitleFromHierarchy();
        syncSlugFromTitle(titleInput);
    }

    document.querySelectorAll('[data-catalog-cascade]').forEach(initCascade);
})();
