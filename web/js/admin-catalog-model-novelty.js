(function () {
    function parseBadgeIds(raw) {
        try {
            var parsed = JSON.parse(raw || '[]');
            return Array.isArray(parsed) ? parsed.map(function (id) { return parseInt(id, 10); }) : [];
        } catch (e) {
            return [];
        }
    }

    function getBadgeField(form) {
        return form.querySelector('[name="CatalogModel[badge_id]"]');
    }

    function isNoveltyBadge(badgeId, noveltyBadgeIds) {
        var id = parseInt(badgeId, 10);
        return id > 0 && noveltyBadgeIds.indexOf(id) !== -1;
    }

    function badgeChanged(form, badgeField, noveltyBadgeIds) {
        var current = parseInt(badgeField.value, 10) || 0;
        var initial = parseInt(form.dataset.initialBadgeId, 10) || 0;
        if (!isNoveltyBadge(current, noveltyBadgeIds)) {
            return false;
        }

        return current !== initial;
    }

    function hasProductsContext(form) {
        if (form.dataset.hasProducts === '1') {
            return true;
        }

        return form.querySelectorAll('input[name="fabric_collection_ids[]"]:checked').length > 0;
    }

    function openModal(modal) {
        modal.hidden = false;
        document.body.classList.add('admin-modal-open');
    }

    function closeModal(modal) {
        modal.hidden = true;
        document.body.classList.remove('admin-modal-open');
    }

    function stopEvent(event) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-catalog-model-form]');
        var modal = document.querySelector('[data-catalog-model-novelty-modal]');
        if (!form || !modal) {
            return;
        }

        var noveltyBadgeIds = parseBadgeIds(form.dataset.noveltyBadgeIds);
        var actionInput = form.querySelector('#catalog-model-novelty-promo-action');
        var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        var modalOpen = false;
        var promoActionChosen = false;

        function needsModal() {
            var badgeField = getBadgeField(form);
            if (!badgeField) {
                return false;
            }

            return badgeChanged(form, badgeField, noveltyBadgeIds) && hasProductsContext(form);
        }

        function clearPromoAction() {
            if (actionInput) {
                actionInput.value = '';
            }
        }

        function shouldBlockSubmit() {
            if (promoActionChosen) {
                return false;
            }

            if (modalOpen) {
                return true;
            }

            return needsModal();
        }

        function ensureModalOpen() {
            if (modalOpen || !needsModal()) {
                return;
            }

            modalOpen = true;
            openModal(modal);
        }

        function blockSubmitEvent(event) {
            if (!shouldBlockSubmit()) {
                if (!needsModal()) {
                    clearPromoAction();
                }
                return;
            }

            stopEvent(event);
            ensureModalOpen();
        }

        form.addEventListener('click', function (event) {
            var button = event.target.closest('button[type="submit"], input[type="submit"]');
            if (!button || !form.contains(button)) {
                return;
            }

            blockSubmitEvent(event);
        }, true);

        form.addEventListener('submit', function (event) {
            blockSubmitEvent(event);
        }, true);

        if (window.jQuery) {
            window.jQuery(form).on('beforeSubmit', function () {
                if (promoActionChosen) {
                    return true;
                }

                if (!needsModal()) {
                    clearPromoAction();
                    return true;
                }

                ensureModalOpen();
                return false;
            });
        }

        modal.querySelectorAll('[data-catalog-model-novelty-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal(modal);
                modalOpen = false;
                clearPromoAction();
            });
        });

        modal.querySelectorAll('[data-catalog-model-novelty-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (actionInput) {
                    actionInput.value = button.getAttribute('data-catalog-model-novelty-action') || '';
                }

                closeModal(modal);
                modalOpen = false;
                promoActionChosen = true;

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitButton || undefined);
                    return;
                }

                if (submitButton) {
                    submitButton.click();
                }
            });
        });
    });
})();
