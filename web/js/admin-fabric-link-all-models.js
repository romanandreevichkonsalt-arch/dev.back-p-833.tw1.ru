(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function csrfParam() {
        var meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.getAttribute('content') : '_csrf';
    }

    function postStage(url, stage) {
        var body = new URLSearchParams();
        body.set('stage', stage);
        body.set(csrfParam(), csrfToken());

        return fetch(url, {
            method: 'POST',
            body: body.toString(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-Token': csrfToken(),
            },
            credentials: 'same-origin',
        }).then(function (response) {
            return response.text().then(function (text) {
                var payload = {};
                if (text) {
                    try {
                        payload = JSON.parse(text);
                    } catch (error) {
                        throw new Error('Сервер вернул неожиданный ответ. Обновите страницу и проверьте результат.');
                    }
                }
                if (!response.ok) {
                    throw new Error(payload.message || 'Ошибка обновления моделей');
                }
                return payload;
            });
        });
    }

    function initFabricLinkAllModels() {
        var root = document.querySelector('[data-fabric-link-all-root]');
        if (!root || root.getAttribute('data-fabric-link-all-bound') === '1') {
            return;
        }

        var form = root.querySelector('[data-fabric-link-all-form]');
        var loader = root.querySelector('[data-fabric-link-all-loader]');
        var submitButton = root.querySelector('[data-fabric-link-all-submit]');
        var progressText = root.querySelector('[data-fabric-link-all-text]');
        var progressBar = root.querySelector('[data-fabric-link-all-bar]');
        var progressStats = root.querySelector('[data-fabric-link-all-stats]');
        var actionUrl = root.getAttribute('data-fabric-link-all-url');

        if (!form || !loader || !actionUrl) {
            return;
        }

        root.setAttribute('data-fabric-link-all-bound', '1');

        var busy = false;

        function setLoading(active) {
            if (active) {
                loader.hidden = false;
                loader.removeAttribute('hidden');
                loader.style.display = '';
                loader.classList.remove('admin-import-loader--success');
            } else {
                loader.hidden = true;
                loader.setAttribute('hidden', 'hidden');
                loader.style.display = 'none';
            }

            if (submitButton) {
                submitButton.disabled = active;
                submitButton.setAttribute('aria-busy', active ? 'true' : 'false');
            }

            var toolbarLinks = root.querySelectorAll('.admin-toolbar__links a.admin-btn, .admin-toolbar__links button.admin-btn');
            toolbarLinks.forEach(function (el) {
                if (el === submitButton) {
                    return;
                }
                if (active) {
                    el.setAttribute('aria-disabled', 'true');
                    el.classList.add('admin-btn--disabled');
                    if (el.tagName === 'A') {
                        el.dataset.fabricLinkAllPrevHref = el.getAttribute('href') || '';
                        el.removeAttribute('href');
                    }
                } else {
                    el.removeAttribute('aria-disabled');
                    el.classList.remove('admin-btn--disabled');
                    if (el.tagName === 'A' && el.dataset.fabricLinkAllPrevHref) {
                        el.setAttribute('href', el.dataset.fabricLinkAllPrevHref);
                        delete el.dataset.fabricLinkAllPrevHref;
                    }
                }
            });
        }

        function updateProgress(message, percent, statsText) {
            if (progressText) {
                progressText.textContent = message || 'Обновление моделей…';
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

        function formatStats(linksAdded, synced, total) {
            var parts = [];
            if (linksAdded > 0) {
                parts.push('новых связей: ' + linksAdded);
            }
            if (total > 0) {
                parts.push('модели: ' + synced + ' из ' + total);
            }
            return parts.join(' · ');
        }

        function runSync(ctx) {
            return postStage(actionUrl, 'sync').then(function (data) {
                var total = data.totalModels || ctx.totalModels || 0;
                var synced = data.syncedModels || 0;
                var linksAdded = data.linksAdded != null ? data.linksAdded : ctx.linksAdded;
                var percent = total > 0 ? Math.round((synced / total) * 100) : 100;

                updateProgress(
                    data.message || 'Синхронизация моделей…',
                    percent,
                    formatStats(linksAdded, synced, total)
                );

                if (data.done) {
                    return data;
                }
                if (data.needsSync) {
                    return runSync({
                        totalModels: total,
                        linksAdded: linksAdded,
                    });
                }

                throw new Error('Неожиданный ответ сервера');
            });
        }

        function runLinkAll() {
            if (busy) {
                return;
            }
            busy = true;
            setLoading(true);
            updateProgress('Добавление связей коллекций…', 0, '');

            postStage(actionUrl, 'init')
                .then(function (data) {
                    if (data.done && data.redirect) {
                        updateProgress(data.message || 'Готово', 100, formatStats(data.linksAdded || 0, 0, 0));
                        window.location.href = data.redirect;
                        return;
                    }
                    if (!data.needsSync) {
                        throw new Error('Не удалось начать синхронизацию');
                    }

                    updateProgress(
                        data.message || 'Синхронизация…',
                        0,
                        formatStats(data.linksAdded || 0, 0, data.totalModels || 0)
                    );

                    return runSync({
                        totalModels: data.totalModels || 0,
                        linksAdded: data.linksAdded || 0,
                    }).then(function (finishData) {
                        if (finishData.redirect) {
                            window.location.href = finishData.redirect;
                        } else {
                            window.location.reload();
                        }
                    });
                })
                .catch(function (error) {
                    alert(error.message || 'Ошибка обновления моделей');
                    setLoading(false);
                    busy = false;
                });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var confirmMessage = submitButton
                ? submitButton.getAttribute('data-confirm')
                : null;
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            runLinkAll();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFabricLinkAllModels);
    } else {
        initFabricLinkAllModels();
    }
})();
