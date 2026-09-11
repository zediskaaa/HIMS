import './bootstrap';

import Alpine from 'alpinejs';
import { himsCameraScanner, playScanAudio } from './scanner';

window.Alpine = Alpine;
window.playScanAudio = playScanAudio;

const loadingButtons = new WeakMap();

const loadingLabelFor = (button, form) => {
    if (button?.dataset.loadingText) return button.dataset.loadingText;

    const label = button?.textContent?.trim().toLowerCase() ?? '';
    const action = form?.action?.toLowerCase() ?? '';

    if (label.includes('sign in') || action.endsWith('/login')) return 'Signing in...';
    if (label.includes('verify')) return 'Verifying...';
    if (label.includes('send') || label.includes('resend')) return 'Sending...';
    if (label.includes('search') || label.includes('filter') || form?.method === 'get') return 'Loading results...';
    if (label.includes('delete')) return 'Deleting...';
    if (label.includes('deactivate') || label.includes('reactivate')) return 'Updating account...';
    if (label.includes('save') || label.includes('update')) return 'Saving...';
    if (label.includes('create') || label.includes('add')) return 'Creating...';
    if (label.includes('approve')) return 'Approving...';
    if (label.includes('receive')) return 'Receiving...';
    if (label.includes('log out') || label.includes('sign out')) return 'Signing out...';

    return 'Processing...';
};

const setButtonLoading = (button, label) => {
    if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement)
        || loadingButtons.has(button)) {
        return;
    }

    loadingButtons.set(button, {
        disabled: button.disabled,
        html: button instanceof HTMLButtonElement ? button.innerHTML : null,
        value: button instanceof HTMLInputElement ? button.value : null,
    });

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.setAttribute('data-hims-loading-active', '');

    if (button instanceof HTMLInputElement) {
        button.value = label;
        return;
    }

    const spinner = document.createElement('span');
    spinner.className = 'loader loader--sm';
    spinner.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.textContent = label;
    button.replaceChildren(spinner, text);
};

const resetButtonLoading = (button) => {
    const original = loadingButtons.get(button);
    if (!original) return;

    button.disabled = original.disabled;
    button.removeAttribute('aria-busy');
    button.removeAttribute('data-hims-loading-active');

    if (button instanceof HTMLButtonElement) {
        button.innerHTML = original.html;
    } else {
        button.value = original.value;
    }

    loadingButtons.delete(button);
};

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
    const warningEnabled = document.body.dataset.sessionWarningEnabled !== 'false';
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
        resetButtonLoading(continueButton);

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
        if (!warningEnabled || expirationStarted || warningDismissed || warningOpen) return;

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

        if (!warningEnabled) {
            closeWarning();
        } else if (remaining <= warningMs) {
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
        setButtonLoading(continueButton, 'Continuing...');
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
            resetButtonLoading(continueButton);
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
        if (!(event.target instanceof HTMLFormElement) || event.defaultPrevented) return;

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
        const passwordExpiredLocation = response.headers.get('X-HIMS-Password-Expired');
        const redirectedToLogin = response.redirected
            && new URL(response.url, window.location.origin).pathname.endsWith('/login');

        if (passwordExpiredLocation) {
            window.location.assign(passwordExpiredLocation);
        } else if (response.status === 401 || response.status === 419 || redirectedToLogin) {
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
            const passwordExpiredLocation = error.response?.headers?.['x-hims-password-expired'];

            if (passwordExpiredLocation) {
                window.location.assign(passwordExpiredLocation);
            } else if ([401, 419].includes(error.response?.status)) {
                expire();
            }

            return Promise.reject(error);
        },
    );
};

/**
 * One confirmation gate for consequential native form submissions.
 *
 * Forms opt in with data attributes. The first submit is cancelled before the
 * session monitor or loading indicator sees it; only an explicit confirmation
 * re-submits the form and starts the existing loading flow.
 */
