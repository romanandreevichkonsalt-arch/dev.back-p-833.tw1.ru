(function () {
    var typeLabels = {
        text: 'Текст',
        heading: 'Заголовок',
        image: 'Фото',
        quote: 'Цитата',
        divider: 'Разделитель',
        gallery: 'Галерея',
    };

    function initMediaPickers(root) {
        root.querySelectorAll('.admin-media-picker').forEach(function (picker) {
            if (window.adminMediaPickerInit) {
                window.adminMediaPickerInit(picker);
            }
        });
    }

    function resizeJournalText(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(textarea.scrollHeight, 40) + 'px';
    }

    function initJournalTextEditors(root) {
        if (!root) {
            return;
        }

        root.querySelectorAll('.admin-journal-text-editor').forEach(function (textarea) {
            if (textarea.dataset.journalTextInit === '1') {
                return;
            }
            textarea.dataset.journalTextInit = '1';
            resizeJournalText(textarea);
            textarea.addEventListener('input', function () {
                resizeJournalText(textarea);
            });
        });
    }

    function renumberBlocks(list) {
        if (!list) {
            return;
        }

        var blocks = list.querySelectorAll('[data-journal-block]');
        blocks.forEach(function (block, index) {
            block.dataset.itemIndex = String(index);

            var type = block.getAttribute('data-block-type') || 'text';
            var title = block.querySelector('[data-journal-block-title]');
            if (title) {
                var label = typeLabels[type] || type;
                title.textContent = 'Блок ' + (index + 1) + ' · ' + label;
            }

            block.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/blocks\[\d+\]/, 'blocks[' + index + ']');
                input.name = input.name.replace(/blocks\[__INDEX__\]/, 'blocks[' + index + ']');
            });

            block.querySelectorAll('[data-parent-index]').forEach(function (nested) {
                nested.dataset.parentIndex = String(index);
            });
        });
    }

    function moveBlock(list, block, direction) {
        var blocks = Array.prototype.slice.call(list.querySelectorAll('[data-journal-block]'));
        var currentIndex = blocks.indexOf(block);
        var targetIndex = currentIndex + direction;

        if (targetIndex < 0 || targetIndex >= blocks.length) {
            return;
        }

        var target = blocks[targetIndex];
        if (direction < 0) {
            list.insertBefore(block, target);
        } else {
            list.insertBefore(target, block);
        }

        renumberBlocks(list);
    }

    function repeatableTemplate(section) {
        if (!section) {
            return null;
        }
        return section.querySelector(':scope > template[data-repeatable-template]');
    }

    function initRepeatable(section) {
        if (!section || section.dataset.repeatableInit === '1') {
            return;
        }
        section.dataset.repeatableInit = '1';

        var list = section.querySelector('[data-repeatable-list]');
        var template = repeatableTemplate(section);
        var addBtn = section.querySelector('[data-repeatable-add]');

        if (list) {
            list.querySelectorAll(':scope > [data-repeatable-item]').forEach(function (item, index) {
                item.dataset.itemIndex = String(index);
                initBlockItem(item);
            });
        }

        if (addBtn && list && template) {
            addBtn.addEventListener('click', function () {
                var index = list.querySelectorAll(':scope > [data-repeatable-item]').length;
                var parentIndex = section.dataset.parentIndex || '';
                var html = template.innerHTML
                    .replace(/__INDEX__/g, String(index))
                    .replace(/__PARENT_INDEX__/g, parentIndex);

                var wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                var item = wrapper.firstElementChild;
                if (!item) {
                    return;
                }

                item.dataset.itemIndex = String(index);
                list.appendChild(item);
                initBlockItem(item);
            });
        }
    }

    function initBlockItem(item) {
        if (!item) {
            return;
        }

        var removeBtn = item.querySelector('[data-repeatable-remove]');
        if (removeBtn && !removeBtn.dataset.bound) {
            removeBtn.dataset.bound = '1';
            removeBtn.addEventListener('click', function () {
                var list = item.closest('[data-repeatable-list]');
                item.remove();
                if (list && list.hasAttribute('data-journal-blocks-list')) {
                    renumberBlocks(list);
                }
            });
        }

        var moveUp = item.querySelector('[data-journal-block-move-up]');
        if (moveUp && !moveUp.dataset.bound) {
            moveUp.dataset.bound = '1';
            moveUp.addEventListener('click', function () {
                var list = item.closest('[data-repeatable-list]');
                if (list) {
                    moveBlock(list, item, -1);
                }
            });
        }

        var moveDown = item.querySelector('[data-journal-block-move-down]');
        if (moveDown && !moveDown.dataset.bound) {
            moveDown.dataset.bound = '1';
            moveDown.addEventListener('click', function () {
                var list = item.closest('[data-repeatable-list]');
                if (list) {
                    moveBlock(list, item, 1);
                }
            });
        }

        initDragReorder(item);
        initMediaPickers(item);
        initJournalTextEditors(item);
        if (window.adminInitFaqAnswerEditors) {
            window.adminInitFaqAnswerEditors(item);
        }
        item.querySelectorAll('[data-repeatable]').forEach(initRepeatable);
    }

    function initDragReorder(block) {
        if (!block || block.dataset.dragInit === '1') {
            return;
        }
        block.dataset.dragInit = '1';

        var list = block.closest('[data-repeatable-list]');
        if (!list || !list.hasAttribute('data-journal-blocks-list')) {
            return;
        }

        var draggedBlock = null;

        block.addEventListener('dragstart', function (event) {
            draggedBlock = block;
            block.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'journal-block');
        });

        block.addEventListener('dragend', function () {
            draggedBlock = null;
            block.classList.remove('is-dragging');
            list.querySelectorAll('[data-journal-block]').forEach(function (node) {
                node.classList.remove('is-drag-over');
            });
        });

        block.addEventListener('dragover', function (event) {
            if (!draggedBlock || draggedBlock === block) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            block.classList.add('is-drag-over');
        });

        block.addEventListener('dragleave', function () {
            block.classList.remove('is-drag-over');
        });

        block.addEventListener('drop', function (event) {
            event.preventDefault();
            block.classList.remove('is-drag-over');
            if (!draggedBlock || draggedBlock === block) {
                return;
            }

            var blocks = Array.prototype.slice.call(list.querySelectorAll('[data-journal-block]'));
            var fromIndex = blocks.indexOf(draggedBlock);
            var toIndex = blocks.indexOf(block);
            if (fromIndex < toIndex) {
                list.insertBefore(draggedBlock, block.nextElementSibling);
            } else {
                list.insertBefore(draggedBlock, block);
            }

            renumberBlocks(list);
        });
    }

    function addBlock(root, list, type) {
        var template = root.querySelector('template[data-journal-block-template="' + type + '"]');
        if (!template) {
            return;
        }

        var index = list.querySelectorAll('[data-journal-block]').length;
        var html = template.innerHTML.replace(/__INDEX__/g, String(index));

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        var block = wrapper.firstElementChild;
        if (!block) {
            return;
        }

        list.appendChild(block);
        initBlockItem(block);
        renumberBlocks(list);
    }

    function positionBlockTypeModal(modal, anchor) {
        if (!modal || !anchor) {
            return;
        }

        var dialog = modal.querySelector('.admin-modal__dialog');
        if (!dialog) {
            return;
        }

        dialog.style.top = '';
        dialog.style.left = '';
        dialog.style.bottom = '';
        dialog.style.right = '';
        dialog.style.position = '';

        modal.classList.remove('admin-journal-block-type-modal--below');

        var anchorRect = anchor.getBoundingClientRect();
        var dialogRect = dialog.getBoundingClientRect();
        var gap = 12;
        var margin = 16;
        var spaceAbove = anchorRect.top - margin;
        var spaceBelow = window.innerHeight - anchorRect.bottom - margin;

        if (dialogRect.height + gap <= spaceAbove || spaceAbove >= spaceBelow) {
            return;
        }

        modal.classList.add('admin-journal-block-type-modal--below');
    }

    function openBlockTypeModal(modal, anchor) {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        positionBlockTypeModal(modal, anchor);
    }

    function closeBlockTypeModal(modal) {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.classList.remove('admin-journal-block-type-modal--below');
    }

    function initBlockTypeModal(root, list) {
        var modal = root.querySelector('[data-journal-block-type-modal]');
        var anchor = root.querySelector('[data-journal-block-add-anchor]');
        var openBtn = root.querySelector('[data-journal-block-add-open]');
        if (!modal || !openBtn || !list) {
            return;
        }

        openBtn.addEventListener('click', function () {
            if (!modal.hidden) {
                closeBlockTypeModal(modal);
                return;
            }
            openBlockTypeModal(modal, anchor);
        });

        modal.querySelectorAll('[data-journal-block-type-close]').forEach(function (node) {
            node.addEventListener('click', function () {
                closeBlockTypeModal(modal);
            });
        });

        modal.querySelectorAll('[data-journal-block-type-pick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var type = btn.getAttribute('data-journal-block-type-pick') || 'text';
                addBlock(root, list, type);
                closeBlockTypeModal(modal);
            });
        });
    }

    function initJournalBlocks(root) {
        if (!root || root.dataset.journalBlocksInit === '1') {
            return;
        }
        root.dataset.journalBlocksInit = '1';

        var list = root.querySelector('[data-repeatable-list]');
        if (list) {
            list.setAttribute('data-journal-blocks-list', '1');
            list.querySelectorAll('[data-journal-block]').forEach(initBlockItem);
            renumberBlocks(list);
        }

        initBlockTypeModal(root, list);
    }

    function escapeRecommendedHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renumberRecommendedCards(list) {
        if (!list) {
            return;
        }

        list.querySelectorAll('[data-journal-recommended-card]').forEach(function (card, index) {
            card.querySelectorAll('[name]').forEach(function (input) {
                input.name = input.name.replace(/recommended_products\[\d+\]/, 'recommended_products[' + index + ']');
                input.name = input.name.replace(/recommended_products\[__INDEX__\]/, 'recommended_products[' + index + ']');
            });
        });
    }

    function initRecommendedProducts(root) {
        if (!root || root.dataset.journalRecommendedInit === '1') {
            return;
        }
        root.dataset.journalRecommendedInit = '1';

        var list = root.querySelector('[data-journal-recommended-list]');
        var template = root.querySelector('[data-journal-recommended-template]');
        var searchRoot = root.querySelector('[data-journal-recommended-search]');
        var searchInput = root.querySelector('[data-journal-recommended-search-input]');
        var searchResults = root.querySelector('[data-journal-recommended-search-results]');
        var searchUrl = searchRoot ? searchRoot.getAttribute('data-search-url') : '';
        var maxCount = parseInt(root.getAttribute('data-max-count') || '12', 10);
        var debounceTimer = null;

        function cardCount() {
            return list ? list.querySelectorAll('[data-journal-recommended-card]').length : 0;
        }

        function hasProductId(productId) {
            if (!list || !productId) {
                return false;
            }
            return list.querySelector('[data-journal-recommended-card][data-product-id="' + productId + '"]') !== null;
        }

        function bindCard(card) {
            if (!card) {
                return;
            }
            var removeBtn = card.querySelector('[data-journal-recommended-remove]');
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    card.remove();
                    renumberRecommendedCards(list);
                });
            }
        }

        function cloneTemplateCard() {
            if (!template) {
                return null;
            }
            if (template.content && template.content.firstElementChild) {
                return template.content.firstElementChild.cloneNode(true);
            }
            var wrap = document.createElement('div');
            wrap.innerHTML = template.innerHTML;
            return wrap.firstElementChild;
        }

        function fillCard(card, item) {
            var productId = parseInt(item.id, 10);
            var title = item.productTitle || item.title || '';
            var meta = item.collection || '';
            if (meta && item.fabric) {
                meta += ' · ' + item.fabric;
            }

            card.setAttribute('data-product-id', String(productId));
            card.querySelector('[data-product-id-input]').value = String(productId);
            var labelInput = card.querySelector('[data-product-search-label]');
            if (labelInput) {
                labelInput.value = title;
            }

            var swatch = card.querySelector('.admin-home-product-search__swatch');
            if (swatch) {
                if (item.swatchStyle) {
                    swatch.style.cssText = item.swatchStyle;
                    swatch.classList.remove('admin-home-product-search__swatch--empty');
                } else {
                    swatch.style.cssText = '';
                    swatch.classList.add('admin-home-product-search__swatch--empty');
                }
            }

            var titleEl = card.querySelector('.admin-journal-recommended-card__title');
            if (titleEl) {
                titleEl.textContent = title || '—';
            }

            var metaEl = card.querySelector('.admin-journal-recommended-card__meta');
            if (metaEl) {
                if (meta) {
                    metaEl.textContent = meta;
                    metaEl.hidden = false;
                } else {
                    metaEl.textContent = '';
                    metaEl.hidden = true;
                }
            }
        }

        function addProduct(item) {
            if (!list || !item || !item.id) {
                return;
            }
            var productId = parseInt(item.id, 10);
            if (productId <= 0 || hasProductId(productId)) {
                return;
            }
            if (cardCount() >= maxCount) {
                return;
            }

            var card = cloneTemplateCard();
            if (!card) {
                return;
            }

            fillCard(card, item);
            list.appendChild(card);
            renumberRecommendedCards(list);
            bindCard(card);

            if (searchInput) {
                searchInput.value = '';
            }
            if (searchResults) {
                searchResults.hidden = true;
            }
        }

        function renderSearchResults(items) {
            if (!searchResults) {
                return;
            }

            if (!items || items.length === 0) {
                searchResults.innerHTML = '<div class="admin-home-product-search__empty">Ничего не найдено</div>';
                searchResults.hidden = false;
                return;
            }

            searchResults.innerHTML =
                '<div class="admin-home-product-search__head">Цвет</div>' +
                items
                    .map(function (item) {
                        var swatch = item.swatchStyle
                            ? '<span class="admin-home-product-search__swatch" style="' + escapeRecommendedHtml(item.swatchStyle) + '"></span>'
                            : '<span class="admin-home-product-search__swatch admin-home-product-search__swatch--empty"></span>';

                        return (
                            '<button type="button" class="admin-home-product-search__item" data-product-id="' + item.id + '">' +
                                swatch +
                                '<span class="admin-home-product-search__item-body">' +
                                    '<span class="admin-home-product-search__item-title">' + escapeRecommendedHtml(item.title) + '</span>' +
                                    (item.productTitle
                                        ? '<span class="admin-home-product-search__item-meta">' + escapeRecommendedHtml(item.productTitle) + '</span>'
                                        : '') +
                                '</span>' +
                            '</button>'
                        );
                    })
                    .join('');
            searchResults.hidden = false;

            searchResults.querySelectorAll('[data-product-id]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var id = parseInt(button.getAttribute('data-product-id'), 10);
                    var selected = items.find(function (item) {
                        return item.id === id;
                    });
                    if (selected) {
                        addProduct(selected);
                    }
                });
            });
        }

        function fetchSearch(query) {
            if (!searchUrl) {
                return Promise.resolve([]);
            }

            return fetch(searchUrl + '?q=' + encodeURIComponent(query), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('search failed');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    return Array.isArray(payload) ? payload : [];
                });
        }

        function runSearch(query) {
            if (query.length < 3) {
                if (searchResults) {
                    searchResults.hidden = true;
                }
                return;
            }

            fetchSearch(query)
                .then(function (items) {
                    renderSearchResults(items);
                })
                .catch(function () {
                    if (searchResults) {
                        searchResults.innerHTML = '<div class="admin-home-product-search__empty">Ошибка поиска</div>';
                        searchResults.hidden = false;
                    }
                });
        }

        if (list) {
            list.querySelectorAll('[data-journal-recommended-card]').forEach(bindCard);
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    runSearch(searchInput.value.trim());
                }, 300);
            });

            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });

            searchInput.addEventListener('focus', function () {
                var query = searchInput.value.trim();
                if (query.length >= 3) {
                    runSearch(query);
                }
            });
        }

        document.addEventListener('click', function (event) {
            if (!searchRoot || !searchResults) {
                return;
            }
            if (!searchRoot.contains(event.target)) {
                searchResults.hidden = true;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-journal-blocks]').forEach(initJournalBlocks);
        document.querySelectorAll('[data-journal-recommended]').forEach(initRecommendedProducts);
    });
})();
