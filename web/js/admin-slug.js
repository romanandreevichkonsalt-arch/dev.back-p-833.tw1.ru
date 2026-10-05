(function () {
    var TRANSLIT = {
        'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd',
        'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i',
        'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
        'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't',
        'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'ts', 'ч': 'ch',
        'ш': 'sh', 'щ': 'sch', 'ъ': '', 'ы': 'y', 'ь': '',
        'э': 'e', 'ю': 'yu', 'я': 'ya',
    };

    function slugify(text) {
        var value = String(text || '').trim().toLowerCase();
        if (!value) {
            return '';
        }

        var chars = [];
        for (var i = 0; i < value.length; i++) {
            var char = value.charAt(i);
            chars.push(Object.prototype.hasOwnProperty.call(TRANSLIT, char) ? TRANSLIT[char] : char);
        }

        return chars.join('')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 64);
    }

    function resolveSourceInput(slugInput) {
        var sourceId = slugInput.getAttribute('data-admin-slug-source');
        if (sourceId) {
            var byId = document.getElementById(sourceId);
            if (byId) {
                return byId;
            }
        }

        var form = slugInput.form;
        var sourceName = slugInput.getAttribute('data-admin-slug-source-name');
        if (form && sourceName && form.elements[sourceName]) {
            return form.elements[sourceName];
        }

        if (form && slugInput.name) {
            var fallbackNames = [
                slugInput.name.replace(/\[slug\]$/, '[label]'),
                slugInput.name.replace(/\[slug\]$/, '[title]'),
                slugInput.name.replace(/\[slug\]$/, '[name]'),
            ];
            for (var i = 0; i < fallbackNames.length; i++) {
                if (fallbackNames[i] !== slugInput.name && form.elements[fallbackNames[i]]) {
                    return form.elements[fallbackNames[i]];
                }
            }
        }

        return null;
    }

    window.adminSlugify = slugify;

    function initSlugField(slugInput) {
        if (!slugInput || slugInput.getAttribute('data-admin-slug') !== '1') {
            return;
        }

        if (slugInput.dataset.slugInitialized === '1') {
            return;
        }
        slugInput.dataset.slugInitialized = '1';

        var sourceInput = resolveSourceInput(slugInput);
        if (!sourceInput) {
            return;
        }

        var slugEditedByUser = false;

        slugInput.addEventListener('input', function () {
            slugEditedByUser = true;
        });

        var syncSlug = function () {
            if (slugEditedByUser) {
                return;
            }
            slugInput.value = slugify(sourceInput.value);
        };

        sourceInput.addEventListener('input', syncSlug);
        sourceInput.addEventListener('change', syncSlug);

        if (!slugEditedByUser && slugInput.value.trim() === '' && sourceInput.value.trim() !== '') {
            syncSlug();
        }
    }

    function initAllSlugFields(root) {
        var scope = root || document;
        scope.querySelectorAll('input[data-admin-slug="1"]').forEach(initSlugField);
    }

    window.adminSlugInit = function (slugInput) {
        if (slugInput) {
            slugInput.dataset.slugInitialized = '';
            initSlugField(slugInput);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAllSlugFields();
        });
    } else {
        initAllSlugFields();
    }
})();
