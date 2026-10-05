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

    function renderResults(container, items, onSelect) {
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
            items.map(function (item) {
                var swatch = item.swatchStyle
                    ? '<span class="admin-home-product-search__swatch" style="' + escapeHtml(item.swatchStyle) + '"></span>'
                    : '<span class="admin-home-product-search__swatch admin-home-product-search__swatch--empty"></span>';

                return (
                    '<button type="button" class="admin-home-product-search__item" data-product-id="' + item.id + '">' +
                        swatch +
                        '<span class="admin-home-product-search__item-body">' +
                            '<span class="admin-home-product-search__item-title">' + escapeHtml(item.title) + '</span>' +
                            (item.productTitle
                                ? '<span class="admin-home-product-search__item-meta">' + escapeHtml(item.productTitle) + '</span>'
                                : '') +
                        '</span>' +
                    '</button>'
                );
            }).join('');

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

    function initProductPicker(picker) {
        if (!picker) {
            return;
        }
        if (picker.dataset.homeProductInit === '1') {
            return;
        }
        picker.dataset.homeProductInit = '1';

        var searchUrl = picker.dataset.searchUrl;
        var searchInput = picker.querySelector('[data-product-search-input]');
        var productIdInput = picker.querySelector('[data-product-id-input]');
        var results = picker.querySelector('[data-product-search-results]');
        var debounceTimer = null;

        if (!searchUrl || !searchInput || !productIdInput) {
            return;
        }

        function selectProduct(data) {
            productIdInput.value = data.id ? String(data.id) : '';
            searchInput.value = data.productTitle || data.title || '';
        }

        function runSearch(query) {
            if (query.length < MIN_QUERY_LENGTH) {
                if (results) {
                    results.hidden = true;
                }
                return;
            }

            var params = { q: query };
            if (picker.dataset.directionId) {
                params.direction_id = picker.dataset.directionId;
            }
            if (picker.dataset.categoryId) {
                params.category_id = picker.dataset.categoryId;
            }
            if (picker.dataset.subcategoryId) {
                params.subcategory_id = picker.dataset.subcategoryId;
            }
            if (picker.dataset.modelIdSelector) {
                var modelEl = document.querySelector(picker.dataset.modelIdSelector);
                if (modelEl && modelEl.value) {
                    params.model_id = modelEl.value;
                }
            }

            fetchPickerData(searchUrl, params)
                .then(function (payload) {
                    var items = Array.isArray(payload) ? payload : [];
                    renderResults(results, items, selectProduct);
                })
                .catch(function () {
                    if (results) {
                        results.innerHTML = '<div class="admin-home-product-search__empty">Ошибка поиска</div>';
                        results.hidden = false;
                    }
                });
        }

        searchInput.addEventListener('input', function () {
            if (searchInput.value.trim() === '') {
                productIdInput.value = '';
            }
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

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target) && results) {
                results.hidden = true;
            }
        });
    }

    function initHomeProducts(root) {
        root.querySelectorAll('[data-home-product-picker], [data-product-picker]').forEach(initProductPicker);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initHomeProducts(document);
    });

    window.adminInitProductPickers = function (root) {
        initHomeProducts(root || document);
    };
})();