const startDecisionConfirmations = () => {
    const dialog = document.querySelector('[data-decision-confirmation]');
    const title = dialog?.querySelector('[data-decision-title]');
    const message = dialog?.querySelector('[data-decision-message]');
    const cancelButton = dialog?.querySelector('[data-decision-cancel]');
    const confirmButton = dialog?.querySelector('[data-decision-confirm]');

    if (typeof HTMLDialogElement === 'undefined'
        || !(dialog instanceof HTMLDialogElement)
        || !(title instanceof HTMLElement)
        || !(message instanceof HTMLElement)
        || !(cancelButton instanceof HTMLButtonElement)
        || !(confirmButton instanceof HTMLButtonElement)) {
        return;
    }

    const confirmedForms = new WeakSet();
    let pending = null;
    let processing = false;

    const decisionFor = (form) => {
        if (form.matches('[data-confirm-mfa]')) {
            const enabled = form.querySelector('input[name="mfa_enabled"][type="checkbox"]')?.checked ?? false;
            const originallyEnabled = form.dataset.originalMfa === '1';

            if (enabled === originallyEnabled) return null;

            return {
                title: 'Confirm security change',
                message: enabled
                    ? 'Are you sure you want to turn on MFA?'
                    : 'Are you sure you want to turn off MFA?',
                label: enabled ? 'Turn On MFA' : 'Turn Off MFA',
            };
        }

        if (form.matches('[data-confirm-email-change]')) {
            const email = form.querySelector('input[name="email"]');
            const originalEmail = form.dataset.originalEmail?.trim().toLowerCase() ?? '';
            const nextEmail = email instanceof HTMLInputElement ? email.value.trim().toLowerCase() : '';

            if (nextEmail === originalEmail) return null;

            return {
                title: 'Confirm account change',
                message: 'Are you sure you want to change your sign-in email address?',
                label: 'Change Email',
            };
        }

        const confirmationMessage = form.dataset.confirmMessage?.trim();
        if (!confirmationMessage) return null;

        return {
            title: form.dataset.confirmTitle?.trim() || 'Confirm action',
            message: confirmationMessage,
            label: form.dataset.confirmLabel?.trim() || 'Confirm',
        };
    };

    const resetDialog = () => {
        processing = false;
        cancelButton.disabled = false;
        confirmButton.disabled = false;
        confirmButton.removeAttribute('aria-busy');
    };

    const closeDialog = ({ restoreFocus = true } = {}) => {
        const focusedBeforeOpen = pending?.focusedBeforeOpen;

        if (dialog.open) dialog.close();
        pending = null;
        resetDialog();

        if (restoreFocus && focusedBeforeOpen instanceof HTMLElement
            && document.contains(focusedBeforeOpen)) {
            focusedBeforeOpen.focus();
        }
    };

    const cancel = () => {
        if (processing) return;

        const form = pending?.form;
        if (form instanceof HTMLFormElement && form.matches('[data-confirm-mfa]')) {
            const checkbox = form.querySelector('input[name="mfa_enabled"][type="checkbox"]');

            if (checkbox instanceof HTMLInputElement) {
                checkbox.checked = form.dataset.originalMfa === '1';
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        closeDialog();
    };

    const confirm = () => {
        if (processing || !pending) return;

        processing = true;
        cancelButton.disabled = true;
        confirmButton.disabled = true;
        confirmButton.setAttribute('aria-busy', 'true');

        const { form, submitter } = pending;
        confirmedForms.add(form);
        dialog.close();
        pending = null;

        window.queueMicrotask(() => {
            if (!form.isConnected) {
                confirmedForms.delete(form);
                resetDialog();
                return;
            }

            try {
                form.requestSubmit(submitter ?? undefined);
            } catch {
                try {
                    form.requestSubmit();
                } catch {
                    confirmedForms.delete(form);
                    resetDialog();
                }
            }
        });
    };

    // This listener is registered before the session and loading listeners so
    // Cancel has no side effects and no loading UI can appear prematurely.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (confirmedForms.has(form)) {
            confirmedForms.delete(form);
            return;
        }

        const decision = decisionFor(form);
        if (!decision) return;

        event.preventDefault();

        if (dialog.open || processing) return;

        pending = {
            form,
            submitter: event.submitter instanceof HTMLButtonElement
                || event.submitter instanceof HTMLInputElement
                ? event.submitter
                : null,
            focusedBeforeOpen: document.activeElement,
        };

        title.textContent = decision.title;
        message.textContent = decision.message;
        confirmButton.textContent = decision.label;
        dialog.showModal();
        window.requestAnimationFrame(() => cancelButton.focus());
    });

    cancelButton.addEventListener('click', cancel);
    confirmButton.addEventListener('click', confirm);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        cancel();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) cancel();
    });
    window.addEventListener('pageshow', () => closeDialog({ restoreFocus: false }));
};

startDecisionConfirmations();
startSessionMonitor();

