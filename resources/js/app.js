import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Browser-side companion to the server-enforced inactivity middleware.
 *
 * The server remains authoritative. This monitor only lets an idle page move
 * to the login screen at the deadline (without waiting for another request),
 * synchronizes meaningful interaction at a controlled rate, and keeps tabs in
 * agreement through localStorage.
 */
const startSessionMonitor = () => {
    const timeoutSeconds = Number(document.body.dataset.sessionTimeoutSeconds);
    const activityUrl = document.body.dataset.sessionActivityUrl;
    const expiredUrl = document.body.dataset.sessionExpiredUrl;

    if (!Number.isFinite(timeoutSeconds) || timeoutSeconds <= 0 || !activityUrl || !expiredUrl) {
        return;
    }

    const timeoutMs = timeoutSeconds * 1000;
    const activityKey = 'hims:session:last-activity';
    const manualLogoutKey = 'hims:session:manual-logout';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const nativeFetch = window.fetch.bind(window);
    let lastActivityAt = Date.now();
    let expirationTimer = null;
    let heartbeatTimer = null;
    let heartbeatStartedAt = null;
    let expirationStarted = false;

    const readSharedActivity = () => {
        try {
            const value = Number(window.localStorage.getItem(activityKey));
            return Number.isFinite(value) ? value : 0;
        } catch {
            return 0;
        }
    };

    const writeSharedActivity = (timestamp) => {
        try {
            window.localStorage.setItem(activityKey, String(timestamp));
        } catch {
            // Storage can be unavailable in privacy-restricted contexts. The
            // current tab still has a fully functional inactivity timer.
        }
    };

    const stopSessionMonitor = () => {
        expirationStarted = true;
        window.clearTimeout(expirationTimer);
        window.clearTimeout(heartbeatTimer);
        expirationTimer = null;
        heartbeatTimer = null;
    };

    const expire = () => {
        if (expirationStarted) return;

        expirationStarted = true;
        window.location.replace(expiredUrl);
    };

    const scheduleExpiration = () => {
        window.clearTimeout(expirationTimer);

        const sharedActivity = readSharedActivity();
        lastActivityAt = Math.max(lastActivityAt, sharedActivity);
        const remaining = timeoutMs - (Date.now() - lastActivityAt);

        if (remaining <= 0) {
            expire();
            return;
        }

        expirationTimer = window.setTimeout(() => {
            const latestSharedActivity = readSharedActivity();

            if (latestSharedActivity > lastActivityAt) {
                lastActivityAt = latestSharedActivity;
                scheduleExpiration();
                return;
            }

            expire();
        }, remaining);
    };

    const sendHeartbeat = async () => {
        window.clearTimeout(heartbeatTimer);
        heartbeatTimer = null;
        heartbeatStartedAt = null;

        try {
            const response = await nativeFetch(activityUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.status === 401 || response.status === 419 || response.redirected) {
                expire();
            }
        } catch {
            // A temporary network outage must not falsely log a user out. The
            // next protected server request will still enforce the deadline.
        }
    };

    const queueHeartbeat = () => {
        const now = Date.now();

        if (heartbeatStartedAt === null) {
            heartbeatStartedAt = now;
        }

        window.clearTimeout(heartbeatTimer);

        // Send 500ms after interaction settles, or at least every 15 seconds
        // during continuous interaction. This avoids a request per keystroke.
        const maxWaitRemaining = Math.max(0, 15000 - (now - heartbeatStartedAt));
        heartbeatTimer = window.setTimeout(sendHeartbeat, Math.min(500, maxWaitRemaining));
    };

    const recordActivity = () => {
        if (expirationStarted) return;

        lastActivityAt = Date.now();
        writeSharedActivity(lastActivityAt);
        scheduleExpiration();
        queueHeartbeat();
    };

    // Loading a protected page is itself an authenticated server request.
    writeSharedActivity(lastActivityAt);
    scheduleExpiration();

    ['pointerdown', 'keydown', 'touchstart'].forEach((eventName) => {
        window.addEventListener(eventName, recordActivity, { passive: true });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === manualLogoutKey) {
            stopSessionMonitor();
            return;
        }

        if (event.key !== activityKey || !event.newValue) return;

        const timestamp = Number(event.newValue);
        if (!Number.isFinite(timestamp) || timestamp <= lastActivityAt) return;

        lastActivityAt = timestamp;
        scheduleExpiration();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) scheduleExpiration();
    });

    document.addEventListener('submit', (event) => {
        if (!(event.target instanceof HTMLFormElement)
            || !event.target.matches('[data-manual-logout]')) return;

        // Stop this tab before the POST navigation and notify every other tab.
        // The server still validates elapsed inactivity independently.
        stopSessionMonitor();

        try {
            window.localStorage.setItem(manualLogoutKey, String(Date.now()));
        } catch {
            // The current tab is already stopped when storage is unavailable.
        }
    });

    // Normalize expired fetch responses from all existing inline page scripts.
    window.fetch = async (...args) => {
        const response = await nativeFetch(...args);
        const redirectedToLogin = response.redirected
            && new URL(response.url, window.location.origin).pathname.endsWith('/login');

        if (response.status === 401 || response.status === 419 || redirectedToLogin) {
            expire();
        }

        return response;
    };

    window.axios?.interceptors.response.use(
        (response) => response,
        (error) => {
            if ([401, 419].includes(error.response?.status)) expire();
            return Promise.reject(error);
        },
    );
};

