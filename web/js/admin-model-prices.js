(function () {
    function getAllCategories(root) {
        try {
            return JSON.parse(root.getAttribute('data-all-categories') || '[]');
        } catch (error) {
            return [];
        }
    }

    function setAllCategories(root, categories) {
        root.setAttribute('data-all-categories', JSON.stringify(categories));
    }

    function registerCategory(root, category) {
        var allCategories = getAllCategories(root);
        if (!allCategories.some(function (item) {
            return String(item.id) === String(category.id);
        })) {
            allCategories.push({
                id: Number(category.id),
                label: String(category.label),
                number: Number(category.number || 0),
            });
            setAllCategories(root, allCategories);
        }
    }

    function getVisibleCategoryIds(root) {
        return Array.prototype.map.call(
            root.querySelectorAll('[data-admin-model-prices-item]'),
            function (item) {
                return String(item.getAttribute('data-category-id') || '');
            }
        );
    }

    function getVisibleMaxNumber(root) {
        var maxNumber = 0;
        root.querySelectorAll('[data-admin-model-prices-item]').forEach(function (item) {
            var number = parseInt(item.getAttribute('data-category-number') || '0', 10);
            if (number > maxNumber) {
                maxNumber = number;
            }
        });
        return maxNumber;
    }

    function getNextCategoryLabel(root) {
        return 'Категория ' + (getVisibleMaxNumber(root) + 1);
    }

    function updateAddButtonTitle(root) {
        var addButton = root.querySelector('[data-admin-model-prices-add]');
        if (!addButton) {
            return;
        }
        var nextLabel = getNextCategoryLabel(root);
        addButton.title = 'Добавить ' + nextLabel;
        addButton.setAttribute('aria-label', 'Добавить ' + nextLabel);
    }

    function setError(root, message) {
        var errorEl = root.querySelector('[data-admin-model-prices-error]');
        if (!errorEl) {
            return;
        }
        errorEl.hidden = !message;
        errorEl.textContent = message || '';
    }

    function buildItemHtml(root, category) {
        var template = root.querySelector('[data-admin-model-prices-template]');
        if (!template) {
            return '';
        }

        return template.innerHTML
            .replace(/__ID__/g, String(category.id))
            .replace(/__NUMBER__/g, String(category.number || ''))
            .replace(/__LABEL__/g, category.label);
    }

    function addCategory(root, category) {
        var grid = root.querySelector('.admin-model-prices__grid');
        if (!grid || !category) {
            return false;
        }

        if (getVisibleCategoryIds(root).indexOf(String(category.id)) >= 0) {
            setError(root, 'Категория «' + category.label + '» уже добавлена.');
            return false;
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML = buildItemHtml(root, category).trim();
        var item = wrapper.firstElementChild;
        if (!item) {
            return false;
        }

        grid.appendChild(item);
        updateAddButtonTitle(root);

        var input = item.querySelector('input[type="text"]');
        if (input) {
            input.focus();
        }

        return true;
    }

    function createCategory(root) {
        var addButton = root.querySelector('[data-admin-model-prices-add]');
        var createUrl = root.getAttribute('data-create-url') || '';
        var modelId = root.getAttribute('data-model-id') || '0';
        var csrfParam = root.getAttribute('data-csrf-param') || '';
        var csrfToken = root.getAttribute('data-csrf-token') || '';

        if (!createUrl) {
            return;
        }

        if (addButton) {
            addButton.disabled = true;
        }
        setError(root, '');

        var body = new URLSearchParams();
        body.set('after_number', String(getVisibleMaxNumber(root)));
        getVisibleCategoryIds(root).forEach(function (categoryId) {
            if (categoryId !== '' && categoryId !== '0') {
                body.append('exclude_category_ids[]', categoryId);
            }
        });
        if (parseInt(modelId, 10) > 0) {
            body.set('model_id', modelId);
        }
        if (csrfParam && csrfToken) {
            body.set(csrfParam, csrfToken);
        }

        fetch(createUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body.toString(),
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    throw new Error(result.payload && result.payload.message ? result.payload.message : 'Не удалось создать категорию.');
                }

                var category = {
                    id: result.payload.id,
                    label: result.payload.label,
                    number: result.payload.number,
                };
                registerCategory(root, category);
                addCategory(root, category);
            })
            .catch(function (error) {
                setError(root, error.message || 'Не удалось создать категорию.');
            })
            .finally(function () {
                if (addButton) {
                    addButton.disabled = false;
                }
            });
    }

    function initPrices(root) {
        if (root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        root.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-admin-model-prices-remove]');
            if (removeButton) {
                var item = removeButton.closest('[data-admin-model-prices-item]');
                if (!item) {
                    return;
                }
                item.remove();
                updateAddButtonTitle(root);
                return;
            }

            if (event.target.closest('[data-admin-model-prices-add]')) {
                createCategory(root);
            }
        });

        updateAddButtonTitle(root);
    }

    document.querySelectorAll('[data-admin-model-prices]').forEach(initPrices);
})();