const startAuditLocationConsent = () => {
    const endpoint = document.body.dataset.auditLocationUrl;

    if (!endpoint || !navigator.geolocation) {
        return;
    }

    const store = async (position) => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
            }),
        });

        if (!response.ok) throw new Error(`Location store failed with ${response.status}`);
    };

    const capture = () => {
        navigator.geolocation.getCurrentPosition(
            (position) => store(position).catch(() => {}),
            () => {},
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
        );
    };

    if (navigator.permissions?.query) {
        navigator.permissions.query({ name: 'geolocation' })
            .then((permission) => {
                if (permission.state === 'denied') {
                    return;
                }

                if (permission.state === 'prompt' || permission.state === 'granted') {
                    capture();
                }

                permission.addEventListener('change', () => {
                    if (permission.state === 'granted') {
                        capture();
                    }
                });
            })
            .catch(() => {
                capture();
            });
        return;
    }

    capture();
};

startAuditLocationConsent();

const initLoginLocationCapture = () => {
    const form = document.querySelector('[data-login-form]');
    if (!form || !navigator.geolocation) return;

    const latInput = form.querySelector('[data-login-latitude]');
    const lngInput = form.querySelector('[data-login-longitude]');
    const accInput = form.querySelector('[data-login-accuracy]');

    if (!latInput || !lngInput) return;

    const capture = () => {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                latInput.value = position.coords.latitude;
                lngInput.value = position.coords.longitude;
                if (accInput) accInput.value = position.coords.accuracy;
            },
            () => {},
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
        );
    };

    if (navigator.permissions?.query) {
        navigator.permissions.query({ name: 'geolocation' })
            .then((permission) => {
                if (permission.state === 'granted' || permission.state === 'prompt') {
                    capture();
                }
            })
            .catch(() => capture());
    } else {
        capture();
    }
};

initLoginLocationCapture();

/**
 * Display a server-issued login cooldown without making the browser a security
 * boundary. The next submission is always revalidated by Laravel.
 */
const startLoginCooldown = () => {
    const notice = document.querySelector('[data-login-cooldown]');
    const form = document.querySelector('[data-login-form]');
    const email = form?.querySelector('input[name="email"]');
    const submit = form?.querySelector('button[type="submit"]');
    const countdown = notice?.querySelector('[data-login-cooldown-value]');
    const liveRegion = notice?.querySelector('[data-login-cooldown-live]');
    const expiresAt = Number(notice?.dataset.loginCooldownExpiresAt);
    const serverNow = Number(notice?.dataset.loginCooldownServerNow);
    const restrictedEmail = notice?.dataset.loginCooldownEmail?.trim().toLowerCase() ?? '';

    if (!(notice instanceof HTMLElement)
        || !(form instanceof HTMLFormElement)
        || !(email instanceof HTMLInputElement)
        || !(submit instanceof HTMLButtonElement)
        || !(countdown instanceof HTMLElement)
        || !(liveRegion instanceof HTMLElement)
        || !Number.isFinite(expiresAt)
        || !Number.isFinite(serverNow)
        || restrictedEmail === '') {
        return;
    }

    const startedAt = performance.now();
    let timer = null;
    let lastAnnouncement = null;
    let cooldownActive = false;

    const remainingSeconds = () => Math.max(
        0,
        Math.ceil(expiresAt - (serverNow + ((performance.now() - startedAt) / 1000))),
    );
    const formatCountdown = (seconds) => {
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;

        return String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
    };
    const emailMatches = () => email.value.trim().toLowerCase() === restrictedEmail;

    const update = () => {
        const remaining = remainingSeconds();
        const active = remaining > 0 && emailMatches();

        cooldownActive = active;
        notice.hidden = !active;
        submit.disabled = active;
        submit.setAttribute('aria-disabled', String(active));
        countdown.textContent = formatCountdown(remaining);

        const announce = remaining === 0
            || remaining <= 10
            || remaining % 60 === 0;

        if (active && announce && remaining !== lastAnnouncement) {
            liveRegion.textContent = 'Sign-in is available in ' + remaining + ' seconds.';
            lastAnnouncement = remaining;
        }

        if (remaining === 0) {
            window.clearInterval(timer);
            timer = null;
            liveRegion.textContent = 'The waiting period has ended. Submit again to let the server confirm access.';
        }
    };

    email.addEventListener('input', update);
    form.addEventListener('submit', (event) => {
        if (cooldownActive) event.preventDefault();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) update();
    });

    update();
    if (remainingSeconds() > 0) timer = window.setInterval(update, 1000);
};

startLoginCooldown();

/**
 * Shared visual feedback for native page submissions, internal navigation,
 * and foreground API requests. Passive polling and session heartbeats stay
 * quiet.
 */
