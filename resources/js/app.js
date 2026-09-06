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
    const configuredWarningSeconds = Number(document.body.dataset.sessionWarningSeconds);
    const activityUrl = document.body.dataset.sessionActivityUrl;
    const expiredUrl = document.body.dataset.sessionExpiredUrl;
    const warningDialog = document.querySelector('[data-session-warning]');
    const countdown = warningDialog?.querySelector('[data-session-countdown]');
    const liveRegion = warningDialog?.querySelector('[data-session-warning-live]');
    const warningError = warningDialog?.querySelector('[data-session-warning-error]');
    const warningAudio = warningDialog?.querySelector('[data-session-warning-audio]');
    const continueButton = warningDialog?.querySelector('[data-session-continue]');
    const dismissButton = warningDialog?.querySelector('[data-session-warning-dismiss]');

    if (!Number.isFinite(timeoutSeconds) || timeoutSeconds <= 0
        || !Number.isFinite(configuredWarningSeconds) || configuredWarningSeconds <= 0
        || !activityUrl || !expiredUrl || typeof HTMLDialogElement === 'undefined'
        || typeof HTMLAudioElement === 'undefined'
        || !(warningDialog instanceof HTMLDialogElement)
        || !(warningAudio instanceof HTMLAudioElement)
        || !countdown || !liveRegion || !warningError || !continueButton || !dismissButton) {
        return;
    }

    const timeoutMs = timeoutSeconds * 1000;
    const warningMs = Math.min(configuredWarningSeconds * 1000, timeoutMs - 1000);
    const activityKey = 'hims:session:last-activity';
    const manualLogoutKey = 'hims:session:manual-logout';
    const expiredKey = 'hims:session:expired';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const nativeFetch = window.fetch.bind(window);
    let lastActivityAt = Date.now();
    let expirationTimer = null;
    let warningTimer = null;
    let countdownTimer = null;
    let heartbeatTimer = null;
    let heartbeatStartedAt = null;
    let expirationStarted = false;
    let warningDismissed = false;
    let warningOpen = false;
    let previouslyFocusedElement = null;
    let lastAnnouncedSecond = null;

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

    const writeSharedExpiration = () => {
        try {
            window.localStorage.setItem(expiredKey, String(Date.now()));
        } catch {
            // This tab still follows the server-validated timeout flow.
        }
    };

    const formatCountdown = (remainingSeconds) => {
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;

        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    };

    const stopWarningSound = () => {
        warningAudio.pause();

        try {
            warningAudio.currentTime = 0;
        } catch {
            // Some browsers cannot seek until enough audio metadata is loaded.
        }
    };

    const playWarningSound = () => {
        stopWarningSound();

        try {
            const playback = warningAudio.play();
            if (playback && typeof playback.catch === 'function') {
                playback.catch(() => {
                    // Autoplay may be blocked; the visual warning remains active.
                });
            }
        } catch {
            // Missing or unsupported audio must not interrupt the warning.
        }
    };

    const closeWarning = ({ restoreFocus = true } = {}) => {
        window.clearInterval(countdownTimer);
        countdownTimer = null;
        lastAnnouncedSecond = null;

        if (warningDialog.open) warningDialog.close();
        warningOpen = false;
        liveRegion.textContent = '';
        warningError.textContent = '';
        warningError.classList.add('hidden');
        stopWarningSound();
        continueButton.disabled = false;
        continueButton.removeAttribute('aria-busy');

        if (restoreFocus && previouslyFocusedElement instanceof HTMLElement
            && document.contains(previouslyFocusedElement)) {
            previouslyFocusedElement.focus();
        }

        previouslyFocusedElement = null;
    };

    const stopSessionMonitor = () => {
        expirationStarted = true;
        window.clearTimeout(expirationTimer);
        window.clearTimeout(warningTimer);
        window.clearInterval(countdownTimer);
        window.clearTimeout(heartbeatTimer);
        expirationTimer = null;
        warningTimer = null;
        countdownTimer = null;
        heartbeatTimer = null;
        closeWarning({ restoreFocus: false });
    };

    const expire = (publish = true) => {
        if (expirationStarted) return;

        expirationStarted = true;
        window.clearTimeout(expirationTimer);
        window.clearTimeout(warningTimer);
        window.clearInterval(countdownTimer);
        window.clearTimeout(heartbeatTimer);
        stopWarningSound();
        if (publish) writeSharedExpiration();
        window.location.replace(expiredUrl);
    };

    const remainingMilliseconds = () => timeoutMs - (Date.now() - lastActivityAt);

    const updateCountdown = () => {
        const remainingSeconds = Math.max(0, Math.ceil(remainingMilliseconds() / 1000));
        countdown.textContent = formatCountdown(remainingSeconds);

        if (remainingSeconds !== lastAnnouncedSecond
            && (lastAnnouncedSecond === null || [30, 10].includes(remainingSeconds))) {
            liveRegion.textContent = remainingSeconds === 1
                ? 'Your session expires in 1 second.'
                : `Your session expires in ${remainingSeconds} seconds.`;
            lastAnnouncedSecond = remainingSeconds;
        }

        if (remainingSeconds <= 0) expire();
    };

    const showWarning = () => {
        if (expirationStarted || warningDismissed || warningOpen) return;

        warningOpen = true;
        previouslyFocusedElement = document.activeElement;
        updateCountdown();
        warningDialog.showModal();
        playWarningSound();
        window.requestAnimationFrame(() => continueButton.focus());
        countdownTimer = window.setInterval(updateCountdown, 250);
    };

    const scheduleExpiration = () => {
        window.clearTimeout(expirationTimer);
        window.clearTimeout(warningTimer);

        const sharedActivity = readSharedActivity();
        if (sharedActivity > lastActivityAt) {
            lastActivityAt = sharedActivity;
            warningDismissed = false;
            closeWarning();
        }

        const remaining = remainingMilliseconds();

        if (remaining <= 0) {
            expire();
            return;
        }

        if (remaining <= warningMs) {
            showWarning();
        } else {
            warningDismissed = false;
            closeWarning();
            warningTimer = window.setTimeout(showWarning, remaining - warningMs);
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

    const acceptServerActivity = (response) => {
        const header = response.headers.get('X-Session-Activity-At');
        if (header === null || header.trim() === '') return false;

        const timestampSeconds = Number(header);
        if (!Number.isFinite(timestampSeconds)) return false;

        const timestamp = timestampSeconds * 1000;
        if (timestamp <= lastActivityAt) return true;

        lastActivityAt = timestamp;
        warningDismissed = false;
        writeSharedActivity(lastActivityAt);
        closeWarning();
        scheduleExpiration();

        return true;
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
                return;
            }

            if (response.ok) acceptServerActivity(response);
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
        // Interacting with the warning, including dismissing it, must not
        // silently extend the session. Only Continue Session may do that.
        if (expirationStarted || warningOpen) return;

        lastActivityAt = Date.now();
        warningDismissed = false;
        writeSharedActivity(lastActivityAt);
        scheduleExpiration();
        queueHeartbeat();
    };

    const continueSession = async () => {
        if (expirationStarted || continueButton.disabled) return;

        stopWarningSound();
        continueButton.disabled = true;
        continueButton.setAttribute('aria-busy', 'true');
        warningError.textContent = '';
        warningError.classList.add('hidden');

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
                return;
            }

            if (!response.ok || !acceptServerActivity(response)) {
                throw new Error('The server did not confirm the session extension.');
            }
        } catch {
            if (expirationStarted) return;

            warningError.classList.remove('hidden');
            warningError.textContent = 'Unable to continue your session. Check your connection and try again before the timer expires.';
            continueButton.disabled = false;
            continueButton.removeAttribute('aria-busy');
            continueButton.focus();
        }
    };

    const dismissWarning = () => {
        warningDismissed = true;
        closeWarning();
    };

    // Loading a protected page is itself an authenticated server request.
    writeSharedActivity(lastActivityAt);
    scheduleExpiration();

    ['pointerdown', 'keydown', 'touchstart'].forEach((eventName) => {
        window.addEventListener(eventName, recordActivity, { passive: true });
    });

    continueButton.addEventListener('click', continueSession);
    dismissButton.addEventListener('click', dismissWarning);
    warningDialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        dismissWarning();
    });

    window.addEventListener('storage', (event) => {
        if (event.key === manualLogoutKey) {
            stopSessionMonitor();
            return;
        }

        if (event.key === expiredKey) {
            expire(false);
            return;
        }

        if (event.key !== activityKey || !event.newValue) return;

        const timestamp = Number(event.newValue);
        if (!Number.isFinite(timestamp) || timestamp <= lastActivityAt) return;

        lastActivityAt = timestamp;
        warningDismissed = false;
        closeWarning();
        scheduleExpiration();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) scheduleExpiration();
    });

    document.addEventListener('submit', (event) => {
        if (!(event.target instanceof HTMLFormElement)) return;

        // Stop pending heartbeats before a form navigation. Otherwise a slow
        // validation redirect can race the heartbeat's session write and lose
        // the flashed validation errors before the next page renders them.
        stopSessionMonitor();

        if (!event.target.matches('[data-manual-logout]')) return;

        // Manual logout also tells every other tab to stop its monitor.
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
        } else if (response.ok) {
            acceptServerActivity(response);
        }

        return response;
    };

    window.axios?.interceptors.response.use(
        (response) => {
            if (response.headers?.['x-session-activity-at']) {
                const timestamp = Number(response.headers['x-session-activity-at']) * 1000;

                if (Number.isFinite(timestamp) && timestamp > lastActivityAt) {
                    lastActivityAt = timestamp;
                    warningDismissed = false;
                    writeSharedActivity(lastActivityAt);
                    closeWarning();
                    scheduleExpiration();
                }
            }

            return response;
        },
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
