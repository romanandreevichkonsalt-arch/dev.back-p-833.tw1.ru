(function () {
    var POLL_INTERVAL_MS = 1500;
    var SUCCESS_CLOSE_MS = 1800;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function csrfParam() {
        var meta = document.querySelector('meta[name="csrf-param"]');
        return meta ? meta.getAttribute('content') : '_csrf';
    }

    function postFormData(url, formData) {
        var headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-Token': csrfToken(),
        };
        headers[csrfParam()] = csrfToken();

        return fetch(url, {
            method: 'POST',
            body: formData,
            headers: headers,
            credentials: 'same-origin',
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.message || 'Ошибка запроса');
                }
                return payload;
            });
        });
    }

    function getJson(url) {
        return fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.message || 'Ошибка запроса');
                }
                return payload;
            });
        });
    }

    function formatStats(stats) {
        if (!stats) {
            return '';
        }
        var parts = [];
        if (stats.models_created) {
            parts.push('создано: ' + stats.models_created);
        }
        if (stats.models_updated) {
            parts.push('обновлено: ' + stats.models_updated);
        }
        if (stats.models_skipped) {
            parts.push('пропущено: ' + stats.models_skipped);
        }
        if (stats.errors) {
            parts.push('ошибок: ' + stats.errors);
        }
        return parts.join(' · ');
    }

    function initModelImportProgress() {
        var root = document.querySelector('[data-model-import]');
        if (!root || root.getAttribute('data-model-import-bound') === '1') {
            return;
        }

        var form = root.querySelector('[data-model-import-form]');
        var loader = root.querySelector('[data-model-import-loader]');
        var submitButton = root.querySelector('[data-model-import-submit]');
        if (!form || !loader || !submitButton) {
            return;
        }

        var startUrl = root.getAttribute('data-import-start-url');
        var statusUrlTemplate = root.getAttribute('data-import-status-url');
        var resolveUrl = root.getAttribute('data-import-resolve-url');
        if (!startUrl || !statusUrlTemplate || !resolveUrl) {
            return;
        }

        var spinner = loader.querySelector('[data-model-import-spinner]');
        var successIcon = loader.querySelector('[data-model-import-success]');
        var progressText = loader.querySelector('[data-model-import-progress-text]');
        var progressBar = loader.querySelector('[data-model-import-progress-bar]');
        var progressStats = loader.querySelector('[data-model-import-progress-stats]');
        var progressHint = loader.querySelector('[data-model-import-progress-hint]');
        var progressWrap = loader.querySelector('.admin-import-progress');
        var conflictPanel = loader.querySelector('[data-model-import-conflict]');
        var pollTimer = null;
        var closeTimer = null;
        var importFinished = false;

        function statusUrl(runId) {
            return statusUrlTemplate.replace('__RUN_ID__', String(runId));
        }

        function resetLoaderView() {
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
            if (progressHint) {
                progressHint.hidden = false;
            }
        }

        function setLoading(active) {
            if (!active) {
                loader.hidden = true;
                loader.setAttribute('hidden', 'hidden');
                loader.style.display = 'none';
            } else {
                loader.hidden = false;
                loader.removeAttribute('hidden');
                loader.style.display = '';
            }
            submitButton.disabled = active;
            submitButton.setAttribute('aria-busy', active ? 'true' : 'false');
        }

        function updateProgressView(data) {
            var processed = data.processed || 0;
            var total = data.total || 0;
            var percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;

            if (progressText) {
                progressText.textContent = data.message || ('Обработано ' + processed + ' из ' + total);
            }
            if (progressBar) {
                progressBar.style.width = percent + '%';
                progressBar.setAttribute('aria-valuenow', String(percent));
            }
            if (progressStats) {
                progressStats.textContent = formatStats(data.stats);
            }
        }

        function stopPolling() {
            if (pollTimer !== null) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function clearCloseTimer() {
            if (closeTimer !== null) {
                clearTimeout(closeTimer);
                closeTimer = null;
            }
        }

        function isCompleted(data) {
            if (data.status === 'awaiting_conflict' || data.status === 'failed' || data.status === 'aborted') {
                return false;
            }

            return data.status === 'completed'
                || data.phase === 'done'
                || data.success === true
                || (data.total > 0 && data.processed >= data.total);
        }

        function showSuccessState(data) {
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
            if (progressHint) {
                progressHint.hidden = true;
            }
            if (progressText) {
                progressText.textContent = data.message || 'Импорт завершён';
            }
            if (progressStats) {
                progressStats.textContent = formatStats(data.stats);
            }
        }

        function showConflict(runId, conflict) {
            if (!conflictPanel) {
                return;
            }
            conflictPanel.hidden = false;
            var collectionEl = conflictPanel.querySelector('[data-conflict-collection]');
            var modelEl = conflictPanel.querySelector('[data-conflict-model]');
            if (collectionEl) {
                collectionEl.textContent = conflict.collection_name || '—';
            }
            if (modelEl) {
                modelEl.textContent = conflict.model_label || '—';
            }

            conflictPanel.querySelectorAll('[data-conflict-action]').forEach(function (button) {
                button.onclick = function () {
                    var action = button.getAttribute('data-conflict-action');
                    var body = new FormData();
                    body.append('run_id', String(runId));
                    body.append('conflict_action', action);
                    body.append(csrfParam(), csrfToken());
                    conflictPanel.hidden = true;
                    postFormData(resolveUrl, body)
                        .then(function () {
                            pollRun(runId);
                        })
                        .catch(function (error) {
                            alert(error.message);
                            conflictPanel.hidden = false;
                        });
                };
            });
        }

        function finishImport(data) {
            if (importFinished) {
                return;
            }

            stopPolling();
            clearCloseTimer();
            updateProgressView(data);

            if (isCompleted(data)) {
                importFinished = true;
                showSuccessState(data);
                closeTimer = window.setTimeout(function () {
                    setLoading(false);
                    resetLoaderView();
                    importFinished = false;
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 100);
                }, SUCCESS_CLOSE_MS);
                return;
            }

            if (data.status === 'failed') {
                importFinished = true;
                alert(data.errorMessage || 'Импорт завершился с ошибкой.');
                setLoading(false);
                resetLoaderView();
                importFinished = false;
                return;
            }

            setLoading(false);
            resetLoaderView();
        }

        function handleStatus(runId, data) {
            updateProgressView(data);

            if (data.status === 'awaiting_conflict') {
                stopPolling();
                showConflict(runId, data.pendingConflict || {});
                return;
            }

            if (isCompleted(data) || data.status === 'failed' || data.status === 'aborted') {
                finishImport(data);
            }
        }

        function pollRun(runId) {
            stopPolling();
            clearCloseTimer();
            importFinished = false;
            resetLoaderView();
            setLoading(true);
            if (conflictPanel) {
                conflictPanel.hidden = true;
            }

            var tick = function () {
                getJson(statusUrl(runId))
                    .then(function (data) {
                        handleStatus(runId, data);
                    })
                    .catch(function (error) {
                        stopPolling();
                        alert(error.message);
                        setLoading(false);
                        resetLoaderView();
                    });
            };

            tick();
            pollTimer = setInterval(tick, POLL_INTERVAL_MS);
        }

        function startImport() {
            var fileInput = form.querySelector('[name="price_list_file"]');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                fileInput.focus();
                return;
            }

            if (!fileInput.reportValidity || !fileInput.reportValidity()) {
                return;
            }

            var formData = new FormData(form);
            clearCloseTimer();
            resetLoaderView();
            setLoading(true);
            updateProgressView({ processed: 0, total: 0, message: 'Загрузка файла…' });

            postFormData(startUrl, formData)
                .then(function (payload) {
                    pollRun(payload.runId);
                })
                .catch(function (error) {
                    alert(error.message);
                    setLoading(false);
                    resetLoaderView();
                });
        }

        submitButton.addEventListener('click', startImport);

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            startImport();
        });

        root.setAttribute('data-model-import-bound', '1');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModelImportProgress);
    } else {
        initModelImportProgress();
    }
})();
