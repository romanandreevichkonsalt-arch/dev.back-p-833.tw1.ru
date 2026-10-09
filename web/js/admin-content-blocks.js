(function () {
    function initMediaPickers(root) {
        root.querySelectorAll('.admin-media-picker').forEach(function (picker) {
            if (window.adminMediaPickerInit) {
                window.adminMediaPickerInit(picker);
            }
        });
    }

    function initParagraphRow(row) {
        if (!row || row.dataset.paragraphInit === '1') {
            return;
        }
        row.dataset.paragraphInit = '1';

        var typeSelect = row.querySelector('[data-paragraph-type]');
        var textBlock = row.querySelector('[data-paragraph-text]');
        var richBlock = row.querySelector('[data-paragraph-rich]');

        function syncType() {
            var isRich = typeSelect && typeSelect.value === 'rich';
            if (textBlock) {
                textBlock.hidden = isRich;
            }
            if (richBlock) {
                richBlock.hidden = !isRich;
            }
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', syncType);
            syncType();
        }
    }

    function getItemIndex(section) {
        var item = section.closest('[data-repeatable-item]');
        if (item && item.dataset.itemIndex) {
            return item.dataset.itemIndex;
        }

        return '0';
    }

    function initItem(item) {
        if (!item) {
            return;
        }

        var removeBtn = item.querySelector('[data-repeatable-remove]');
        if (removeBtn && !removeBtn.dataset.bound) {
            removeBtn.dataset.bound = '1';
            removeBtn.addEventListener('click', function () {
                var list = item.closest('[data-repeatable-list]');
                item.remove();
                if (list) {
                    renumberFaqItems(list);
                }
            });
        }

        initMediaPickers(item);
        item.querySelectorAll('[data-paragraph-row]').forEach(initParagraphRow);
        item.querySelectorAll('[data-repeatable]').forEach(initRepeatable);
        initFaqAnswerEditors(item);
    }

    function resizeFaqAnswer(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(textarea.scrollHeight, 40) + 'px';
    }

    function initFaqAnswerEditors(root) {
        if (!root) {
            return;
        }

        root.querySelectorAll('.admin-faq-answer-editor').forEach(function (textarea) {
            if (textarea.dataset.faqAnswerInit === '1') {
                return;
            }
            textarea.dataset.faqAnswerInit = '1';
            resizeFaqAnswer(textarea);
            textarea.addEventListener('input', function () {
                resizeFaqAnswer(textarea);
            });
        });

        root.querySelectorAll('[data-faq-answer-link]').forEach(function (button) {
            if (button.dataset.faqAnswerLinkInit === '1') {
                return;
            }
            button.dataset.faqAnswerLinkInit = '1';
            button.addEventListener('click', function () {
                var field = button.closest('.admin-page-field');
                var textarea = field ? field.querySelector('.admin-faq-answer-editor') : null;
                if (!textarea) {
                    return;
                }
                insertFaqAnswerLink(textarea);
            });
        });
    }

    function insertFaqAnswerLink(textarea) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.slice(start, end).trim();
        var label = selected;

        if (!label) {
            label = window.prompt('Текст ссылки', '') || '';
            if (!label) {
                return;
            }
        }

        var url = window.prompt('URL ссылки', '/');
        if (url === null) {
            return;
        }

        url = url.trim();
        if (url === '') {
            return;
        }

        var insertion = '[' + label + '](' + url + ')';
        textarea.value = value.slice(0, start) + insertion + value.slice(end);
        textarea.focus();
        var cursor = start + insertion.length;
        textarea.setSelectionRange(cursor, cursor);
        resizeFaqAnswer(textarea);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function renumberFaqItems(list) {
        if (!list) {
            return;
        }

        list.querySelectorAll('.admin-faq-item[data-repeatable-item]').forEach(function (faqItem, index) {
            var title = faqItem.querySelector('[data-faq-item-title]');
            if (title) {
                title.textContent = 'Вопрос ' + (index + 1);
            }
        });
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
                initItem(item);
            });
            renumberFaqItems(list);
        }

        if (addBtn && list && template) {
            addBtn.addEventListener('click', function () {
                var index = list.querySelectorAll(':scope > [data-repeatable-item]').length;
                var parentIndex = section.dataset.parentIndex || '';
                var itemIndex = getItemIndex(section);
                var html = template.innerHTML
                    .replace(/__INDEX__/g, String(index))
                    .replace(/__PARENT_INDEX__/g, parentIndex)
                    .replace(/__ITEM_INDEX__/g, itemIndex);

                var wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                var item = wrapper.firstElementChild;
                if (!item) {
                    return;
                }

                item.dataset.itemIndex = String(index);
                list.appendChild(item);
                initItem(item);
                renumberFaqItems(list);
            });
        }
    }

    window.adminInitFaqAnswerEditors = initFaqAnswerEditors;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-repeatable]').forEach(initRepeatable);
        document.querySelectorAll('[data-paragraph-row]').forEach(initParagraphRow);
        document.querySelectorAll('.admin-faq-tabs').forEach(initFaqTabs);
        initFaqAnswerEditors(document);
    });

    function initFaqTabs(root) {
        if (!root || root.dataset.faqTabsInit === '1') {
            return;
        }
        root.dataset.faqTabsInit = '1';

        var tabs = root.querySelectorAll('[data-faq-tab]');
        var panels = root.querySelectorAll('[data-faq-panel]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var index = tab.getAttribute('data-faq-tab');

                tabs.forEach(function (item) {
                    var active = item.getAttribute('data-faq-tab') === index;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    panel.classList.toggle('is-active', panel.getAttribute('data-faq-panel') === index);
                });
            });
        });
    }
})();
