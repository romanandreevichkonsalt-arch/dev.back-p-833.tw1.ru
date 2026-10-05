(function () {
    function openModal(modal) {
        if (!modal) {
            return;
        }
        modal.hidden = false;
        document.body.classList.add('admin-modal-open');
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('admin-modal-open');
    }

    function bindCreateModal(modal, openSelector, closeSelector) {
        if (!modal) {
            return;
        }

        document.querySelectorAll(openSelector).forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(modal);
            });
        });

        modal.querySelectorAll(closeSelector).forEach(function (el) {
            el.addEventListener('click', function () {
                closeModal(modal);
            });
        });

        if (!modal.hidden) {
            document.body.classList.add('admin-modal-open');
        }
    }

    function initPromoCodeCreateModal() {
        bindCreateModal(
            document.querySelector('[data-promo-code-create-modal]'),
            '[data-promo-code-create-modal-open]',
            '[data-promo-code-create-modal-close]'
        );
    }

    function initPromoBannerCreateModal() {
        bindCreateModal(
            document.querySelector('[data-promo-banner-create-modal]'),
            '[data-promo-banner-create-modal-open]',
            '[data-promo-banner-create-modal-close]'
        );
    }

    function initPromoPopupCreateModal() {
        bindCreateModal(
            document.querySelector('[data-promo-popup-create-modal]'),
            '[data-promo-popup-create-modal-open]',
            '[data-promo-popup-create-modal-close]'
        );
    }

    function promoModalFields(formRoot) {
        var modal = formRoot.querySelector('[data-banner-promo-modal]');
        if (!modal) {
            return null;
        }
        return modal.querySelector('[data-banner-promo-modal-fields]');
    }

    function promoPostFields(formRoot) {
        return formRoot.querySelector('[data-banner-promo-post-fields]');
    }

    function postInput(formRoot, attr) {
        var postRoot = promoPostFields(formRoot);
        if (!postRoot) {
            return null;
        }
        return postRoot.querySelector('[name$="[' + attr + ']"]');
    }

    function syncDraftToPost(formRoot) {
        var fieldsRoot = promoModalFields(formRoot);
        var postRoot = promoPostFields(formRoot);
        if (!fieldsRoot || !postRoot) {
            return;
        }

        fieldsRoot.querySelectorAll('[data-promo-draft]').forEach(function (input) {
            var key = input.getAttribute('data-promo-draft');
            if (!key) {
                return;
            }
            var postInputEl = postInput(formRoot, key);
            if (!postInputEl) {
                return;
            }
            if (input.type === 'checkbox') {
                postInputEl.value = input.checked ? '1' : '0';
                return;
            }
            postInputEl.value = input.value;
        });
    }

    function syncPromoModeBeforeSubmit(formRoot) {
        syncDraftToPost(formRoot);

        var modeInput = formRoot.querySelector('[data-banner-promo-mode]');
        var templateSelect = formRoot.querySelector('[data-banner-promo-template]');
        if (!modeInput) {
            return;
        }

        var codePost = postInput(formRoot, 'promo_code');
        var titlePost = postInput(formRoot, 'promo_title');
        var code = codePost ? codePost.value.trim() : '';
        var title = titlePost ? titlePost.value.trim() : '';

        if (templateSelect && templateSelect.value) {
            modeInput.value = 'existing';
            return;
        }

        if (code !== '' || title !== '') {
            modeInput.value = 'create';
            return;
        }

        modeInput.value = 'none';
    }

    function validatePromoModalFields(fieldsRoot) {
        if (!fieldsRoot) {
            return true;
        }
        var codeInput = fieldsRoot.querySelector('[data-promo-draft="promo_code"]');
        var titleInput = fieldsRoot.querySelector('[data-promo-draft="promo_title"]');
        var code = codeInput ? codeInput.value.trim() : '';
        var title = titleInput ? titleInput.value.trim() : '';
        if (code === '' || title === '') {
            window.alert('Укажите код и название промокода.');
            if (code === '' && codeInput) {
                codeInput.focus();
            } else if (titleInput) {
                titleInput.focus();
            }
            return false;
        }
        return true;
    }

    function defaultPromptText(select) {
        return select.getAttribute('data-prompt-default') || '— существующий —';
    }

    function syncTemplateSelectPrompt(formRoot) {
        var templateSelect = formRoot.querySelector('[data-banner-promo-template]');
        var modeInput = formRoot.querySelector('[data-banner-promo-mode]');
        if (!templateSelect) {
            return;
        }

        var promptOpt = templateSelect.querySelector('option[value=""]');
        if (!promptOpt) {
            return;
        }

        if (templateSelect.value) {
            promptOpt.textContent = defaultPromptText(templateSelect);
            templateSelect.classList.remove('admin-promo-template-select--pending');
            return;
        }

        var mode = modeInput ? modeInput.value : '';
        if (mode === 'create') {
            var codePost = postInput(formRoot, 'promo_code');
            var titlePost = postInput(formRoot, 'promo_title');
            var discountPost = postInput(formRoot, 'promo_discount_percent');
            var code = codePost ? codePost.value.trim() : '';
            if (code !== '') {
                var title = titlePost ? titlePost.value.trim() : '';
                var discount = discountPost ? discountPost.value.trim() : '';
                var label = 'Новый: ' + code;
                if (title !== '') {
                    label += ' — ' + title;
                }
                if (discount !== '') {
                    label += ' (' + discount + '%)';
                }
                promptOpt.textContent = label;
                templateSelect.value = '';
                templateSelect.classList.add('admin-promo-template-select--pending');
                return;
            }
        }

        promptOpt.textContent = defaultPromptText(templateSelect);
        templateSelect.classList.remove('admin-promo-template-select--pending');
    }

    function bannerPromoSummary(formRoot) {
        var summary = formRoot.querySelector('[data-banner-promo-summary]');
        var modeInput = formRoot.querySelector('[data-banner-promo-mode]');
        var templateSelect = formRoot.querySelector('[data-banner-promo-template]');
        if (!summary || !modeInput) {
            return;
        }

        var mode = modeInput.value;
        var hasPromo = false;

        if (templateSelect && templateSelect.value) {
            var linkedLabel = templateSelect.options[templateSelect.selectedIndex].text;
            summary.textContent = 'Привязан: ' + linkedLabel;
            hasPromo = true;
        } else if (mode === 'create') {
            var codePost = postInput(formRoot, 'promo_code');
            var discountPost = postInput(formRoot, 'promo_discount_percent');
            var code = codePost ? codePost.value.trim() : '';
            var discount = discountPost ? discountPost.value.trim() : '';
            if (code !== '') {
                summary.textContent = 'Новый промокод: ' + code + (discount !== '' ? ' (' + discount + '%)' : '');
                hasPromo = true;
            } else {
                summary.textContent = 'Промокод не задан';
            }
        } else {
            summary.textContent = 'Промокод не задан';
        }

        summary.classList.toggle('admin-promo-bar-summary', hasPromo);
        summary.classList.toggle('admin-muted', !hasPromo);

        syncTemplateSelectPrompt(formRoot);
    }

    function initBannerPromoModal() {
        document.querySelectorAll('[data-promo-link-form]').forEach(function (formRoot) {
            var modal = formRoot.querySelector('[data-banner-promo-modal]');
            if (!modal) {
                return;
            }

            var modeInput = formRoot.querySelector('[data-banner-promo-mode]');
            var templateSelect = formRoot.querySelector('[data-banner-promo-template]');

            syncDraftToPost(formRoot);

            formRoot.querySelectorAll('[data-banner-promo-modal-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (modeInput) {
                        modeInput.value = 'create';
                    }
                    if (templateSelect) {
                        templateSelect.value = '';
                    }
                    openModal(modal);
                });
            });

            modal.querySelectorAll('[data-banner-promo-modal-close]').forEach(function (el) {
                el.addEventListener('click', function () {
                    closeModal(modal);
                });
            });

            var applyBtn = modal.querySelector('[data-banner-promo-modal-apply]');
            var fieldsRoot = modal.querySelector('[data-banner-promo-modal-fields]');
            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    if (!validatePromoModalFields(fieldsRoot)) {
                        return;
                    }
                    if (modeInput) {
                        modeInput.value = 'create';
                    }
                    if (templateSelect) {
                        templateSelect.value = '';
                    }
                    syncDraftToPost(formRoot);
                    bannerPromoSummary(formRoot);
                    closeModal(modal);
                });
            }

            formRoot.addEventListener(
                'submit',
                function () {
                    syncPromoModeBeforeSubmit(formRoot);
                },
                true
            );

            formRoot.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    syncPromoModeBeforeSubmit(formRoot);
                });
            });

            if (templateSelect) {
                templateSelect.addEventListener('change', function () {
                    if (!modeInput) {
                        return;
                    }
                    if (templateSelect.value) {
                        modeInput.value = 'existing';
                    } else if (modeInput.value === 'existing') {
                        modeInput.value = 'none';
                    }
                    bannerPromoSummary(formRoot);
                });
            }

            bannerPromoSummary(formRoot);

            if (formRoot.getAttribute('data-reopen-promo-modal') === '1') {
                if (modeInput) {
                    modeInput.value = 'create';
                }
                openModal(modal);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initPromoCodeCreateModal();
        initPromoBannerCreateModal();
        initPromoPopupCreateModal();
        initBannerPromoModal();
    });
})();
