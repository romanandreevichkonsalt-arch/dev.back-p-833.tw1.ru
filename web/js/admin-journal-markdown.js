(function () {
    var IMAGE_VARIANTS = ['medium', 'large', 'original'];

    function insertAtCursor(editor, text) {
        if (!editor || !editor.codemirror) {
            return;
        }

        var cm = editor.codemirror;
        var doc = cm.getDoc();
        var cursor = doc.getCursor();
        doc.replaceRange(text, cursor);
        cm.focus();
    }

    function normalizeVariant(value) {
        var variant = String(value || 'medium').trim().toLowerCase();
        if (IMAGE_VARIANTS.indexOf(variant) === -1) {
            return 'medium';
        }

        return variant;
    }

    function pickImageVariant() {
        var choice = window.prompt(
            'Размер фото в статье:\nmedium — до 1200px (по умолчанию)\nlarge — до 1920px\noriginal — оригинал',
            'medium'
        );
        if (choice === null) {
            return null;
        }

        return normalizeVariant(choice);
    }

    function mediaUrlForVariant(item, variant) {
        if (!item) {
            return '';
        }

        variant = normalizeVariant(variant);
        if (item.urls && item.urls[variant]) {
            return String(item.urls[variant]);
        }

        if (variant === 'medium' && item.url) {
            var url = String(item.url);
            if (url.charAt(0) === '/' || url.indexOf('http') === 0) {
                return url;
            }
        }

        if (item.urls && item.urls.original) {
            return String(item.urls.original);
        }

        if (item.url) {
            var fallbackUrl = String(item.url);
            if (fallbackUrl.charAt(0) === '/' || fallbackUrl.indexOf('http') === 0) {
                return fallbackUrl;
            }
        }

        return '';
    }

    function mediaReference(item, variant) {
        if (!item) {
            return '';
        }

        variant = normalizeVariant(variant);
        if (item.id) {
            var ref = String(item.id);
            if (variant !== 'medium') {
                ref += '#' + variant;
            }

            return ref;
        }

        return mediaUrlForVariant(item, variant);
    }

    function mediaAlt(item) {
        if (!item) {
            return '';
        }

        var alt = item.alt ? String(item.alt).trim() : '';
        if (alt !== '') {
            return alt;
        }

        return '';
    }

    function resolvePreviewImageUrl(url) {
        var value = String(url || '').trim();
        if (value === '') {
            return value;
        }

        var match = value.match(/^(\d+)(?:#(medium|large|original|mini))?$/);
        if (match) {
            var previewUrl = '/admin/media/public-url?id=' + encodeURIComponent(match[1]);
            previewUrl += '&variant=' + encodeURIComponent(match[2] || 'medium');

            return previewUrl;
        }

        if (value.charAt(0) === '/' || value.indexOf('http') === 0) {
            return value;
        }

        return value;
    }

    function enhancePreviewHtml(html) {
        if (!html) {
            return html;
        }

        return html.replace(/<img\b([^>]*?)\bsrc=(["'])(.*?)\2/gi, function (_, attrs, quote, src) {
            var raw = String(src).replace(/&amp;/g, '&');
            try {
                raw = decodeURIComponent(raw);
            } catch (e) {
                // keep raw src
            }
            var resolved = resolvePreviewImageUrl(raw);
            return '<img' + attrs + 'src=' + quote + resolved + quote;
        });
    }

    function imageMarkdown(item, variant) {
        var src = mediaReference(item, variant);
        if (!src) {
            return '';
        }

        return '![' + mediaAlt(item) + '](' + src + ')';
    }

    function ensureMediaLibrary(root, onApply) {
        var host = root.querySelector('.admin-journal-markdown__media-host');
        if (!host || !window.AdminMediaLibrary) {
            return null;
        }

        if (host.dataset.journalMediaInit !== '1') {
            host.dataset.journalMediaInit = '1';
            host._journalMediaLibrary = window.AdminMediaLibrary.init(host, {
                mode: 'multi',
                openButton: host.querySelector('[data-journal-media-open]'),
                onApply: function (selectedItems) {
                    if (typeof host._journalMediaApplyHandler === 'function') {
                        host._journalMediaApplyHandler(selectedItems);
                    }
                    return Promise.resolve();
                },
            });
        }

        host._journalMediaApplyHandler = onApply;

        if (host._journalMediaLibrary && host._journalMediaLibrary.open) {
            host._journalMediaLibrary.open();
        }

        return host._journalMediaLibrary;
    }

    function insertGallery(editor, items) {
        if (!items || items.length === 0) {
            return;
        }

        var lines = ['', '::: gallery columns=2'];
        items.forEach(function (item) {
            var line = imageMarkdown(item, 'medium');
            if (line) {
                lines.push(line);
            }
        });
        lines.push(':::');
        lines.push('');

        insertAtCursor(editor, lines.join('\n'));
    }

    function insertImage(editor, items) {
        if (!items || items.length === 0) {
            return;
        }

        var variant = pickImageVariant();
        if (variant === null) {
            return;
        }

        var markdown = imageMarkdown(items[0], variant);
        if (!markdown) {
            return;
        }

        insertAtCursor(editor, '\n\n' + markdown + '\n\n');
    }

    function buildToolbar(root) {
        return [
            'italic',
            'quote',
            'heading-2',
            'heading-3',
            '|',
            'link',
            {
                name: 'journal-photo',
                action: function (editor) {
                    ensureMediaLibrary(root, function (items) {
                        insertImage(editor, items.slice(0, 1));
                    });
                },
                className: 'fa fa-image',
                title: 'Фото',
            },
            {
                name: 'journal-gallery',
                action: function (editor) {
                    ensureMediaLibrary(root, function (items) {
                        insertGallery(editor, items);
                    });
                },
                className: 'fa fa-th-large',
                title: 'Галерея',
            },
            '|',
            'unordered-list',
            'ordered-list',
            '|',
            'preview',
            'side-by-side',
            'guide',
        ];
    }

    function initEditor(root) {
        if (root.dataset.journalMarkdownInit === '1') {
            return;
        }

        var textarea = root.querySelector('.admin-journal-markdown__textarea');
        if (!textarea || !window.EasyMDE) {
            return;
        }

        root.dataset.journalMarkdownInit = '1';

        var easymde = new window.EasyMDE({
            element: textarea,
            autofocus: false,
            spellChecker: false,
            status: false,
            sideBySideFullscreen: false,
            previewImagesInEditor: true,
            imagesPreviewHandler: resolvePreviewImageUrl,
            previewRender: function (text) {
                return enhancePreviewHtml(easymde.markdown(text));
            },
            renderingConfig: {
                singleLineBreaks: false,
            },
            toolbar: buildToolbar(root),
        });
    }

    document.querySelectorAll('[data-journal-markdown-editor]').forEach(initEditor);
})();
