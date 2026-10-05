(function () {
    var state = {
        modal: null,
        mediaId: 0,
        config: null,
        frame: null,
        originalUrl: '',
        loadUrlTemplate: '',
        saveUrlTemplate: '',
        csrfParam: '',
        csrfToken: '',
        dragging: false,
        dragStartX: 0,
        dragStartY: 0,
        dragOriginX: 0,
        dragOriginY: 0,
        onSaved: null,
        canEditFloorGuide: false,
        floorGuideSaveUrl: '',
        guideSaving: false,
        savedFloorGuide: 0,
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildUrl(base, mediaId) {
        if (!base) {
            return '';
        }
        var separator = base.indexOf('?') >= 0 ? '&' : '?';

        return base + separator + 'id=' + encodeURIComponent(String(mediaId));
    }

    var iconChevronUp =
        '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="m18 15-6-6-6 6"></path>' +
        '</svg>';
    var iconChevronDown =
        '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="m6 9 6 6 6-6"></path>' +
        '</svg>';
    var iconMinus =
        '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M5 12h14"></path>' +
        '</svg>';
    var iconPlus =
        '<svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M12 5v14"></path><path d="M5 12h14"></path>' +
        '</svg>';

    var SCALE_STEP = 0.01;
    var SCALE_WHEEL_STEP = 0.05;

    function ensureModal() {
        if (state.modal) {
            return state.modal;
        }

        var html =
            '<div class="admin-modal admin-listing-tile-modal" hidden>' +
                '<div class="admin-modal__backdrop" data-modal-close="1"></div>' +
                '<div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-listing-tile-modal-title">' +
                    '<div class="admin-modal__header">' +
                        '<h3 class="admin-modal__title" id="admin-listing-tile-modal-title">Кадр каталога</h3>' +
                        '<button type="button" class="admin-modal__close" data-modal-close="1" aria-label="Закрыть">&times;</button>' +
                    '</div>' +
                    '<div class="admin-modal__body">' +
                        '<p class="admin-muted admin-listing-tile-modal__hint">Перетащите фото. Справа — сдвиг и масштаб по шагу; также ползунок и Ctrl+колёсико. Нижняя линия — ориентир для опоры дивана.</p>' +
                        
                        '<div class="admin-listing-tile-editor">' +
                            '<div class="admin-listing-tile-editor__canvas">' +
                                '<div class="admin-listing-tile-editor__stage">' +
                                    '<img class="admin-listing-tile-editor__image" alt="" draggable="false">' +
                                    '<div class="admin-listing-tile-editor__guide" aria-hidden="true"></div>' +
                                '</div>' +
                                '<div class="admin-listing-tile-editor__nudge">' +
                                    '<button type="button" class="admin-icon-btn" data-listing-tile-nudge="up" aria-label="Сдвинуть на 1 px вверх" title="1 px вверх">' +
                                        iconChevronUp +
                                    '</button>' +
                                    '<button type="button" class="admin-icon-btn" data-listing-tile-nudge="down" aria-label="Сдвинуть на 1 px вниз" title="1 px вниз">' +
                                        iconChevronDown +
                                    '</button>' +
                                    '<div class="admin-listing-tile-editor__nudge-scale">' +
                                        '<button type="button" class="admin-icon-btn" data-listing-tile-scale-step="minus" aria-label="Уменьшить масштаб" title="Масштаб −0,01">' +
                                            iconMinus +
                                        '</button>' +
                                        '<button type="button" class="admin-icon-btn" data-listing-tile-scale-step="plus" aria-label="Увеличить масштаб" title="Масштаб +0,01">' +
                                            iconPlus +
                                        '</button>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            '<label class="admin-listing-tile-editor__scale">' +
                                'Масштаб' +
                                '<input type="range" min="0.25" max="4" step="0.01" value="1" data-listing-tile-scale>' +
                            '</label>' +
                            '<div class="admin-listing-tile-editor__guide-settings" hidden data-listing-tile-guide-settings>' +
                                '<span class="admin-listing-tile-editor__guide-label">Линия опоры от низа, px (общая для всех фото)</span>' +
                                '<div class="admin-listing-tile-editor__guide-row">' +
                                    '<input type="number" class="form-control admin-input--narrow admin-listing-tile-editor__guide-number" min="0" max="346" step="1" value="75" data-listing-tile-floor-guide>' +
                                    '<button type="button" class="admin-btn admin-btn--secondary admin-btn--sm" data-listing-tile-floor-guide-save>Сохранить линию</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<p class="admin-listing-tile-modal__error admin-muted" hidden data-listing-tile-error></p>' +
                    '</div>' +
                    '<div class="admin-modal__footer">' +
                        '<button type="button" class="admin-btn admin-btn--secondary" data-modal-close="1">Отмена</button>' +
                        '<button type="button" class="admin-btn admin-btn--primary" data-listing-tile-save>Сохранить</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        state.modal = wrapper.firstElementChild;
        document.body.appendChild(state.modal);

        state.modal.querySelectorAll('[data-modal-close]').forEach(function (node) {
            node.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state.modal && !state.modal.hidden) {
                closeModal();
            }
        });

        var stage = state.modal.querySelector('.admin-listing-tile-editor__stage');
        var image = state.modal.querySelector('.admin-listing-tile-editor__image');
        var scaleInput = state.modal.querySelector('[data-listing-tile-scale]');

        stage.addEventListener('pointerdown', function (event) {
            if (!state.frame) {
                return;
            }
            state.dragging = true;
            state.dragStartX = event.clientX;
            state.dragStartY = event.clientY;
            state.dragOriginX = state.frame.offsetX;
            state.dragOriginY = state.frame.offsetY;
            stage.setPointerCapture(event.pointerId);
        });

        stage.addEventListener('pointermove', function (event) {
            if (!state.dragging || !state.frame) {
                return;
            }
            state.frame.offsetX = state.dragOriginX + (event.clientX - state.dragStartX);
            state.frame.offsetY = state.dragOriginY + (event.clientY - state.dragStartY);
            applyTransform();
        });

        stage.addEventListener('pointerup', function (event) {
            state.dragging = false;
            if (stage.hasPointerCapture(event.pointerId)) {
                stage.releasePointerCapture(event.pointerId);
            }
        });

        stage.addEventListener('wheel', function (event) {
            if (!state.frame) {
                return;
            }
            if (!event.ctrlKey && !event.metaKey) {
                return;
            }
            event.preventDefault();
            var delta = event.deltaY > 0 ? -SCALE_WHEEL_STEP : SCALE_WHEEL_STEP;
            setFrameScale(state.frame.scale + delta);
        }, { passive: false });

        scaleInput.addEventListener('input', function () {
            setFrameScale(parseFloat(scaleInput.value || '1'));
        });

        state.modal.querySelector('[data-listing-tile-save]').addEventListener('click', saveCurrent);

        var guideInput = state.modal.querySelector('[data-listing-tile-floor-guide]');
        guideInput.addEventListener('input', function () {
            syncFloorGuideFromInput(guideInput);
        });
        guideInput.addEventListener('change', function () {
            syncFloorGuideFromInput(guideInput);
        });

        state.modal.querySelector('[data-listing-tile-floor-guide-save]').addEventListener('click', saveFloorGuide);

        state.modal.querySelectorAll('[data-listing-tile-nudge]').forEach(function (button) {
            button.addEventListener('click', function () {
                nudgeFrame(button.getAttribute('data-listing-tile-nudge'));
            });
        });

        state.modal.querySelectorAll('[data-listing-tile-scale-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                stepFrameScale(button.getAttribute('data-listing-tile-scale-step'));
            });
        });

        return state.modal;
    }

    function setFrameScale(value) {
        if (!state.frame || !state.modal) {
            return;
        }
        state.frame.scale = clampScale(value);
        var scaleInput = state.modal.querySelector('[data-listing-tile-scale]');
        if (scaleInput) {
            scaleInput.value = String(state.frame.scale);
            syncScaleRangeProgress(scaleInput);
        }
        applyTransform();
    }

    function stepFrameScale(direction) {
        if (!state.frame) {
            return;
        }
        if (direction === 'plus') {
            setFrameScale(state.frame.scale + SCALE_STEP);
        } else if (direction === 'minus') {
            setFrameScale(state.frame.scale - SCALE_STEP);
        }
    }

    function nudgeFrame(direction) {
        if (!state.frame) {
            return;
        }
        if (direction === 'up') {
            state.frame.offsetY -= 1;
        } else if (direction === 'down') {
            state.frame.offsetY += 1;
        } else {
            return;
        }
        applyTransform();
    }

    function clampScale(value) {
        if (Number.isNaN(value)) {
            return 1;
        }
        return Math.min(4, Math.max(0.25, value));
    }

    function clampFloorGuide(value, maxGuide) {
        var parsed = parseInt(String(value), 10);
        if (Number.isNaN(parsed)) {
            return 0;
        }
        return Math.min(maxGuide, Math.max(0, parsed));
    }

    function updateFloorGuideCurrentLabel() {
        if (!state.modal || !state.config) {
            return;
        }
        var currentEl = state.modal.querySelector('[data-listing-tile-floor-guide-current] strong');
        if (currentEl) {
            currentEl.textContent = String(state.savedFloorGuide);
        }
    }

    function syncScaleRangeProgress(scaleInput) {
        if (!scaleInput) {
            return;
        }
        var min = parseFloat(scaleInput.min || '0.25');
        var max = parseFloat(scaleInput.max || '4');
        var value = parseFloat(scaleInput.value || '1');
        var range = max - min;
        var pct = range > 0 ? ((value - min) / range) * 100 : 0;
        pct = Math.min(100, Math.max(0, pct));
        scaleInput.style.setProperty('--listing-tile-range-pct', pct + '%');
    }

    function setError(message) {
        if (!state.modal) {
            return;
        }
        var errorEl = state.modal.querySelector('[data-listing-tile-error]');
        if (!errorEl) {
            return;
        }
        errorEl.hidden = !message;
        errorEl.textContent = message || '';
    }

    function openModal() {
        if (!state.modal) {
            return;
        }
        state.modal.hidden = false;
        document.body.classList.add('admin-modal-open');
    }

    function closeModal() {
        if (!state.modal) {
            return;
        }
        state.modal.hidden = true;
        document.body.classList.remove('admin-modal-open');
        setError('');
    }

    function applyTransform() {
        var image = state.modal.querySelector('.admin-listing-tile-editor__image');
        if (!image || !state.frame || !state.config) {
            return;
        }
        var naturalWidth = image.naturalWidth;
        var naturalHeight = image.naturalHeight;
        if (!naturalWidth || !naturalHeight) {
            return;
        }
        var coverScale = Math.max(
            state.config.width / naturalWidth,
            state.config.height / naturalHeight
        ) * state.frame.scale;
        var drawWidth = naturalWidth * coverScale;
        var drawHeight = naturalHeight * coverScale;
        image.style.width = drawWidth + 'px';
        image.style.height = drawHeight + 'px';
        image.style.transform = 'translate(-50%, -50%) translate(' +
            state.frame.offsetX + 'px, ' + state.frame.offsetY + 'px)';
    }

    function applyConfigToStage() {
        if (!state.config || !state.modal) {
            return;
        }
        var stage = state.modal.querySelector('.admin-listing-tile-editor__stage');
        var guide = state.modal.querySelector('.admin-listing-tile-editor__guide');
        stage.style.width = state.config.width + 'px';
        stage.style.height = state.config.height + 'px';
        stage.style.background = state.config.background;
        stage.style.aspectRatio = state.config.aspectRatio || '132 / 100';
        guide.style.bottom = (state.config.floorGuideFromBottom || 100) + 'px';
        updateFloorGuideCurrentLabel();

        var guideSettings = state.modal.querySelector('[data-listing-tile-guide-settings]');
        var guideInput = state.modal.querySelector('[data-listing-tile-floor-guide]');
        if (guideSettings && guideInput) {
            guideSettings.hidden = !state.canEditFloorGuide;
            var maxGuide = Math.max(0, state.config.height - 1);
            guideInput.max = String(maxGuide);
            guideInput.value = String(state.config.floorGuideFromBottom || 0);
        }
    }

    function syncFloorGuideFromInput(guideInput) {
        if (!state.config || !guideInput) {
            return;
        }
        var maxGuide = Math.max(0, (state.config.height || 347) - 1);
        var clamped = clampFloorGuide(guideInput.value, maxGuide);
        guideInput.value = String(clamped);
        state.config.floorGuideFromBottom = clamped;
        applyConfigToStage();
    }

    function open(options) {
        ensureModal();
        state.mediaId = parseInt(options.mediaId || '0', 10);
        state.loadUrlTemplate = options.loadUrl || '';
        state.saveUrlTemplate = options.saveUrl || '';
        state.csrfParam = options.csrfParam || '';
        state.csrfToken = options.csrfToken || '';
        state.onSaved = typeof options.onSaved === 'function' ? options.onSaved : null;
        setError('');

        if (state.mediaId <= 0) {
            setError('Сначала выберите или сохраните фото.');
            return;
        }

        var loadUrl = buildUrl(state.loadUrlTemplate, state.mediaId);
        fetch(loadUrl, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.message || 'Не удалось загрузить кадр.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                state.config = payload.config;
                state.savedFloorGuide = parseInt(
                    payload.config && payload.config.floorGuideFromBottom != null
                        ? payload.config.floorGuideFromBottom
                        : 0,
                    10
                );
                state.canEditFloorGuide = !!payload.canEditFloorGuide;
                state.floorGuideSaveUrl = payload.floorGuideSaveUrl || '';
                state.frame = {
                    scale: parseFloat(payload.frame && payload.frame.scale != null ? payload.frame.scale : 1),
                    offsetX: parseFloat(payload.frame && payload.frame.offsetX != null ? payload.frame.offsetX : 0),
                    offsetY: parseFloat(payload.frame && payload.frame.offsetY != null ? payload.frame.offsetY : 0),
                };
                state.originalUrl = payload.originalUrl;
                applyConfigToStage();
                var image = state.modal.querySelector('.admin-listing-tile-editor__image');
                image.onload = function () {
                    applyTransform();
                };
                image.src = payload.originalUrl;
                var scaleInput = state.modal.querySelector('[data-listing-tile-scale]');
                scaleInput.value = String(state.frame.scale);
                syncScaleRangeProgress(scaleInput);
                openModal();
            })
            .catch(function (error) {
                setError(error.message || 'Ошибка загрузки.');
                openModal();
            });
    }

    function saveFloorGuide() {
        if (!state.canEditFloorGuide || !state.config || !state.floorGuideSaveUrl || state.guideSaving) {
            return;
        }
        var guideInput = state.modal.querySelector('[data-listing-tile-floor-guide]');
        if (!guideInput) {
            return;
        }

        var body = new URLSearchParams();
        var maxGuide = Math.max(0, (state.config.height || 347) - 1);
        body.set('floorGuideFromBottom', String(clampFloorGuide(guideInput.value, maxGuide)));
        if (state.csrfParam && state.csrfToken) {
            body.set(state.csrfParam, state.csrfToken);
        }

        state.guideSaving = true;
        setError('');
        fetch(state.floorGuideSaveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.message || 'Не удалось сохранить линию.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                if (payload.config) {
                    state.config = payload.config;
                    state.savedFloorGuide = parseInt(
                        payload.config.floorGuideFromBottom != null ? payload.config.floorGuideFromBottom : 0,
                        10
                    );
                    applyConfigToStage();
                }
            })
            .catch(function (error) {
                setError(error.message || 'Ошибка сохранения линии.');
            })
            .finally(function () {
                state.guideSaving = false;
            });
    }

    function saveCurrent() {
        if (state.mediaId <= 0 || !state.frame) {
            return;
        }
        var saveUrl = buildUrl(state.saveUrlTemplate, state.mediaId);
        var body = new URLSearchParams();
        body.set('scale', String(state.frame.scale));
        body.set('offsetX', String(state.frame.offsetX));
        body.set('offsetY', String(state.frame.offsetY));
        if (state.csrfParam && state.csrfToken) {
            body.set(state.csrfParam, state.csrfToken);
        }

        setError('');
        fetch(saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload.message || 'Не удалось сохранить кадр.');
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                if (state.onSaved) {
                    state.onSaved(payload);
                }
                closeModal();
            })
            .catch(function (error) {
                setError(error.message || 'Ошибка сохранения.');
            });
    }

    window.AdminListingTileEditor = {
        open: open,
    };
})();
