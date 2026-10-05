(function () {
    var SCOPE_PRODUCT = 'product';
    var DISCOUNT_FIXED = 'fixed_amount';

    function clearProductPicker(wrap) {
        if (!wrap) {
            return;
        }
        var productIdInput = wrap.querySelector('[data-product-id-input]');
        var searchInput = wrap.querySelector('[data-product-search-input]');
        var results = wrap.querySelector('[data-product-search-results]');
        if (productIdInput) {
            productIdInput.value = '';
        }
        if (searchInput) {
            searchInput.value = '';
        }
        if (results) {
            results.hidden = true;
        }
    }

    function syncProductFieldVisibility(form) {
        var scopeSelect = form.querySelector('[data-catalog-promotion-scope]');
        var productWrap = form.querySelector('[data-catalog-promotion-product-wrap]');
        if (!scopeSelect || !productWrap) {
            return;
        }

        var isProduct = scopeSelect.value === SCOPE_PRODUCT;
        productWrap.hidden = !isProduct;
        if (!isProduct) {
            clearProductPicker(productWrap);
        }
    }

    function syncDiscountHint(form) {
        var typeSelect = form.querySelector('[data-catalog-promotion-discount-type]');
        var hintEl = form.querySelector('[data-catalog-promotion-discount-hint]');
        if (!typeSelect || !hintEl) {
            return;
        }

        var isFixed = typeSelect.value === DISCOUNT_FIXED;
        var text = isFixed
            ? (form.dataset.discountHintFixed || '')
            : (form.dataset.discountHintPercent || '');
        hintEl.textContent = text;
    }

    function initCatalogPromotionForm(form) {
        if (!form || form.dataset.catalogPromotionInit === '1') {
            return;
        }
        form.dataset.catalogPromotionInit = '1';

        var scopeSelect = form.querySelector('[data-catalog-promotion-scope]');
        var modelSelect = form.querySelector('[data-catalog-promotion-model]');
        var productWrap = form.querySelector('[data-catalog-promotion-product-wrap]');
        var discountTypeSelect = form.querySelector('[data-catalog-promotion-discount-type]');

        if (scopeSelect) {
            scopeSelect.addEventListener('change', function () {
                syncProductFieldVisibility(form);
            });
        }

        if (modelSelect && productWrap) {
            modelSelect.addEventListener('change', function () {
                clearProductPicker(productWrap);
            });
        }

        if (discountTypeSelect) {
            discountTypeSelect.addEventListener('change', function () {
                syncDiscountHint(form);
            });
        }

        syncProductFieldVisibility(form);
        syncDiscountHint(form);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-catalog-promotion-form]').forEach(initCatalogPromotionForm);
    });
})();
