/**
 * История поиска в localStorage (без серверного API).
 * Запись — только при submit или клике по результату, не при autocomplete.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'searchHistory';
    var MAX_ITEMS = 20;
    var MIN_LENGTH = 3;

    function normalize(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .replace(/ё/g, 'е');
    }

    function readHistory() {
        try {
            var raw = global.localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return [];
            }

            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed.filter(function (item) {
                return typeof item === 'string' && item.trim() !== '';
            });
        } catch (error) {
            return [];
        }
    }

    function writeHistory(items) {
        try {
            global.localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, MAX_ITEMS)));
        } catch (error) {
            // quota exceeded or private mode
        }
    }

    function addSearchHistory(query) {
        var trimmed = String(query || '').trim();
        if (trimmed.length < MIN_LENGTH) {
            return readHistory();
        }

        var normalized = normalize(trimmed);
        var history = readHistory().filter(function (item) {
            return normalize(item) !== normalized;
        });

        history.unshift(trimmed);
        writeHistory(history);

        return history;
    }

    function getSearchHistory() {
        return readHistory();
    }

    function clearSearchHistory() {
        writeHistory([]);
        return [];
    }

    global.SearchHistory = {
        add: addSearchHistory,
        get: getSearchHistory,
        clear: clearSearchHistory,
        STORAGE_KEY: STORAGE_KEY,
        MAX_ITEMS: MAX_ITEMS,
        MIN_LENGTH: MIN_LENGTH,
    };
}(typeof window !== 'undefined' ? window : this));
