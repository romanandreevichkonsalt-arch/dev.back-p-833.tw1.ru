(function () {
    function isSofaSlug(slug) {
        return slug === 'sofa' || slug === 'divan';
    }

    function isArmchairSlug(slug) {
        return slug === 'armchair' || slug === 'kreslo';
    }

    function getCategorySlug(categorySelect) {
        if (!categorySelect || !categorySelect.selectedOptions.length) {
            return '';
        }

        var option = categorySelect.selectedOptions[0];
        return option.dataset.slug || '';
    }

    function filterCheckbox(root, attribute) {
        return root.querySelector('input[type="checkbox"][name="CatalogModel[' + attribute + ']"]');
    }

    function isSizeApplicable(root) {
        var sleepingCheckbox = filterCheckbox(root, 'has_sleeping_place');
        var foldableCheckbox = filterCheckbox(root, 'is_foldable');
        var widthInput = root.querySelector('[name="CatalogModel[sleeping_place_width_mm]"]');
        var depthInput = root.querySelector('[name="CatalogModel[sleeping_place_depth_mm]"]');

        if ((sleepingCheckbox && sleepingCheckbox.checked) || (foldableCheckbox && foldableCheckbox.checked)) {
            return true;
        }

        return Boolean(
            (widthInput && widthInput.value !== '')
            || (depthInput && depthInput.value !== '')
        );
    }

    function syncFilterFields(root) {
        var fieldsRoot = root.querySelector('[data-model-filter-fields]') || root;
        var categorySelect = root.querySelector('[data-catalog-category]');
        var slug = getCategorySlug(categorySelect);
        var sofaGroup = fieldsRoot.querySelector('[data-filter-group="sofa"]');
        var armchairGroup = fieldsRoot.querySelector('[data-filter-group="armchair"]');
        var sizeFields = fieldsRoot.querySelector('[data-filter-size-fields]');

        if (sofaGroup) {
            sofaGroup.hidden = !isSofaSlug(slug);
        }
        if (armchairGroup) {
            armchairGroup.hidden = !isArmchairSlug(slug);
        }

        if (!isSofaSlug(slug)) {
            var sleepingCheckbox = filterCheckbox(root, 'has_sleeping_place');
            if (sleepingCheckbox) {
                sleepingCheckbox.checked = false;
            }
        }

        if (!isArmchairSlug(slug)) {
            var foldableCheckbox = filterCheckbox(root, 'is_foldable');
            if (foldableCheckbox) {
                foldableCheckbox.checked = false;
            }
        }

        if (sizeFields) {
            var showSize = (isSofaSlug(slug) || isArmchairSlug(slug)) && isSizeApplicable(root);
            sizeFields.hidden = !showSize;
        }
    }

    function initFilterFields(fieldsRoot) {
        if (!fieldsRoot || fieldsRoot.dataset.filterFieldsInitialized === '1') {
            return;
        }
        fieldsRoot.dataset.filterFieldsInitialized = '1';

        var root = fieldsRoot.closest('[data-catalog-cascade]') || fieldsRoot.closest('form') || document;
        var categorySelect = root.querySelector('[data-catalog-category]');
        if (categorySelect) {
            categorySelect.addEventListener('change', function () {
                syncFilterFields(root);
            });
        }

        ['has_sleeping_place', 'is_foldable'].forEach(function (attribute) {
            var checkbox = filterCheckbox(root, attribute);
            if (checkbox) {
                checkbox.addEventListener('change', function () {
                    syncFilterFields(root);
                });
            }
        });

        syncFilterFields(root);
    }

    document.querySelectorAll('[data-model-filter-fields]').forEach(initFilterFields);
})();