const startLoadingIndicators = () => {
    const overlay = document.querySelector('[data-hims-loading-overlay]');
    const overlayMessage = overlay?.querySelector('[data-hims-loading-message]');
    const processingForms = new WeakSet();
    const nativeFetch = window.fetch.bind(window);
    let activeApiRequests = 0;
    let pageTransitionPending = false;

    const showOverlay = (message) => {
        if (!(overlay instanceof HTMLElement)) return;

        if (overlayMessage) overlayMessage.textContent = message;
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.setAttribute('aria-busy', 'true');
    };

    const hideOverlay = () => {
        if (!(overlay instanceof HTMLElement)
            || activeApiRequests > 0
            || pageTransitionPending) {
            return;
        }

        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.removeAttribute('aria-busy');
    };

    const reset = () => {
        activeApiRequests = 0;
        pageTransitionPending = false;

        document.querySelectorAll('[data-hims-loading-active]').forEach((button) => {
            resetButtonLoading(button);
        });
        document.querySelectorAll('form[aria-busy="true"]').forEach((form) => {
            form.removeAttribute('aria-busy');
            processingForms.delete(form);
        });
        document.querySelectorAll('[data-hims-submitter-value]').forEach((input) => input.remove());
        document.querySelectorAll('[data-hims-navigation-active]').forEach((link) => {
            link.removeAttribute('aria-busy');
            link.removeAttribute('data-hims-navigation-active');
        });

        if (overlay instanceof HTMLElement) {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.removeAttribute('aria-busy');
    };

    // Never inherit a visible or busy state from cached/restored page markup.
    reset();

    const continueAfterPaint = (callback) => {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(callback);
        });
    };

    const requestUsesVisibleLoader = (input, options = {}) => {
        try {
            const request = input instanceof Request ? input : null;
            const url = new URL(request?.url ?? String(input), window.location.origin);
            const headers = new Headers(request?.headers);
            new Headers(options.headers).forEach((value, key) => headers.set(key, value));

            return url.origin === window.location.origin
                && url.pathname.startsWith('/api/v1/')
                && headers.get('X-Session-Activity') !== 'passive';
        } catch {
            return false;
        }
    };

    window.fetch = async (...args) => {
        const visible = requestUsesVisibleLoader(args[0], args[1]);

        if (visible) {
            activeApiRequests += 1;
            showOverlay('Loading data...');
        }

        try {
            return await nativeFetch(...args);
        } finally {
            if (visible) {
                activeApiRequests = Math.max(0, activeApiRequests - 1);
                hideOverlay();
            }
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)
            || event.defaultPrevented
            || form.matches('[data-no-loading]')
            || (form.target && form.target !== '_self')) {
            return;
        }

        if (pageTransitionPending || processingForms.has(form)) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        processingForms.add(form);
        form.setAttribute('aria-busy', 'true');

        const submitter = event.submitter instanceof HTMLButtonElement
            || event.submitter instanceof HTMLInputElement
            ? event.submitter
            : form.querySelector('button[type="submit"], input[type="submit"]');

        if (submitter?.name) {
            const preservedValue = document.createElement('input');
            preservedValue.type = 'hidden';
            preservedValue.name = submitter.name;
            preservedValue.value = submitter.value;
            preservedValue.setAttribute('data-hims-submitter-value', '');
            form.append(preservedValue);
        }

        if (submitter) {
            const label = loadingLabelFor(submitter, form);
            setButtonLoading(submitter, label);
            showOverlay(label);
        } else {
            showOverlay('Processing request...');
        }

        pageTransitionPending = true;

        continueAfterPaint(() => {
            if (!form.isConnected) {
                reset();
                return;
            }

            try {
                HTMLFormElement.prototype.submit.call(form);
            } catch {
                reset();
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!(link instanceof HTMLAnchorElement)
            || link.matches('[data-no-loading], [download]')
            || (link.target && link.target !== '_self')) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        const isSamePageHash = url.pathname === window.location.pathname
            && url.search === window.location.search
            && url.hash !== '';

        if (url.origin !== window.location.origin
            || !['http:', 'https:'].includes(url.protocol)
            || isSamePageHash) {
            return;
        }

        event.preventDefault();
        if (pageTransitionPending) return;

        pageTransitionPending = true;
        link.setAttribute('aria-busy', 'true');
        link.setAttribute('data-hims-navigation-active', '');
        showOverlay('Loading page...');

        continueAfterPaint(() => {
            try {
                window.location.assign(url.href);
            } catch {
                reset();
            }
        });
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) reset();
    });

    window.addEventListener('pagehide', reset);
};

startLoadingIndicators();

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

Alpine.data('himsCameraScanner', himsCameraScanner);

Alpine.start();
