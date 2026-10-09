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

    var EDITOR_HEIGHT_KEY = 'adminJournalMarkdownEditorHeight';
    var EDITOR_HEIGHT_DEFAULT = 420;
    var EDITOR_HEIGHT_MIN = 200;
    var EDITOR_HEIGHT_MAX_FALLBACK = 900;

    function editorHeightBounds() {
        return {
            min: EDITOR_HEIGHT_MIN,
            max: Math.max(EDITOR_HEIGHT_MIN + 80, Math.min(EDITOR_HEIGHT_MAX_FALLBACK, window.innerHeight - 160)),
        };
    }

    function clampEditorHeight(value) {
        var bounds = editorHeightBounds();
        var height = parseInt(String(value), 10);
        if (isNaN(height)) {
            height = EDITOR_HEIGHT_DEFAULT;
        }

        return Math.min(bounds.max, Math.max(bounds.min, height));
    }

    function readStoredEditorHeight() {
        try {
            return clampEditorHeight(sessionStorage.getItem(EDITOR_HEIGHT_KEY));
        } catch (e) {
            return EDITOR_HEIGHT_DEFAULT;
        }
    }

    function storeEditorHeight(height) {
        try {
            sessionStorage.setItem(EDITOR_HEIGHT_KEY, String(height));
        } catch (e) {
            // ignore quota / private mode
        }
    }

    function applyEditorHeight(root, easymde, height) {
        height = clampEditorHeight(height);
        root.style.setProperty('--admin-journal-markdown-editor-max-height', height + 'px');

        if (easymde && easymde.codemirror) {
            easymde.codemirror.setSize(null, height);
            window.setTimeout(function () {
                easymde.codemirror.refresh();
            }, 0);
        }

        storeEditorHeight(height);
    }

    function ensureEditorResizeHandle(root) {
        if (root.querySelector('[data-journal-markdown-resize]')) {
            return root.querySelector('[data-journal-markdown-resize]');
        }

        var container = root.querySelector('.EasyMDEContainer');
        if (!container) {
            return null;
        }

        var resizeEl = document.createElement('div');
        resizeEl.className = 'admin-journal-markdown__resize';
        resizeEl.setAttribute('data-journal-markdown-resize', '');
        resizeEl.setAttribute('role', 'separator');
        resizeEl.setAttribute('aria-orientation', 'horizontal');
        resizeEl.setAttribute('aria-label', 'Изменить высоту редактора');
        resizeEl.setAttribute('title', 'Потяните, чтобы изменить высоту редактора');
        resizeEl.innerHTML = '<span class="admin-journal-markdown__resize-grip" aria-hidden="true"></span>';
        container.insertAdjacentElement('afterend', resizeEl);

        return resizeEl;
    }

    function initEditorResize(root, easymde) {
        var handle = ensureEditorResizeHandle(root);
        if (!handle || handle.dataset.journalMarkdownResizeInit === '1') {
            if (handle) {
                applyEditorHeight(root, easymde, readStoredEditorHeight());
            }
            return;
        }

        handle.dataset.journalMarkdownResizeInit = '1';
        applyEditorHeight(root, easymde, readStoredEditorHeight());

        var dragging = false;
        var startY = 0;
        var startHeight = 0;

        function onPointerMove(event) {
            if (!dragging) {
                return;
            }

            var delta = event.clientY - startY;
            applyEditorHeight(root, easymde, startHeight + delta);
        }

        function stopDrag() {
            if (!dragging) {
                return;
            }

            dragging = false;
            document.body.classList.remove('admin-journal-markdown--resizing');
            document.removeEventListener('mousemove', onPointerMove);
            document.removeEventListener('mouseup', stopDrag);
        }

        handle.addEventListener('mousedown', function (event) {
            if (event.button !== 0) {
                return;
            }

            event.preventDefault();
            dragging = true;
            startY = event.clientY;
            startHeight = clampEditorHeight(
                root.style.getPropertyValue('--admin-journal-markdown-editor-max-height').replace('px', '')
                    || readStoredEditorHeight()
            );
            document.body.classList.add('admin-journal-markdown--resizing');
            document.addEventListener('mousemove', onPointerMove);
            document.addEventListener('mouseup', stopDrag);
        });

        window.addEventListener('resize', function () {
            applyEditorHeight(root, easymde, readStoredEditorHeight());
        });
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

        initEditorResize(root, easymde);
    }

    document.querySelectorAll('[data-journal-markdown-editor]').forEach(initEditor);
})();
