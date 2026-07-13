/**
 * RatNotes Frontend JavaScript
 *
 * @package RatNotes
 */

(function($) {
    'use strict';

    /**
     * Main application object.
     */
    const RatNotesFrontend = {
        notes: [],
        categories: [],
        currentNote: null,
        selectedModalCategoryIds: [],
        currentStatus: 'active',
        currentCategory: 'all',
        isLoading: false,
        isOffline: false,
        pendingOperationsCount: 0,
        isSyncingQueue: false,
        sessionExpired: false,
        syncNotice: '',
        syncNoticeType: 'info',
        syncNoticeTimeoutId: null,
        installHintDismissed: false,
        db: null,
        dbReadyPromise: null,
        $container: null,

        /**
         * Initialize the application.
         */
        init: function() {
            this.$container = $('.ratnotes-frontend');
            if (!this.$container.length) return;

            this.currentStatus = this.$container.data('status') || 'active';
            this.registerServiceWorker();
            this.dbReadyPromise = this.initIndexedDB();
            this.bindConnectivityEvents();
            this.ensureOfflineBanner();
            this.ensureInstallHint();
            this.bindEvents();
            this.loadCategories();
            this.loadNotes();
            this.updateInstallHint();
        },

        /**
         * Detect if current device is iOS.
         */
        isIOSDevice: function() {
            const ua = window.navigator.userAgent || '';
            const platform = window.navigator.platform || '';
            const maxTouchPoints = window.navigator.maxTouchPoints || 0;

            const iOSPlatform = /iPad|iPhone|iPod/.test(platform);
            const iOSUserAgent = /iPad|iPhone|iPod/.test(ua);
            const iPadOSDesktopMode = platform === 'MacIntel' && maxTouchPoints > 1;

            return iOSPlatform || iOSUserAgent || iPadOSDesktopMode;
        },

        /**
         * Detect whether app is running in standalone mode.
         */
        isStandaloneDisplay: function() {
            const mediaStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
            const navigatorStandalone = window.navigator.standalone === true;
            return mediaStandalone || navigatorStandalone;
        },

        /**
         * Create iOS install hint container if missing.
         */
        ensureInstallHint: function() {
            if (this.$container.find('.ratnotes-frontend-install-hint').length) return;

            this.installHintDismissed = localStorage.getItem('ratnotes_ios_install_hint_dismissed') === '1';

            this.$container.prepend(
                '<div class="ratnotes-frontend-install-hint" style="display:none;">' +
                    '<div class="ratnotes-frontend-install-hint-text">On iPhone/iPad: tap Share, then Add to Home Screen for app-like access.</div>' +
                    '<button type="button" class="ratnotes-frontend-install-hint-close" aria-label="Dismiss install hint">&times;</button>' +
                '</div>'
            );
        },

        /**
         * Show install guidance only where it helps (iOS Safari, not standalone).
         */
        updateInstallHint: function() {
            const $hint = this.$container.find('.ratnotes-frontend-install-hint');
            if (!$hint.length) return;

            if (this.installHintDismissed) {
                $hint.hide();
                return;
            }

            const shouldShow = this.isIOSDevice() && !this.isStandaloneDisplay();
            if (shouldShow) {
                $hint.show();
            } else {
                $hint.hide();
            }
        },

        /**
         * Register service worker for archive scope.
         */
        registerServiceWorker: function() {
            if (!('serviceWorker' in navigator)) return;
            if (!ratnotesFrontendData?.serviceWorkerUrl) return;

            window.addEventListener('load', () => {
                navigator.serviceWorker
                    .register(ratnotesFrontendData.serviceWorkerUrl, {
                        scope: ratnotesFrontendData.serviceWorkerScope || '/ratnotes-archive/'
                    })
                    .catch((error) => {
                        console.error('Service worker registration failed:', error);
                    });
            });
        },

            /**
             * Bind online/offline listeners so the UI can show stale/offline state.
             */
            bindConnectivityEvents: function() {
                window.addEventListener('online', () => {
                    this.isOffline = false;
                    this.updateOfflineBanner();
                    this.processQueuedOperations().finally(() => {
                        this.loadCategories();
                        this.loadNotes();
                    });
                });

                window.addEventListener('offline', () => {
                    this.isOffline = true;
                    this.updateOfflineBanner();
                });

                this.isOffline = !navigator.onLine;
                this.updateOfflineBanner();
            },

            /**
             * Initialize IndexedDB for offline cache and operation queue.
             */
            initIndexedDB: function() {
                return new Promise((resolve) => {
                if (!('indexedDB' in window)) {
                    resolve(false);
                    return;
                }

                const request = indexedDB.open('ratnotes_pwa', 1);

                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    if (!db.objectStoreNames.contains('notes')) {
                        db.createObjectStore('notes', { keyPath: 'cacheKey' });
                    }
                    if (!db.objectStoreNames.contains('categories')) {
                        db.createObjectStore('categories', { keyPath: 'cacheKey' });
                    }
                    if (!db.objectStoreNames.contains('operations')) {
                        db.createObjectStore('operations', { keyPath: 'opId' });
                    }
                };

                request.onsuccess = () => {
                    this.db = request.result;
                    this.refreshPendingOperationsCount();
                    this.processQueuedOperations();
                    resolve(true);
                };

                request.onerror = () => {
                    console.warn('IndexedDB unavailable for RatNotes offline mode.');
                    resolve(false);
                };
                });
            },

            /**
             * Make sure an offline banner exists in the DOM.
             */
            ensureOfflineBanner: function() {
                if (this.$container.find('.ratnotes-frontend-offline-banner').length) return;

                this.$container.prepend(
                    '<div class="ratnotes-frontend-offline-banner" style="display:none;">Offline mode - showing cached notes</div>'
                );
            },

            /**
             * Toggle the offline banner based on current connectivity.
             */
            updateOfflineBanner: function() {
                const $banner = this.$container.find('.ratnotes-frontend-offline-banner');
                if (!$banner.length) return;

                if (this.sessionExpired) {
                    const pendingText = this.pendingOperationsCount > 0
                        ? ` (${this.pendingOperationsCount} queued change${this.pendingOperationsCount === 1 ? '' : 's'})`
                        : '';
                    $banner
                        .removeClass('notice-info notice-success')
                        .addClass('notice-error')
                        .text(`Session expired - refresh and log in again${pendingText}`)
                        .show();
                    return;
                }

                if (this.syncNotice) {
                    $banner
                        .removeClass('notice-info notice-success notice-error')
                        .addClass(`notice-${this.syncNoticeType}`)
                        .text(this.syncNotice)
                        .show();
                    return;
                }

                if (this.isOffline || this.pendingOperationsCount > 0 || this.isSyncingQueue) {
                    const pendingText = this.pendingOperationsCount > 0
                        ? ` (${this.pendingOperationsCount} change${this.pendingOperationsCount === 1 ? '' : 's'} pending sync)`
                        : '';
                    const modeText = this.isOffline ? 'Offline mode' : (this.isSyncingQueue ? 'Syncing changes' : 'Back online');
                    $banner
                        .removeClass('notice-success notice-error')
                        .addClass('notice-info')
                        .text(`${modeText} - showing cached notes${pendingText}`)
                        .show();
                } else {
                    $banner.hide();
                }
            },

            /**
             * Show temporary sync/auth feedback in the banner.
             */
            setSyncNotice: function(message, type = 'info', autoHideMs = 0) {
                if (this.syncNoticeTimeoutId) {
                    clearTimeout(this.syncNoticeTimeoutId);
                    this.syncNoticeTimeoutId = null;
                }

                this.syncNotice = message;
                this.syncNoticeType = type;
                this.updateOfflineBanner();

                if (autoHideMs > 0) {
                    this.syncNoticeTimeoutId = setTimeout(() => {
                        this.syncNotice = '';
                        this.syncNoticeType = 'info';
                        this.syncNoticeTimeoutId = null;
                        this.updateOfflineBanner();
                    }, autoHideMs);
                }
            },

            /**
             * Sleep helper used for retry backoff.
             */
            sleep: function(ms) {
                return new Promise((resolve) => setTimeout(resolve, ms));
            },

            /**
             * Promise wrapper around jQuery ajax with full error metadata.
             */
            performAjaxRequest: function(options) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        timeout: 12000,
                        ...options,
                        success: (data) => resolve(data),
                        error: (jqXHR, textStatus, errorThrown) => reject({ jqXHR, textStatus, errorThrown })
                    });
                });
            },

            /**
             * Detect expired nonce/session errors.
             */
            isAuthFailure: function(errorOrResponse) {
                if (errorOrResponse === '-1' || errorOrResponse === -1) {
                    return true;
                }

                if (errorOrResponse?.success === false) {
                    const message = String(errorOrResponse?.data?.message || '').toLowerCase();
                    if (message.includes('session') || message.includes('nonce') || message.includes('logged in')) {
                        return true;
                    }
                }

                const jqXHR = errorOrResponse?.jqXHR || errorOrResponse;
                const status = jqXHR?.status;
                const bodyText = String(jqXHR?.responseText || '').trim();

                if (status === 401 || status === 403) {
                    return true;
                }

                return bodyText === '-1';
            },

            /**
             * Detect transient network/server failures eligible for retry.
             */
            isTransientFailure: function(error) {
                const jqXHR = error?.jqXHR || error;
                const status = jqXHR?.status;
                const textStatus = error?.textStatus;

                if (textStatus === 'timeout') {
                    return true;
                }

                return [0, 408, 429, 500, 502, 503, 504].includes(status);
            },

            /**
             * Fetch and store a fresh frontend nonce.
             */
            refreshFrontendNonce: async function() {
                if (!navigator.onLine) {
                    return false;
                }

                try {
                    const response = await this.performAjaxRequest({
                        url: ratnotesFrontendData.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ratnotes_refresh_nonce'
                        }
                    });

                    if (response?.success && response?.data?.nonce) {
                        ratnotesFrontendData.nonce = response.data.nonce;
                        this.sessionExpired = false;
                        return true;
                    }
                } catch (error) {
                    console.error('Failed to refresh RatNotes nonce:', error);
                }

                this.sessionExpired = true;
                this.setSyncNotice('Session expired - refresh and log in again.', 'error');
                return false;
            },

            /**
             * Resilient AJAX helper with retry and one-time nonce refresh.
             */
            ajaxRequest: async function(data, options = {}) {
                const retries = Number.isInteger(options.retries) ? options.retries : 2;
                const shouldRefreshNonce = options.refreshNonce !== false;
                const payload = { ...data };
                let refreshedNonce = false;

                for (let attempt = 0; attempt <= retries; attempt++) {
                    try {
                        const response = await this.performAjaxRequest({
                            url: ratnotesFrontendData.ajaxUrl,
                            type: 'POST',
                            data: payload
                        });

                        if (this.isAuthFailure(response)) {
                            throw response;
                        }

                        if (this.sessionExpired) {
                            this.sessionExpired = false;
                        }

                        if (this.syncNoticeType === 'error' && this.syncNotice.includes('Session expired')) {
                            this.syncNotice = '';
                            this.syncNoticeType = 'info';
                        }

                        return response;
                    } catch (error) {
                        if (this.isAuthFailure(error)) {
                            if (shouldRefreshNonce && !refreshedNonce) {
                                const nonceRefreshed = await this.refreshFrontendNonce();
                                if (nonceRefreshed) {
                                    payload.nonce = ratnotesFrontendData.nonce;
                                    refreshedNonce = true;
                                    continue;
                                }
                            }

                            this.sessionExpired = true;
                            this.setSyncNotice('Session expired - refresh and log in again.', 'error');
                            throw error;
                        }

                        if (this.isTransientFailure(error) && attempt < retries) {
                            await this.sleep(300 * (attempt + 1));
                            continue;
                        }

                        throw error;
                    }
                }

                throw new Error('RatNotes request failed after retries.');
            },

            /**
             * Build a local cache key for a specific notes query.
             */
            getNotesCacheKey: function() {
                const searchValue = this.$container.find('.ratnotes-frontend-search-input').val() || '';
                return [
                    'ratnotes_notes',
                    this.currentStatus,
                    this.currentCategory === 'all' ? 'all' : this.currentCategory,
                    searchValue.trim().toLowerCase()
                ].join('::');
            },

            /**
             * Persist data in localStorage if available.
             */
            cacheData: function(key, value) {
                try {
                    localStorage.setItem(key, JSON.stringify(value));
                } catch (error) {
                    console.warn('RatNotes cache write failed:', error);
                }
            },

            /**
             * Read cached data from localStorage if available.
             */
            readCache: function(key) {
                try {
                    const rawValue = localStorage.getItem(key);
                    return rawValue ? JSON.parse(rawValue) : null;
                } catch (error) {
                    console.warn('RatNotes cache read failed:', error);
                    return null;
                }
            },

            /**
             * Fallback rendering for cached notes/categories when offline.
             */
            renderCachedState: function() {
                const cachedNotes = this.readCache(this.getNotesCacheKey());
                if (Array.isArray(cachedNotes)) {
                    this.notes = cachedNotes;
                    this.renderNotes();
                    this.isOffline = true;
                    this.updateOfflineBanner();
                    return true;
                }

                const cachedCategories = this.readCache('ratnotes_categories');
                if (Array.isArray(cachedCategories)) {
                    this.categories = cachedCategories;
                    this.renderCategories();
                    this.updateSelectedCategoryDisplay();
                    this.renderModalCategoryDropdown();
                    this.updateModalCategoryTriggerText();
                    this.isOffline = true;
                    this.updateOfflineBanner();
                    return true;
                }

                return false;
            },

        /**
         * Promise wrapper for IndexedDB requests.
         */
        idbRequest: function(request) {
            return new Promise((resolve, reject) => {
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        },

        /**
         * Read notes cache from IndexedDB.
         */
        readNotesFromIndexedDB: async function(cacheKey) {
            if (!this.db) return null;
            try {
                const tx = this.db.transaction('notes', 'readonly');
                const store = tx.objectStore('notes');
                const record = await this.idbRequest(store.get(cacheKey));
                return record?.items || null;
            } catch (error) {
                console.warn('Failed to read notes cache from IndexedDB:', error);
                return null;
            }
        },

        /**
         * Write notes cache to IndexedDB.
         */
        writeNotesToIndexedDB: async function(cacheKey, items) {
            if (!this.db) return;
            try {
                const tx = this.db.transaction('notes', 'readwrite');
                const store = tx.objectStore('notes');
                await this.idbRequest(store.put({ cacheKey, items, updatedAt: Date.now() }));
                await this.idbRequest(store.put({ cacheKey: 'ratnotes_notes::last', items, updatedAt: Date.now() }));
            } catch (error) {
                console.warn('Failed to write notes cache to IndexedDB:', error);
            }
        },

        /**
         * Read the latest notes cache entry from IndexedDB.
         */
        readLatestNotesFromIndexedDB: async function() {
            if (!this.db) return null;
            try {
                const tx = this.db.transaction('notes', 'readonly');
                const store = tx.objectStore('notes');

                const latestRecord = await this.idbRequest(store.get('ratnotes_notes::last'));
                if (latestRecord?.items && Array.isArray(latestRecord.items)) {
                    return latestRecord.items;
                }

                const allRecords = await this.idbRequest(store.getAll());
                if (!Array.isArray(allRecords) || allRecords.length === 0) {
                    return null;
                }

                allRecords.sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
                return Array.isArray(allRecords[0]?.items) ? allRecords[0].items : null;
            } catch (error) {
                console.warn('Failed to read latest notes cache from IndexedDB:', error);
                return null;
            }
        },

        /**
         * Read categories cache from IndexedDB.
         */
        readCategoriesFromIndexedDB: async function() {
            if (!this.db) return null;
            try {
                const tx = this.db.transaction('categories', 'readonly');
                const store = tx.objectStore('categories');
                const record = await this.idbRequest(store.get('all'));
                return record?.items || null;
            } catch (error) {
                console.warn('Failed to read category cache from IndexedDB:', error);
                return null;
            }
        },

        /**
         * Write categories cache to IndexedDB.
         */
        writeCategoriesToIndexedDB: async function(items) {
            if (!this.db) return;
            try {
                const tx = this.db.transaction('categories', 'readwrite');
                const store = tx.objectStore('categories');
                await this.idbRequest(store.put({ cacheKey: 'all', items, updatedAt: Date.now() }));
            } catch (error) {
                console.warn('Failed to write category cache to IndexedDB:', error);
            }
        },

        /**
         * Read queued operations from IndexedDB.
         */
        getQueuedOperations: async function() {
            if (!this.db) return [];
            try {
                const tx = this.db.transaction('operations', 'readonly');
                const store = tx.objectStore('operations');
                const operations = await this.idbRequest(store.getAll());
                return (operations || []).sort((a, b) => (a.queuedAt || 0) - (b.queuedAt || 0));
            } catch (error) {
                console.warn('Failed to read queued operations:', error);
                return [];
            }
        },

        /**
         * Add or replace a queued operation.
         */
        putQueuedOperation: async function(operation) {
            if (!this.db) return;
            try {
                const tx = this.db.transaction('operations', 'readwrite');
                const store = tx.objectStore('operations');
                await this.idbRequest(store.put(operation));
            } catch (error) {
                console.warn('Failed to queue operation:', error);
            }
        },

        /**
         * Remove one queued operation.
         */
        removeQueuedOperation: async function(opId) {
            if (!this.db) return;
            try {
                const tx = this.db.transaction('operations', 'readwrite');
                const store = tx.objectStore('operations');
                await this.idbRequest(store.delete(opId));
            } catch (error) {
                console.warn('Failed to remove queued operation:', error);
            }
        },

        /**
         * Remove queued operations for a specific note key.
         */
        removeQueuedOperationsByNoteKey: async function(noteKey) {
            if (!this.db || !noteKey) return;
            const operations = await this.getQueuedOperations();
            const matching = operations.filter((op) => String(op.noteKey) === String(noteKey));
            for (const op of matching) {
                await this.removeQueuedOperation(op.opId);
            }
        },

        /**
         * Replace temp note keys in remaining queue after create succeeds.
         */
        replaceQueuedNoteKey: async function(fromKey, toKey) {
            if (!this.db) return;
            const operations = await this.getQueuedOperations();
            for (const op of operations) {
                if (String(op.noteKey) !== String(fromKey)) continue;
                op.noteKey = toKey;
                if (op.data && (op.data.id === 0 || String(op.data.id) === String(fromKey))) {
                    op.data.id = toKey;
                }
                await this.putQueuedOperation(op);
            }
        },

        /**
         * Refresh queued operation count and banner text.
         */
        refreshPendingOperationsCount: async function() {
            const operations = await this.getQueuedOperations();
            this.pendingOperationsCount = operations.length;
            this.updateOfflineBanner();
        },

        /**
         * Queue an operation with last-write-wins behavior per note.
         */
        enqueueOperation: async function(operation) {
            const op = {
                opId: operation.opId || `op-${Date.now()}-${Math.random().toString(16).slice(2)}`,
                queuedAt: Date.now(),
                ...operation
            };

            if (op.noteKey) {
                await this.removeQueuedOperationsByNoteKey(op.noteKey);
            }

            const isUnsyncedLocalDelete = op.type === 'delete' && String(op.noteKey).startsWith('local-');
            if (!isUnsyncedLocalDelete) {
                await this.putQueuedOperation(op);
            }

            await this.refreshPendingOperationsCount();
            this.setSyncNotice('Change queued. It will sync automatically when online.', 'info', 2200);
        },

        /**
         * Build category display payload from selected category IDs.
         */
        buildCategoryDataFromIds: function(categoryIds) {
            return (categoryIds || []).map((id) => {
                const match = this.categories.find((category) => String(category.id) === String(id));
                return {
                    id: Number(id),
                    name: match ? match.name : `Category ${id}`
                };
            });
        },

        /**
         * Upsert a note locally in memory and caches.
         */
        upsertLocalNote: async function(note) {
            const index = this.notes.findIndex((item) => String(item.id) === String(note.id));
            if (index >= 0) {
                this.notes[index] = { ...this.notes[index], ...note };
            } else {
                this.notes.unshift(note);
            }

            this.renderNotes();
            const cacheKey = this.getNotesCacheKey();
            this.cacheData(cacheKey, this.notes);
            this.cacheData('ratnotes_notes_last', this.notes);
            await this.writeNotesToIndexedDB(cacheKey, this.notes);
        },

        /**
         * Remove a note locally in memory and caches.
         */
        removeLocalNote: async function(noteId) {
            this.notes = this.notes.filter((item) => String(item.id) !== String(noteId));
            this.renderNotes();
            const cacheKey = this.getNotesCacheKey();
            this.cacheData(cacheKey, this.notes);
            this.cacheData('ratnotes_notes_last', this.notes);
            await this.writeNotesToIndexedDB(cacheKey, this.notes);
        },

        /**
         * Render queued local drafts if no notes cache is available.
         */
        renderQueuedDraftNotes: async function() {
            const operations = await this.getQueuedOperations();
            const saveOperations = (operations || []).filter((op) => op.type === 'save' && op.data);
            if (!saveOperations.length) {
                return false;
            }

            const draftsById = new Map();
            for (const operation of saveOperations) {
                const data = operation.data || {};
                const draftId = operation.noteKey || data.id || `local-${operation.opId}`;
                draftsById.set(String(draftId), {
                    id: draftId,
                    title: data.title || '',
                    content: data.content || '',
                    is_pinned: !!data.is_pinned,
                    is_archived: !!data.is_archived,
                    is_trashed: false,
                    categories: this.buildCategoryDataFromIds(data.category_ids || []),
                    labels: [],
                    created_at: new Date(operation.queuedAt || Date.now()).toISOString(),
                    updated_at: new Date(operation.queuedAt || Date.now()).toISOString(),
                    pending_sync: true
                });
            }

            this.notes = Array.from(draftsById.values()).sort(
                (a, b) => new Date(b.updated_at) - new Date(a.updated_at)
            );
            this.renderNotes();
            return true;
        },

        /**
         * Try all offline note fallbacks before failing.
         */
        tryRenderOfflineNotes: async function(cacheKey) {
            if (!this.db && this.dbReadyPromise) {
                await this.dbReadyPromise;
            }

            const idbNotes = await this.readNotesFromIndexedDB(cacheKey);
            if (Array.isArray(idbNotes)) {
                this.notes = idbNotes;
                this.renderNotes();
                return true;
            }

            const latestIdbNotes = await this.readLatestNotesFromIndexedDB();
            if (Array.isArray(latestIdbNotes)) {
                this.notes = latestIdbNotes;
                this.renderNotes();
                return true;
            }

            const latestLocalNotes = this.readCache('ratnotes_notes_last');
            if (Array.isArray(latestLocalNotes)) {
                this.notes = latestLocalNotes;
                this.renderNotes();
                return true;
            }

            if (this.renderCachedState()) {
                return true;
            }

            return this.renderQueuedDraftNotes();
        },

        /**
         * Process queued operations once back online.
         */
        processQueuedOperations: async function() {
            if (!navigator.onLine || this.isSyncingQueue) return;
            if (!this.db) return;

            this.isSyncingQueue = true;
            this.sessionExpired = false;
            this.setSyncNotice('Syncing queued changes...', 'info');
            const operations = await this.getQueuedOperations();

            for (const operation of operations) {
                try {
                    if (operation.type === 'save') {
                        const response = await this.ajaxRequest(operation.data, { retries: 2, refreshNonce: true });

                        if (!response.success) {
                            throw new Error(response.data?.message || 'Save replay failed');
                        }

                        const saved = response.data;
                        if (String(operation.noteKey).startsWith('local-')) {
                            await this.removeLocalNote(operation.noteKey);
                            await this.replaceQueuedNoteKey(operation.noteKey, saved.id);
                        }

                        await this.upsertLocalNote(saved);
                        await this.removeQueuedOperation(operation.opId);
                    }

                    if (operation.type === 'delete') {
                        const response = await this.ajaxRequest(operation.data, { retries: 2, refreshNonce: true });

                        if (!response.success) {
                            throw new Error(response.data?.message || 'Delete replay failed');
                        }

                        await this.removeLocalNote(operation.noteKey || operation.data.id);
                        await this.removeQueuedOperation(operation.opId);
                    }

                    if (operation.type === 'restore') {
                        const response = await this.ajaxRequest(operation.data, { retries: 2, refreshNonce: true });

                        if (!response.success) {
                            throw new Error(response.data?.message || 'Restore replay failed');
                        }

                        await this.removeQueuedOperation(operation.opId);
                    }
                } catch (error) {
                    console.error('Queued operation replay failed:', error);
                    break;
                }
            }

            this.isSyncingQueue = false;
            await this.refreshPendingOperationsCount();

            if (this.pendingOperationsCount === 0) {
                this.setSyncNotice('All queued changes synced.', 'success', 2500);
            }
        },

        /**
         * Bind event listeners.
         */
        bindEvents: function() {
            // Navigation
            this.$container.on('click', '.ratnotes-frontend-nav-item', (e) => this.handleNavClick(e));

            // Search
            this.$container.on('input', '.ratnotes-frontend-search-input', (e) => this.handleSearch(e));
            this.$container.on('search', '.ratnotes-frontend-search-input', (e) => this.handleSearch(e));

            // Create buttons
            this.$container.on('click', '.ratnotes-frontend-create-btn', () => this.openModal());
            this.$container.on('click', '.ratnotes-frontend-category-create-btn', () => this.openModalFromCurrentCategory());

            // Sidebar
            this.$container.on('click', '.ratnotes-frontend-menu-toggle', () => this.toggleSidebar(true));
            this.$container.on('click', '.ratnotes-frontend-sidebar-close, .ratnotes-frontend-sidebar-overlay', () => this.toggleSidebar(false));
            this.$container.on('click', '.ratnotes-frontend-category-item', (e) => this.handleCategoryClick(e));
            this.$container.on('click', '.ratnotes-frontend-clear-category', (e) => this.clearCategoryFilter(e));
            this.$container.on('submit', '.ratnotes-frontend-category-create-form', (e) => this.handleCreateCategory(e));

            // Modal close (X button) - saves note
            this.$container.on('click', '.ratnotes-frontend-modal-close', () => this.saveNote());

            // Clicking overlay - saves note
            this.$container.on('click', '.ratnotes-frontend-modal-overlay', () => this.saveNote());

            // Save button
            this.$container.on('click', '.ratnotes-frontend-save-btn', () => this.saveNote());

            // Delete button
            this.$container.on('click', '.ratnotes-frontend-delete-btn', () => this.deleteNote());

            // Restore link on trash cards
            this.$container.on('click', '.ratnotes-frontend-note-restore', (e) => this.restoreFromTrash(e));

            // Archive button
            this.$container.on('click', '.ratnotes-frontend-archive-btn', () => this.archiveNote());

            // Pin button
            this.$container.on('click', '.ratnotes-frontend-pin-btn', () => this.togglePin());

            // Modal categories dropdown
            this.$container.on('click', '.ratnotes-frontend-category-trigger', (e) => this.toggleModalCategoryDropdown(e));
            this.$container.on('change', '.ratnotes-frontend-category-option input[type="checkbox"]', (e) => this.handleModalCategoryChange(e));

            // Close category dropdown when clicking outside it
            $(document).on('click', (e) => {
                const $target = $(e.target);
                if (!$target.closest('.ratnotes-frontend-category-picker').length) {
                    this.$container.find('.ratnotes-frontend-category-picker').removeClass('open');
                }
            });

            // Note card click
            this.$container.on('click', '.ratnotes-frontend-note', (e) => {
                if (!$(e.target).closest('.ratnotes-frontend-actions, .ratnotes-frontend-note-restore').length) {
                    const noteId = $(e.currentTarget).data('id');
                    this.openModal(noteId);
                }
            });

            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboard(e));

            // Dismiss install hint
            this.$container.on('click', '.ratnotes-frontend-install-hint-close', () => {
                this.installHintDismissed = true;
                localStorage.setItem('ratnotes_ios_install_hint_dismissed', '1');
                this.updateInstallHint();
            });
        },

        /**
         * Load notes from API.
         */
        loadNotes: async function() {
            if (this.isLoading) return;

            this.isLoading = true;
            this.$container.find('.ratnotes-frontend-loading').show();

            const cacheKey = this.getNotesCacheKey();

            try {
                if (!navigator.onLine) {
                    throw new Error('offline');
                }

                const response = await this.ajaxRequest({
                    action: 'ratnotes_get_notes',
                    nonce: ratnotesFrontendData.nonce,
                    status: this.currentStatus,
                    category: this.currentCategory === 'all' ? '' : this.currentCategory,
                    search: this.$container.find('.ratnotes-frontend-search-input').val() || ''
                }, { retries: 2, refreshNonce: true });

                if (!response || typeof response.success === 'undefined') {
                    throw new Error('invalid_response');
                }

                if (response.success) {
                    this.notes = response.data;
                    this.cacheData(cacheKey, this.notes);
                    this.cacheData('ratnotes_notes_last', this.notes);
                    await this.writeNotesToIndexedDB(cacheKey, this.notes);
                    this.isOffline = false;
                    this.updateOfflineBanner();
                    this.renderNotes();
                } else {
                    const renderedOffline = await this.tryRenderOfflineNotes(cacheKey);
                    if (!renderedOffline) {
                        this.showError(response.data?.message || 'Failed to load notes');
                    }
                }
            } catch (error) {
                console.error('Error loading notes:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    return;
                }

                this.isOffline = true;
                this.updateOfflineBanner();

                const renderedOffline = await this.tryRenderOfflineNotes(cacheKey);
                if (!renderedOffline) {
                    this.showError('Failed to load notes');
                }
            } finally {
                this.isLoading = false;
                this.$container.find('.ratnotes-frontend-loading').hide();
            }
        },

        /**
         * Load categories from API.
         */
        loadCategories: async function() {
            try {
                if (!navigator.onLine) {
                    throw new Error('offline');
                }

                const response = await this.ajaxRequest({
                    action: 'ratnotes_get_categories',
                    nonce: ratnotesFrontendData.nonce
                }, { retries: 2, refreshNonce: true });

                if (response.success) {
                    this.categories = response.data || [];
                    this.cacheData('ratnotes_categories', this.categories);
                    await this.writeCategoriesToIndexedDB(this.categories);
                    this.isOffline = false;
                    this.updateOfflineBanner();
                    this.renderCategories();
                    this.updateSelectedCategoryDisplay();
                    this.renderModalCategoryDropdown();
                    this.updateModalCategoryTriggerText();
                }
            } catch (error) {
                console.error('Error loading categories:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    return;
                }

                this.isOffline = true;
                this.updateOfflineBanner();

                const idbCategories = await this.readCategoriesFromIndexedDB();
                if (Array.isArray(idbCategories)) {
                    this.categories = idbCategories;
                    this.renderCategories();
                    this.updateSelectedCategoryDisplay();
                    this.renderModalCategoryDropdown();
                    this.updateModalCategoryTriggerText();
                    return;
                }

                const cachedCategories = this.readCache('ratnotes_categories');
                if (Array.isArray(cachedCategories)) {
                    this.categories = cachedCategories;
                    this.renderCategories();
                    this.updateSelectedCategoryDisplay();
                    this.renderModalCategoryDropdown();
                    this.updateModalCategoryTriggerText();
                }
            }
        },

        /**
         * Render category list in sidebar.
         */
        renderCategories: function() {
            const $list = this.$container.find('.ratnotes-frontend-category-list');
            if (!$list.length) return;

            const items = [
                `<button type="button" class="ratnotes-frontend-category-item ${this.currentCategory === 'all' ? 'active' : ''}" data-id="all">All Notes</button>`,
                ...this.categories.map(category =>
                    `<button type="button" class="ratnotes-frontend-category-item ${String(this.currentCategory) === String(category.id) ? 'active' : ''}" data-id="${category.id}">${this.escapeHtml(category.name)}</button>`
                )
            ];

            $list.html(items.join(''));
        },

        /**
         * Update selected category label above notes.
         */
        updateSelectedCategoryDisplay: function() {
            const $label = this.$container.find('.ratnotes-frontend-selected-category');
            const $button = this.$container.find('.ratnotes-frontend-category-create-btn');
            if (!$label.length) return;

            if (this.currentCategory === 'all') {
                $label.hide();
                $button.hide();
                return;
            }

            const category = this.categories.find(item => String(item.id) === String(this.currentCategory));
            const categoryName = category ? category.name : 'Selected Category';
            const buttonLabel = category ? `New ${categoryName} Note` : 'New Note';
            const safeCategoryName = this.escapeHtml(categoryName);

            $label
                .html(
                    `<span class="ratnotes-frontend-selected-category-text">Category: ${safeCategoryName}</span>` +
                    '<button type="button" class="ratnotes-frontend-clear-category" aria-label="Clear category filter">&times;</button>'
                )
                .show();

            $button
                .find('.ratnotes-frontend-category-create-text')
                .text(buttonLabel);
            
            $button.css('display', 'inline-flex');
        },

        /**
         * Clear selected category filter and return to default view.
         */
        clearCategoryFilter: function(e) {
            e.preventDefault();
            e.stopPropagation();

            this.currentCategory = 'all';
            this.renderCategories();
            this.updateSelectedCategoryDisplay();
            this.loadNotes();
        },

        /**
         * Handle create category form submit.
         */
        handleCreateCategory: async function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $input = $form.find('.ratnotes-frontend-category-create-input');
            const $error = $form.closest('.ratnotes-frontend-category-create-wrap').find('.ratnotes-frontend-category-create-error');
            const $submit = $form.find('.ratnotes-frontend-category-create-submit');
            const name = $input.val().trim();

            if (!name) return;

            $submit.prop('disabled', true);
            $error.hide();

            try {
                const response = await this.ajaxRequest({
                    action: 'ratnotes_create_category',
                    nonce: ratnotesFrontendData.nonce,
                    name: name
                }, { retries: 1, refreshNonce: true });

                if (response.success) {
                    $input.val('');
                    await this.loadCategories();
                } else {
                    $error.text(response.data?.message || 'Failed to create category').show();
                }
            } catch (err) {
                console.error('Error creating category:', err);
                if (this.isAuthFailure(err)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    $error.text('Session expired. Refresh and log in again.').show();
                } else {
                    $error.text('Failed to create category').show();
                }
            } finally {
                $submit.prop('disabled', false);
            }
        },

        /**
         * Render notes grid.
         */
        renderNotes: function(notesToRender = null) {
            const $grid = this.$container.find('.ratnotes-frontend-grid');
            const notes = notesToRender || this.notes;

            if (notes.length === 0) {
                $grid.html(this.renderEmptyState());
                return;
            }

            // Sort: pinned first
            const sortedNotes = [...notes].sort((a, b) => {
                if (a.is_pinned && !b.is_pinned) return -1;
                if (!a.is_pinned && b.is_pinned) return 1;
                return new Date(b.updated_at) - new Date(a.updated_at);
            });

            $grid.html(
                sortedNotes.map(note => this.renderNoteCard(note)).join('')
            );
        },

        /**
         * Render a single note card.
         */
        renderNoteCard: function(note) {
            const pinnedClass = note.is_pinned ? 'pinned' : '';
            const labels = (note.labels || []).map(label =>
                `<span class="ratnotes-frontend-label">${this.escapeHtml(label)}</span>`
            ).join('');
            const categories = (note.categories || []).map(category => this.escapeHtml(category.name)).join(', ');
            const restoreLink = this.currentStatus === 'trash'
                ? `<button type="button" class="ratnotes-frontend-note-restore" data-id="${note.id}">Remove From Trash</button>`
                : '';

            return `
                <div class="ratnotes-frontend-note ${pinnedClass}"
                     data-id="${note.id}">
                    ${note.title ? `<div class="ratnotes-frontend-note-title">${this.escapeHtml(note.title)}</div>` : ''}
                    <div class="ratnotes-frontend-note-content">${this.escapeHtml(note.content)}</div>
                    ${labels ? `<div class="ratnotes-frontend-note-labels">${labels}</div>` : ''}
                    ${categories ? `<div class="ratnotes-frontend-note-category">${categories}</div>` : ''}
                    ${restoreLink}
                </div>
            `;
        },

        /**
         * Render empty state.
         */
        renderEmptyState: function() {
            const messages = {
                active: {
                    icon: 'dashicons-admin-notes',
                    title: ratnotesFrontendData.strings.createNote,
                    text: 'Click "New Note" to create your first note'
                },
                archived: {
                    icon: 'dashicons-archive',
                    title: 'No archived notes',
                    text: 'Archived notes will appear here'
                },
                trash: {
                    icon: 'dashicons-trash',
                    title: 'Trash is empty',
                    text: 'Deleted notes will appear here'
                }
            };

            const msg = messages[this.currentStatus] || messages.active;

            return `
                <div class="ratnotes-frontend-empty">
                    <span class="dashicons ${msg.icon}"></span>
                    <h3>${msg.title}</h3>
                    <p>${msg.text}</p>
                </div>
            `;
        },

        /**
         * Open modal for create/edit.
         */
        openModal: function(noteId = null, presetCategoryIds = []) {
            if (!ratnotesFrontendData.isLoggedIn) {
                this.showError(ratnotesFrontendData.strings.loginRequired);
                return;
            }

            this.currentNote = noteId ? this.notes.find(n => n.id === noteId) : null;

            const $modal = this.$container.find('.ratnotes-frontend-modal');
            const $title = $modal.find('.ratnotes-frontend-note-title');
            const $content = $modal.find('.ratnotes-frontend-note-content');
            const $pinButton = $modal.find('.ratnotes-frontend-pin-btn');
            const $categoryPicker = $modal.find('.ratnotes-frontend-category-picker');

            // Reset modal
            $title.val('');
            $content.val('');
            this.selectedModalCategoryIds = Array.isArray(presetCategoryIds)
                ? presetCategoryIds.map((id) => String(id))
                : [];
            $categoryPicker.removeClass('open');

            if (this.currentNote && this.currentNote.is_archived) {
                $pinButton.hide();
            } else {
                $pinButton.show();
            }

            if (this.currentNote) {
                $title.val(this.currentNote.title);
                $content.val(this.currentNote.content);
                this.selectedModalCategoryIds = (this.currentNote.categories || []).map(category => String(category.id));
            }

            this.renderModalCategoryDropdown();
            this.updateModalCategoryTriggerText();

            $modal.fadeIn(200);
            $title.focus();
        },

        /**
         * Open a new note with the current category preselected.
         */
        openModalFromCurrentCategory: function() {
            if (this.currentCategory === 'all') {
                this.openModal();
                return;
            }

            this.openModal(null, [this.currentCategory]);
        },

        /**
         * Close modal.
         */
        closeModal: function() {
            this.$container.find('.ratnotes-frontend-modal').fadeOut(200);
            this.currentNote = null;
            this.selectedModalCategoryIds = [];
        },

        /**
         * Save note.
         */
        saveNote: async function() {
            const $modal = this.$container.find('.ratnotes-frontend-modal');
            const title = $modal.find('.ratnotes-frontend-note-title').val().trim();
            const content = $modal.find('.ratnotes-frontend-note-content').val().trim();

            if (!title && !content) {
                this.closeModal();
                return;
            }

            const isExisting = !!this.currentNote;
            const localId = isExisting
                ? this.currentNote.id
                : `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;

            const draft = {
                id: localId,
                title,
                content,
                is_pinned: isExisting ? !!this.currentNote.is_pinned : false,
                is_archived: isExisting ? !!this.currentNote.is_archived : false,
                is_trashed: false,
                categories: this.buildCategoryDataFromIds(this.selectedModalCategoryIds),
                labels: isExisting ? (this.currentNote.labels || []) : [],
                created_at: isExisting ? this.currentNote.created_at : new Date().toISOString(),
                updated_at: new Date().toISOString(),
                pending_sync: !navigator.onLine
            };

            try {
                if (!navigator.onLine) {
                    await this.upsertLocalNote(draft);

                    await this.enqueueOperation({
                        type: 'save',
                        noteKey: localId,
                        data: {
                            action: 'ratnotes_save_note',
                            nonce: ratnotesFrontendData.nonce,
                            id: isExisting ? this.currentNote.id : 0,
                            title,
                            content,
                            is_pinned: draft.is_pinned,
                            is_archived: draft.is_archived,
                            category_ids: this.selectedModalCategoryIds,
                            category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                        }
                    });

                    this.closeModal();
                    return;
                }

                const response = await this.ajaxRequest({
                    action: 'ratnotes_save_note',
                    nonce: ratnotesFrontendData.nonce,
                    id: this.currentNote ? this.currentNote.id : 0,
                    title: title,
                    content: content,
                    is_pinned: this.currentNote ? this.currentNote.is_pinned : false,
                    is_archived: this.currentNote ? this.currentNote.is_archived : false,
                    category_ids: this.selectedModalCategoryIds,
                    category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                }, { retries: 2, refreshNonce: true });

                if (response.success) {
                    this.closeModal();
                    await this.upsertLocalNote(response.data);
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to save note');
                }
            } catch (error) {
                console.error('Error saving note:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    this.showError('Session expired. Refresh and log in again.');
                    return;
                }

                await this.upsertLocalNote(draft);
                await this.enqueueOperation({
                    type: 'save',
                    noteKey: localId,
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: isExisting ? this.currentNote.id : 0,
                        title,
                        content,
                        is_pinned: draft.is_pinned,
                        is_archived: draft.is_archived,
                        category_ids: this.selectedModalCategoryIds,
                        category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                    }
                });
                this.closeModal();
            }
        },

        /**
         * Delete note.
         */
        deleteNote: async function() {
            if (!this.currentNote) return;

            if (!confirm(ratnotesFrontendData.strings.confirmDelete)) return;

            if (this.currentStatus === 'trash' && !confirm(ratnotesFrontendData.strings.confirmDeleteForever)) {
                return;
            }

            if (!navigator.onLine) {
                await this.removeLocalNote(this.currentNote.id);
                await this.enqueueOperation({
                    type: 'delete',
                    noteKey: this.currentNote.id,
                    data: {
                        action: 'ratnotes_delete_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        force: this.currentStatus === 'trash'
                    }
                });
                this.closeModal();
                return;
            }

            try {
                const response = await this.ajaxRequest({
                    action: 'ratnotes_delete_note',
                    nonce: ratnotesFrontendData.nonce,
                    id: this.currentNote.id,
                    force: this.currentStatus === 'trash'
                }, { retries: 2, refreshNonce: true });

                if (response.success) {
                    this.closeModal();
                    await this.removeLocalNote(this.currentNote.id);
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to delete note');
                }
            } catch (error) {
                console.error('Error deleting note:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    this.showError('Session expired. Refresh and log in again.');
                    return;
                }

                await this.removeLocalNote(this.currentNote.id);
                await this.enqueueOperation({
                    type: 'delete',
                    noteKey: this.currentNote.id,
                    data: {
                        action: 'ratnotes_delete_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        force: this.currentStatus === 'trash'
                    }
                });
                this.closeModal();
            }
        },

        /**
         * Restore note from trash from card link.
         */
        restoreFromTrash: async function(e) {
            e.preventDefault();
            e.stopPropagation();

            const noteId = $(e.currentTarget).data('id');
            if (!noteId) return;

            if (!navigator.onLine) {
                await this.removeLocalNote(noteId);
                await this.enqueueOperation({
                    type: 'restore',
                    noteKey: noteId,
                    data: {
                        action: 'ratnotes_restore_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: noteId
                    }
                });
                return;
            }

            try {
                const response = await this.ajaxRequest({
                    action: 'ratnotes_restore_note',
                    nonce: ratnotesFrontendData.nonce,
                    id: noteId
                }, { retries: 2, refreshNonce: true });

                if (response.success) {
                    await this.removeLocalNote(noteId);
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to restore note');
                }
            } catch (error) {
                console.error('Error restoring note:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    this.showError('Session expired. Refresh and log in again.');
                    return;
                }

                await this.removeLocalNote(noteId);
                await this.enqueueOperation({
                    type: 'restore',
                    noteKey: noteId,
                    data: {
                        action: 'ratnotes_restore_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: noteId
                    }
                });
            }
        },

        /**
         * Archive note.
         */
        archiveNote: async function() {
            if (!this.currentNote) return;

            try {
                const isArchived = this.currentNote.is_archived || false;

                if (!navigator.onLine) {
                    await this.upsertLocalNote({
                        ...this.currentNote,
                        is_archived: !isArchived,
                        is_pinned: !isArchived ? false : this.currentNote.is_pinned,
                        categories: this.buildCategoryDataFromIds(this.selectedModalCategoryIds),
                        updated_at: new Date().toISOString(),
                        pending_sync: true
                    });

                    await this.enqueueOperation({
                        type: 'save',
                        noteKey: this.currentNote.id,
                        data: {
                            action: 'ratnotes_save_note',
                            nonce: ratnotesFrontendData.nonce,
                            id: this.currentNote.id,
                            title: this.currentNote.title,
                            content: this.currentNote.content,
                            is_pinned: this.currentNote.is_pinned,
                            is_archived: !isArchived,
                            category_ids: this.selectedModalCategoryIds,
                            category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                        }
                    });

                    this.closeModal();
                    return;
                }

                const response = await this.ajaxRequest({
                    action: 'ratnotes_save_note',
                    nonce: ratnotesFrontendData.nonce,
                    id: this.currentNote.id,
                    title: this.currentNote.title,
                    content: this.currentNote.content,
                    is_pinned: this.currentNote.is_pinned,
                    is_archived: !isArchived,
                    category_ids: this.selectedModalCategoryIds,
                    category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                }, { retries: 2, refreshNonce: true });

                if (response.success) {
                    this.closeModal();
                    await this.upsertLocalNote(response.data);
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to archive note');
                }
            } catch (error) {
                console.error('Error archiving note:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    this.showError('Session expired. Refresh and log in again.');
                    return;
                }

                await this.enqueueOperation({
                    type: 'save',
                    noteKey: this.currentNote.id,
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        title: this.currentNote.title,
                        content: this.currentNote.content,
                        is_pinned: this.currentNote.is_pinned,
                        is_archived: !(this.currentNote.is_archived || false),
                        category_ids: this.selectedModalCategoryIds,
                        category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                    }
                });
                this.closeModal();
            }
        },

        /**
         * Toggle pin status.
         */
        togglePin: async function() {
            if (!this.currentNote) return;

            const nextPinned = !this.currentNote.is_pinned;

            if (!navigator.onLine) {
                await this.upsertLocalNote({
                    ...this.currentNote,
                    is_pinned: nextPinned,
                    categories: this.buildCategoryDataFromIds(this.selectedModalCategoryIds),
                    updated_at: new Date().toISOString(),
                    pending_sync: true
                });

                await this.enqueueOperation({
                    type: 'save',
                    noteKey: this.currentNote.id,
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        title: this.currentNote.title,
                        content: this.currentNote.content,
                        is_pinned: nextPinned,
                        is_archived: this.currentNote.is_archived,
                        category_ids: this.selectedModalCategoryIds,
                        category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                    }
                });

                this.closeModal();
                return;
            }

            try {
                const response = await this.ajaxRequest({
                    action: 'ratnotes_save_note',
                    nonce: ratnotesFrontendData.nonce,
                    id: this.currentNote.id,
                    title: this.currentNote.title,
                    content: this.currentNote.content,
                    is_pinned: !this.currentNote.is_pinned,
                    is_archived: this.currentNote.is_archived,
                    category_ids: this.selectedModalCategoryIds,
                    category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                }, { retries: 2, refreshNonce: true });

                if (response.success) {
                    this.currentNote.is_pinned = !this.currentNote.is_pinned;
                    this.closeModal();
                    await this.upsertLocalNote(response.data);
                    this.loadNotes();
                } else {
                    this.showError(response.data?.message || 'Failed to update pin status');
                }
            } catch (error) {
                console.error('Error toggling pin:', error);

                if (this.isAuthFailure(error)) {
                    this.sessionExpired = true;
                    this.updateOfflineBanner();
                    this.showError('Session expired. Refresh and log in again.');
                    return;
                }

                await this.enqueueOperation({
                    type: 'save',
                    noteKey: this.currentNote.id,
                    data: {
                        action: 'ratnotes_save_note',
                        nonce: ratnotesFrontendData.nonce,
                        id: this.currentNote.id,
                        title: this.currentNote.title,
                        content: this.currentNote.content,
                        is_pinned: nextPinned,
                        is_archived: this.currentNote.is_archived,
                        category_ids: this.selectedModalCategoryIds,
                        category_ids_json: JSON.stringify(this.selectedModalCategoryIds)
                    }
                });
                this.closeModal();
            }
        },

        /**
         * Handle navigation click.
         */
        handleNavClick: function(e) {
            this.$container.find('.ratnotes-frontend-nav-item').removeClass('active');
            $(e.currentTarget).addClass('active');
            this.currentStatus = $(e.currentTarget).data('status');
            this.loadNotes();
        },

        /**
         * Handle category selection from sidebar.
         */
        handleCategoryClick: function(e) {
            const selectedCategory = $(e.currentTarget).data('id');
            this.currentCategory = selectedCategory;
            this.renderCategories();
            this.updateSelectedCategoryDisplay();
            this.toggleSidebar(false);
            this.loadNotes();
        },

        /**
         * Toggle sidebar open/close state.
         */
        toggleSidebar: function(isOpen) {
            this.$container.toggleClass('sidebar-open', !!isOpen);
        },

        /**
         * Render category options for modal dropdown.
         */
        renderModalCategoryDropdown: function() {
            const $menu = this.$container.find('.ratnotes-frontend-category-menu');
            if (!$menu.length) return;

            if (!this.categories.length) {
                $menu.html('<div class="ratnotes-frontend-category-empty">No categories available</div>');
                return;
            }

            const items = this.categories.map((category) => {
                const categoryId = String(category.id);
                const checked = this.selectedModalCategoryIds.includes(categoryId) ? 'checked' : '';
                return `
                    <label class="ratnotes-frontend-category-option">
                        <input type="checkbox" value="${categoryId}" ${checked} />
                        <span>${this.escapeHtml(category.name)}</span>
                    </label>
                `;
            });

            $menu.html(items.join(''));
        },

        /**
         * Toggle modal category dropdown.
         */
        toggleModalCategoryDropdown: function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $picker = $(e.currentTarget).closest('.ratnotes-frontend-category-picker');
            $picker.toggleClass('open');
        },

        /**
         * Handle category checkbox change inside modal.
         */
        handleModalCategoryChange: function(e) {
            const categoryId = String($(e.currentTarget).val());
            if ($(e.currentTarget).is(':checked')) {
                if (!this.selectedModalCategoryIds.includes(categoryId)) {
                    this.selectedModalCategoryIds.push(categoryId);
                }
            } else {
                this.selectedModalCategoryIds = this.selectedModalCategoryIds.filter((id) => id !== categoryId);
            }

            this.updateModalCategoryTriggerText();
        },

        /**
         * Update modal category dropdown button label.
         */
        updateModalCategoryTriggerText: function() {
            const $label = this.$container.find('.ratnotes-frontend-category-trigger-text');
            if (!$label.length) return;

            if (!this.selectedModalCategoryIds.length) {
                $label.text('Categories');
                return;
            }

            const selectedNames = this.categories
                .filter((category) => this.selectedModalCategoryIds.includes(String(category.id)))
                .map((category) => category.name);

            if (selectedNames.length <= 2) {
                $label.text(selectedNames.join(', '));
                return;
            }

            $label.text(`${selectedNames.length} categories selected`);
        },

        /**
         * Handle search.
         */
        handleSearch: function(e) {
            const query = e.target.value.trim();

            if (!query) {
                this.loadNotes();
                return;
            }

            const filtered = this.notes.filter(note =>
                (note.title && note.title.toLowerCase().includes(query.toLowerCase())) ||
                (note.content && note.content.toLowerCase().includes(query.toLowerCase()))
            );
            this.renderNotes(filtered);
        },

        /**
         * Handle keyboard shortcuts.
         */
        handleKeyboard: function(e) {
            // Escape to save and close modal
            if (e.key === 'Escape' && this.$container.find('.ratnotes-frontend-modal').is(':visible')) {
                this.saveNote();
            }
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            alert(message);
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready.
    $(document).ready(() => {
        RatNotesFrontend.init();
    });

})(jQuery);
