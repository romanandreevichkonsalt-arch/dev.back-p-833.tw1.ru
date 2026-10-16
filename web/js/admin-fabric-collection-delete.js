(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function csrfParam() {
        var meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.getAttribute('content') : '_csrf';
    }

    function postStage(url, stage, extra) {
        var body = new URLSearchParams();
        body.set('stage', stage);
        body.set(csrfParam(), csrfToken());
        if (extra) {
            Object.keys(extra).forEach(function (key) {
                body.set(key, String(extra[key]));
            });
        }

        var headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-CSRF-Token': csrfToken(),
        };

        return fetch(url, {
            method: 'POST',
            body: body.toString(),
            headers: headers,
            credentials: 'same-origin',
        }).then(function (response) {
            return response.text().then(function (text) {
                var payload = {};
                if (text) {
                    try {
                        payload = JSON.parse(text);
                    } catch (error) {
                        throw new Error('Сервер вернул неожиданный ответ. Обновите страницу и проверьте, удалилась ли коллекция.');
                    }
                }
                if (!response.ok) {
                    throw new Error(payload.message || 'Не удалось удалить коллекцию');
                }
                return payload;
            });
        });
    }

    function initFabricCollectionDelete() {
        var root = document.querySelector('[data-fabric-collection-delete-root]');
        if (!root || root.getAttribute('data-fabric-collection-delete-bound') === '1') {
            return;
        }

        var loader = root.querySelector('[data-fabric-collection-delete-loader]');
        var progressText = root.querySelector('[data-fabric-collection-delete-text]');
        var progressBar = root.querySelector('[data-fabric-collection-delete-bar]');
        var progressStats = root.querySelector('[data-fabric-collection-delete-stats]');
        var progressWrap = loader ? loader.querySelector('.admin-import-progress') : null;
        var spinner = root.querySelector('[data-fabric-collection-delete-spinner]');
        var successIcon = root.querySelector('[data-fabric-collection-delete-success]');

        if (!loader) {
            return;
        }

        root.setAttribute('data-fabric-collection-delete-bound', '1');

        var busy = false;

        function setLoader(active) {
            if (active) {
                loader.hidden = false;
                loader.removeAttribute('hidden');
                loader.style.display = '';
                loader.classList.remove('admin-import-loader--success');
                if (spinner) {
                    spinner.hidden = false;
                }
                if (successIcon) {
                    successIcon.hidden = true;
                }
                if (progressWrap) {
                    progressWrap.hidden = false;
                }
            } else {
                loader.hidden = true;
                loader.setAttribute('hidden', 'hidden');
                loader.style.display = 'none';
            }
        }

        function updateProgress(message, percent, statsText) {
            if (progressText) {
                progressText.textContent = message || 'Удаление…';
            }
            if (progressBar) {
                var safePercent = Math.max(0, Math.min(100, percent || 0));
                progressBar.style.width = safePercent + '%';
                progressBar.setAttribute('aria-valuenow', String(safePercent));
            }
            if (progressStats) {
                progressStats.textContent = statsText || '';
            }
        }

        function showSuccess(message) {
            loader.classList.add('admin-import-loader--success');
            if (spinner) {
                spinner.hidden = true;
            }
            if (successIcon) {
                successIcon.hidden = false;
            }
            if (progressWrap) {
                progressWrap.hidden = true;
            }
            if (progressText) {
                progressText.textContent = message || 'Готово';
            }
        }

        function skuStats(deletedProducts, totalProducts) {
            if (totalProducts > 0) {
                return 'Удалено SKU: ' + deletedProducts + ' из ' + totalProducts;
            }
            return '';
        }

        function productPercent(deletedProducts, totalProducts) {
            if (totalProducts <= 0) {
                return 0;
            }
            return Math.min(75, Math.round((deletedProducts / totalProducts) * 75));
        }

        function syncPercent(syncedModels, totalModels) {
            if (totalModels <= 0) {
                return 80;
            }
            return 75 + Math.round((syncedModels / totalModels) * 25);
        }

        function runSync(url, ctx) {
            return postStage(url, 'sync').then(function (data) {
                var totalModels = data.totalModels || ctx.totalModels || 0;
                var syncedModels = data.syncedModels || 0;
                var stats = skuStats(ctx.deletedProducts, ctx.totalProducts);
                if (totalModels > 0) {
                    stats = stats
                        ? stats + ' · Модели: ' + syncedModels + ' из ' + totalModels
                        : 'Модели: ' + syncedModels + ' из ' + totalModels;
                }

                updateProgress(
                    data.message || 'Синхронизация каталога…',
                    syncPercent(syncedModels, totalModels),
                    stats
                );

                if (data.done) {
                    return data;
                }
                if (data.needsSync) {
                    return runSync(url, {
                        totalProducts: ctx.totalProducts,
                        deletedProducts: ctx.deletedProducts,
                        totalModels: totalModels,
                    });
                }

                throw new Error('Неожиданный ответ сервера');
            });
        }

        function runDelete(url) {
            if (busy) {
                return;
            }
            busy = true;
            setLoader(true);
            updateProgress('Подготовка к удалению…', 0, '');

            var totalProducts = 0;
            var deletedProducts = 0;

            function runBatch() {
                return postStage(url, 'batch').then(function (data) {
                    if (data.totalProducts && totalProducts === 0) {
                        totalProducts = data.totalProducts;
                    }
                    deletedProducts += data.deletedInBatch || 0;

                    var stats = skuStats(deletedProducts, totalProducts);
                    updateProgress(
                        data.message || 'Удаление…',
                        productPercent(deletedProducts, totalProducts),
                        stats
                    );

                    if (!data.needsFinalize) {
                        return runBatch();
                    }

                    updateProgress('Удаление коллекции…', 78, stats);
                    return postStage(url, 'finalize', { deletedProducts: deletedProducts })
                        .then(function (finalizeData) {
                            if (finalizeData.done) {
                                return finalizeData;
                            }
                            if (finalizeData.needsSync) {
                                return runSync(url, {
                                    totalProducts: totalProducts,
                                    deletedProducts: deletedProducts,
                                    totalModels: finalizeData.totalModels || 0,
                                });
                            }
                            throw new Error('Неожиданный ответ сервера');
                        });
                });
            }

            runBatch()
                .then(function (data) {
                    if (!data || !data.done) {
                        throw new Error('Неожиданный ответ сервера');
                    }
                    showSuccess(data.message || 'Коллекция удалена');
                    window.setTimeout(function () {
                        window.location.assign(data.redirect || window.location.href);
                    }, 600);
                })
                .catch(function (error) {
                    setLoader(false);
                    busy = false;
                    window.alert(error.message || 'Не удалось удалить коллекцию');
                });
        }

        root.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-fabric-collection-delete]');
            if (!trigger || !root.contains(trigger)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            var confirmText = trigger.getAttribute('data-confirm');
            if (confirmText && !window.confirm(confirmText)) {
                return;
            }

            var url = trigger.getAttribute('href');
            if (!url) {
                return;
            }

            runDelete(url);
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFabricCollectionDelete);
    } else {
        initFabricCollectionDelete();
    }
})();
