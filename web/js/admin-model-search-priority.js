(function () {
    function priorityProductInput(root) {
        return root.querySelector('[data-search-priority-product-input]');
    }

    function prioritySortInput(root) {
        return root.querySelector('[data-search-priority-sort-input]');
    }

    function variantRows(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-search-priority-variant]'));
    }

    function defaultSortOrder(root) {
        var nextSort = parseInt(root.getAttribute('data-search-priority-next-sort') || '1', 10);
        return isNaN(nextSort) || nextSort < 1 ? 1 : nextSort;
    }

    function activeVariant(root) {
        return (
            variantRows(root).find(function (row) {
                var checkbox = row.querySelector('[data-search-priority-check]');
                return checkbox && checkbox.checked;
            }) || null
        );
    }

    function syncHiddenFields(root) {
        var productHidden = priorityProductInput(root);
        var sortHidden = prioritySortInput(root);
        var active = activeVariant(root);

        if (!productHidden || !sortHidden) {
            return;
        }

        if (!active) {
            productHidden.value = '';
            sortHidden.value = '';
            return;
        }

        var checkbox = active.querySelector('[data-search-priority-check]');
        var sortInput = active.querySelector('[data-search-priority-sort]');
        productHidden.value = checkbox ? checkbox.value : '';

        if (sortInput && sortInput.value !== '') {
            sortHidden.value = sortInput.value;
        } else if (sortHidden.value === '') {
            sortInput.value = String(defaultSortOrder(root));
            sortHidden.value = sortInput.value;
        }
    }

    function syncVariantUi(root, activeRow) {
        variantRows(root).forEach(function (row) {
            var sortInput = row.querySelector('[data-search-priority-sort]');
            if (!sortInput) {
                return;
            }

            var isActive = row === activeRow;
            sortInput.disabled = !isActive;
            if (!isActive) {
                sortInput.value = '';
            } else if (sortInput.value === '') {
                var hidden = prioritySortInput(root);
                if (hidden && hidden.value !== '') {
                    sortInput.value = hidden.value;
                } else {
                    sortInput.value = String(defaultSortOrder(root));
                }
            }
        });
        syncHiddenFields(root);
    }

    function initRoot(root) {
        if (!root || root.dataset.searchPriorityInit === '1') {
            return;
        }
        root.dataset.searchPriorityInit = '1';

        syncVariantUi(root, activeVariant(root));

        root.addEventListener('change', function (event) {
            var target = event.target;
            if (!(target instanceof HTMLInputElement)) {
                return;
            }

            if (target.matches('[data-search-priority-check]')) {
                var row = target.closest('[data-search-priority-variant]');
                if (target.checked) {
                    root.querySelectorAll('[data-search-priority-check]').forEach(function (input) {
                        if (input !== target) {
                            input.checked = false;
                        }
                    });
                    syncVariantUi(root, row);
                } else {
                    syncVariantUi(root, null);
                }
                return;
            }

            if (target.matches('[data-search-priority-sort]')) {
                syncHiddenFields(root);
            }
        });

        root.addEventListener('input', function (event) {
            var target = event.target;
            if (target instanceof HTMLInputElement && target.matches('[data-search-priority-sort]')) {
                syncHiddenFields(root);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-model-fabrics]').forEach(initRoot);
    });

    window.adminModelSearchPriorityResync = function (root) {
        if (!root) {
            return;
        }
        syncVariantUi(root, activeVariant(root));
    };
})();
