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

    /** После lazy-load блока ткани восстановить галочку по скрытым полям формы. */
    function resolveActiveVariant(root) {
        var fromDom = activeVariant(root);
        if (fromDom) {
            return fromDom;
        }

        var productHidden = priorityProductInput(root);
        var productId = productHidden ? String(productHidden.value || '').trim() : '';
        if (productId === '') {
            return null;
        }

        var match = variantRows(root).find(function (row) {
            return String(row.getAttribute('data-product-id') || '') === productId;
        });
        if (!match) {
            return null;
        }

        root.querySelectorAll('[data-search-priority-check]').forEach(function (input) {
            input.checked = input.closest('[data-search-priority-variant]') === match;
        });

        return match;
    }

    function syncHiddenFields(root, clearWhenInactive) {
        var productHidden = priorityProductInput(root);
        var sortHidden = prioritySortInput(root);
        var active = activeVariant(root);

        if (!productHidden || !sortHidden) {
            return;
        }

        if (!active) {
            if (clearWhenInactive) {
                productHidden.value = '';
                sortHidden.value = '';
            }
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

    function syncVariantUi(root, activeRow, clearWhenInactive) {
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
        syncHiddenFields(root, clearWhenInactive === true);
    }

    function initRoot(root) {
        if (!root || root.dataset.searchPriorityInit === '1') {
            return;
        }
        root.dataset.searchPriorityInit = '1';

        syncVariantUi(root, resolveActiveVariant(root), false);

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
                    syncVariantUi(root, row, false);
                } else {
                    syncVariantUi(root, null, true);
                }
                return;
            }

            if (target.matches('[data-search-priority-sort]')) {
                syncHiddenFields(root, false);
            }
        });

        root.addEventListener('input', function (event) {
            var target = event.target;
            if (target instanceof HTMLInputElement && target.matches('[data-search-priority-sort]')) {
                syncHiddenFields(root, false);
            }
        });

        var form = root.closest('form');
        if (form && !form.dataset.searchPrioritySubmitBound) {
            form.dataset.searchPrioritySubmitBound = '1';
            form.addEventListener('submit', function () {
                syncVariantUi(root, resolveActiveVariant(root), false);
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-model-fabrics]').forEach(initRoot);
    });

    window.adminModelSearchPriorityResync = function (root) {
        if (!root) {
            return;
        }
        syncVariantUi(root, resolveActiveVariant(root), false);
    };
})();