startSessionMonitor();

/**
 * Dashboard live updates via 30s polling.
 *
 * Swaps in fresh alert HTML and updates the stat tiles so the dashboard
 * reflects stock recorded from another screen without a manual reload.
 * Pauses when the tab is hidden and refreshes on tab focus.
 */
Alpine.data('dashboardLive', (endpoint) => ({
    intervalId: null,
    statusLabel: '',

    start() {
        this.poll();
        this.intervalId = setInterval(() => this.poll(), 30000);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pause();
            } else {
                this.resume();
            }
        });
    },

    pause() {
        if (this.intervalId !== null) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    },

    resume() {
        if (this.intervalId === null) {
            this.poll();
            this.intervalId = setInterval(() => this.poll(), 30000);
        }
    },

    refresh() {
        this.poll();
    },

    async poll() {
        try {
            const response = await fetch(endpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Session-Activity': 'passive',
                },
            });
            if (!response.ok) return;

            const data = await response.json();

            this.$refs.alerts.innerHTML = data.alertsHtml;

            // Update stat tiles
            const lowStockTile = this.$refs.lowStockTile;
            const openAlertTile = this.$refs.openAlertTile;

            if (lowStockTile) {
                const valueEl = lowStockTile.querySelector('[data-stat-value]');
                const hintEl = lowStockTile.querySelector('[data-stat-hint]');
                if (valueEl) valueEl.textContent = new Intl.NumberFormat().format(data.lowStockItems);
                if (hintEl) {
                    hintEl.textContent = data.outOfStockItems > 0
                        ? `${new Intl.NumberFormat().format(data.outOfStockItems)} fully out of stock`
                        : 'No items out of stock';
                }
            }

            if (openAlertTile) {
                const valueEl = openAlertTile.querySelector('[data-stat-value]');
                const hintEl = openAlertTile.querySelector('[data-stat-hint]');
                if (valueEl) valueEl.textContent = new Intl.NumberFormat().format(data.openAlertCount);
                if (hintEl) {
                    hintEl.textContent = data.openAlertCount > 0
                        ? 'Awaiting acknowledgement'
                        : 'Nothing outstanding';
                }
            }

            const now = new Date();
            this.statusLabel = `— refreshed ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
        } catch (error) {
            console.error('Dashboard poll failed:', error);
        }
    }
}));

/**
 * Server-backed autocomplete for the append-only Audit Trail.
 *
 * Only the current query is sent after the user pauses typing. Previous
 * requests are cancelled so a slower response cannot replace newer results.
 */
Alpine.data('auditSearchAutocomplete', ({ endpoint, formId, initialQuery = '' }) => ({
    query: initialQuery,
    suggestions: [],
    activeIndex: -1,
    open: false,
    loading: false,
    loaded: false,
    failed: false,
    debounceTimer: null,
    request: null,
    debounceDelay: 300,

    queue(value) {
        this.query = value;
        window.clearTimeout(this.debounceTimer);
        this.request?.abort();
        this.request = null;
        this.activeIndex = -1;
        this.failed = false;

        if (value.trim() === '') {
            this.reset();
            return;
        }

        this.open = true;
        this.loading = true;
        this.loaded = false;
        this.debounceTimer = window.setTimeout(
            () => this.fetchSuggestions(value.trim()),
            this.debounceDelay,
        );
    },

    async fetchSuggestions(term) {
        const controller = new AbortController();
        this.request = controller;

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('query', term);

            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Session-Activity': 'passive',
                },
                signal: controller.signal,
            });

            if (!response.ok) throw new Error(`Suggestion request failed with ${response.status}`);

            const payload = await response.json();

            if (this.query.trim() !== term) return;

            this.suggestions = Array.isArray(payload.data) ? payload.data : [];
            this.loaded = true;
        } catch (error) {
            if (error.name === 'AbortError') return;

            this.suggestions = [];
            this.loaded = true;
            this.failed = true;
        } finally {
            if (this.request === controller) {
                this.request = null;
                this.loading = false;
            }
        }
    },

    move(direction) {
        if (this.suggestions.length === 0) return;

        this.open = true;
        this.activeIndex = (
            this.activeIndex + direction + this.suggestions.length
        ) % this.suggestions.length;
    },

    selectActive(event) {
        if (!this.open || this.activeIndex < 0) return;

        event.preventDefault();
        this.select(this.suggestions[this.activeIndex]);
    },

    select(suggestion) {
        this.query = suggestion.value;
        this.close();

        this.$nextTick(() => document.getElementById(formId)?.requestSubmit());
    },

    close() {
        this.open = false;
        this.activeIndex = -1;
    },

    reset() {
        window.clearTimeout(this.debounceTimer);
        this.request?.abort();
        this.request = null;
        this.suggestions = [];
        this.loading = false;
        this.loaded = false;
        this.failed = false;
        this.close();
    },

    destroy() {
        this.reset();
    },
}));

Alpine.start();
