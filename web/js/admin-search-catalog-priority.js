(function () {
    var MIN_QUERY_LENGTH = 3;
    var DEBOUNCE_MS = 300;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fetchPickerData(url, params) {
        var query = Object.keys(params)
            .filter(function (key) {
                return params[key] !== undefined && params[key] !== null && params[key] !== '';
            })
            .map(function (key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            })
            .join('&');

        return fetch(url + (query ? '?' + query : ''), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('search failed');
            }

            return response.json();
        });
    }

    function renderSearchResults(container, items, onSelect) {
        if (!container) {
            return;
        }

        if (!items || items.length === 0) {
            container.innerHTML = '<div class="admin-home-product-search__empty">Ничего не найдено</div>';
            container.hidden = false;
            return;
        }

        container.innerHTML =
            '<div class="admin-home-product-search__head">Цвет</div>' +
            items
                .map(function (item) {
                    var swatch = item.swatchStyle
                        ? '<span class="admin-home-product-search__swatch" style="' + escapeHtml(item.swatchStyle) + '"></span>'
                        : '<span class="admin-home-product-search__swatch admin-home-product-search__swatch--empty"></span>';

                    return (
                        '<button type="button" class="admin-home-product-search__item" data-product-id="' + item.id + '">' +
                        swatch +
                        '<span class="admin-home-product-search__item-body">' +
                        '<span class="admin-home-product-search__item-title">' +
                        escapeHtml(item.title) +
                        '</span>' +
                        (item.productTitle
                            ? '<span class="admin-home-product-search__item-meta">' + escapeHtml(item.productTitle) + '</span>'
                            : '') +
                        '</span>' +
                        '</button>'
                    );
                })
                .join('');

        container.hidden = false;

        container.querySelectorAll('[data-product-id]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = parseInt(button.getAttribute('data-product-id'), 10);
                var selected = items.find(function (item) {
                    return item.id === id;
                });
                if (selected) {
                    onSelect(selected);
                }
                container.hidden = true;
            });
        });
    }

    function panelRows(panel) {
        var list = panel.querySelector('[data-catalog-priority-list]');
        if (!list) {
            return [];
        }

        return Array.prototype.slice.call(list.querySelectorAll('[data-catalog-priority-row]'));
    }

    function findRowByModelId(panel, modelId) {
        return (
            panelRows(panel).find(function (row) {
                return parseInt(row.getAttribute('data-model-id') || '0', 10) === modelId;
            }) || null
        );
    }

    function nextSortOrder(panel) {
        var max = 0;
        panelRows(panel).forEach(function (row) {
            var input = row.querySelector('[data-catalog-sort-input]');
            if (!input) {
                return;
            }
            var value = parseInt(input.value, 10);
            if (!isNaN(value) && value > max) {
                max = value;
            }
        });

        return max > 0 ? max + 1 : 1;
    }

    function updateEmptyState(panel) {
        var empty = panel.querySelector('[data-catalog-priority-empty]');
        if (!empty) {
            return;
        }
        empty.hidden = panelRows(panel).length > 0;
    }

    function reindexPanel(panel) {
        var directionId = panel.getAttribute('data-direction-id');
        panelRows(panel).forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(
                    /catalog_priority\[\d+\]\[\d+\]/,
                    'catalog_priority[' + directionId + '][' + index + ']'
                );
            });
        });
        updateEmptyState(panel);
    }

    function showNotice(panel, message) {
        var notice = panel.querySelector('[data-catalog-priority-notice]');
        if (!notice) {
            return;
        }
        notice.textContent = message;
        notice.hidden = false;
        window.clearTimeout(notice._hideTimer);
        notice._hideTimer = window.setTimeout(function () {
            notice.hidden = true;
        }, 3200);
    }

    function applyProductToRow(row, item) {
        var modelId = parseInt(item.modelId, 10) || 0;
        var productId = parseInt(item.id, 10) || 0;
        if (modelId <= 0) {
            return false;
        }

        row.setAttribute('data-model-id', String(modelId));

        var modelInput = row.querySelector('[data-catalog-model-id-input]');
        if (modelInput) {
            modelInput.value = String(modelId);
        }

        var productInput = row.querySelector('[data-catalog-product-id-input]');
        if (productInput) {
            productInput.value = productId > 0 ? String(productId) : '';
        }

        var modelTitle = item.previewName || item.productTitle || item.title || 'Модель #' + modelId;
        var titleNode = row.querySelector('.admin-catalog-priority-row__title');
        if (titleNode) {
            titleNode.textContent = modelTitle;
        }

        var metaNodes = row.querySelectorAll('.admin-catalog-priority-row__meta');
        if (metaNodes.length > 0) {
            metaNodes[0].textContent = item.productTitle || item.title || '';
        }
        if (metaNodes.length > 1 && item.collection) {
            metaNodes[1].textContent = item.collection;
        }

        var swatch = row.querySelector('.admin-home-product-search__swatch');
        if (swatch) {
            if (item.swatchStyle) {
                swatch.style.cssText = item.swatchStyle;
                swatch.classList.remove('admin-home-product-search__swatch--empty');
            } else {
                swatch.removeAttribute('style');
                swatch.classList.add('admin-home-product-search__swatch--empty');
            }
        }

        var viewLink = row.querySelector('[data-catalog-model-view]');
        if (viewLink) {
            viewLink.setAttribute('href', '/admin/catalog-model/update?id=' + modelId);
        }

        return true;
    }

    function fillTemplateRow(row, item, sortOrder) {
        applyProductToRow(row, item);

        var sortInput = row.querySelector('[data-catalog-sort-input]');
        if (sortInput) {
            sortInput.value = String(sortOrder);
        }
    }

    function buildRowFromTemplate(panel, item) {
        var template = panel.querySelector('[data-catalog-priority-row-template]');
        var list = panel.querySelector('[data-catalog-priority-list]');
        if (!template || !list || !template.content) {
            return null;
        }

        var modelId = parseInt(item.modelId, 10) || 0;
        if (modelId <= 0) {
            return null;
        }

        var sortOrder = nextSortOrder(panel);
        var clone = template.content.cloneNode(true);
        var row = clone.querySelector('[data-catalog-priority-row]');
        if (!row) {
            return null;
        }

        fillTemplateRow(row, item, sortOrder);
        list.appendChild(row);
        bindRowRemove(row, panel);
        reindexPanel(panel);

        return row;
    }

    function bindRowRemove(row, panel) {
        var removeBtn = row.querySelector('[data-catalog-priority-remove]');
        if (!removeBtn || removeBtn.dataset.bound === '1') {
            return;
        }
        removeBtn.dataset.bound = '1';
        removeBtn.addEventListener('click', function () {
            row.remove();
            reindexPanel(panel);
        });
    }

    function handleProductSelect(panel, searchInput, selected) {
        if (searchInput) {
            searchInput.value = '';
        }

        var modelId = parseInt(selected.modelId, 10) || 0;
        if (modelId <= 0) {
            showNotice(panel, 'Не удалось определить модель для выбранного товара.');
            return;
        }

        var existingRow = findRowByModelId(panel, modelId);
        if (existingRow) {
            applyProductToRow(existingRow, selected);
            showNotice(panel, 'Вариант товара для модели обновлён.');
            return;
        }

        buildRowFromTemplate(panel, selected);
        showNotice(panel, 'Модель добавлена в список.');
    }

    function initSearch(panel) {
        var searchRoot = panel.querySelector('[data-catalog-priority-search]');
        if (!searchRoot || searchRoot.dataset.catalogPrioritySearchInit === '1') {
            return;
        }
        searchRoot.dataset.catalogPrioritySearchInit = '1';

        var searchUrl = panel.getAttribute('data-search-url');
        var directionId = panel.getAttribute('data-direction-id');
        var searchInput = searchRoot.querySelector('[data-product-search-input]');
        var results = searchRoot.querySelector('[data-product-search-results]');
        var debounceTimer = null;

        if (!searchUrl || !searchInput) {
            return;
        }

        function runSearch(query) {
            if (query.length < MIN_QUERY_LENGTH) {
                if (results) {
                    results.hidden = true;
                }
                return;
            }

            fetchPickerData(searchUrl, { q: query, direction_id: directionId })
                .then(function (payload) {
                    var items = Array.isArray(payload) ? payload : [];
                    renderSearchResults(results, items, function (selected) {
                        handleProductSelect(panel, searchInput, selected);
                    });
                })
                .catch(function () {
                    if (results) {
                        results.innerHTML = '<div class="admin-home-product-search__empty">Ошибка поиска</div>';
                        results.hidden = false;
                    }
                });
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                runSearch(searchInput.value.trim());
            }, DEBOUNCE_MS);
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        searchInput.addEventListener('focus', function () {
            var query = searchInput.value.trim();
            if (query.length >= MIN_QUERY_LENGTH) {
                runSearch(query);
            }
        });

        searchRoot.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function (event) {
            if (!searchRoot.contains(event.target) && results) {
                results.hidden = true;
            }
        });
    }

    function initPanel(panel) {
        if (!panel || panel.dataset.catalogPriorityInit === '1') {
            return;
        }
        panel.dataset.catalogPriorityInit = '1';

        panelRows(panel).forEach(function (row) {
            bindRowRemove(row, panel);
        });
        updateEmptyState(panel);
        initSearch(panel);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-catalog-priority-panel]').forEach(initPanel);
    });
})();
