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
    const processingDownloads = new WeakSet();
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
        document.querySelectorAll('[data-hims-download-active]').forEach((link) => {
            link.removeAttribute('aria-busy');
            link.removeAttribute('aria-disabled');
            link.removeAttribute('data-hims-download-active');
            processingDownloads.delete(link);
        });

        if (overlay instanceof HTMLElement) {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.removeAttribute('aria-busy');
    };

    document.addEventListener('hims-loading-start', (event) => {
        activeApiRequests += 1;
        showOverlay(event.detail?.message || 'Loading data...');
    });

    document.addEventListener('hims-loading-stop', () => {
        activeApiRequests = Math.max(0, activeApiRequests - 1);
        hideOverlay();
    });

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

        if (link.matches('[data-hims-download]')) {
            event.preventDefault();
            if (processingDownloads.has(link)) return;

            processingDownloads.add(link);
            activeApiRequests += 1;
            link.setAttribute('aria-busy', 'true');
            link.setAttribute('aria-disabled', 'true');
            link.setAttribute('data-hims-download-active', '');
            showOverlay(link.dataset.loadingText || 'Preparing document...');

            void (async () => {
                let objectUrl = null;

                try {
                    const response = await nativeFetch(url.href, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const disposition = response.headers.get('Content-Disposition') || '';

                    if (!response.ok || !disposition.toLowerCase().startsWith('attachment')) {
                        throw new Error(`Download request failed with status ${response.status}.`);
                    }

                    objectUrl = URL.createObjectURL(await response.blob());
                    const download = document.createElement('a');
                    const downloadName = (link.dataset.downloadName || 'document')
                        .split(/[\\/]/)
                        .pop()
                        .replace(/[\u0000-\u001F\u007F]/g, '') || 'document';
                    download.href = objectUrl;
                    download.download = downloadName;
                    download.hidden = true;
                    download.setAttribute('data-no-loading', '');
                    document.body.append(download);
                    download.click();
                    download.remove();
                } catch {
                    window.dispatchEvent(new CustomEvent('notify', {
                        detail: {
                            type: 'error',
                            title: 'Download failed',
                            message: 'The document is missing or temporarily unavailable. Please try again or contact the records custodian.',
                        },
                    }));
                } finally {
                    if (objectUrl) URL.revokeObjectURL(objectUrl);
                    activeApiRequests = Math.max(0, activeApiRequests - 1);
                    processingDownloads.delete(link);
                    link.removeAttribute('aria-busy');
                    link.removeAttribute('aria-disabled');
                    link.removeAttribute('data-hims-download-active');
                    hideOverlay();
                }
            })();

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

Alpine.data('demandForecastDashboard', ({ initialForecast, endpoint }) => ({
    forecast: initialForecast,
    endpoint,
    analysisDays: String(initialForecast?.analysis_days ?? 90),
    forecastDays: String(initialForecast?.forecast_days ?? 30),
    selectedItemId: '',
    category: '',
    risk: '',
    search: '',
    loading: false,
    error: '',
    success: '',
    activePoint: null,
    isDragging: false,
    isHovering: false,
    isFocused: false,
    filtersOpen: false,
    showActual: true,
    showForecast: true,
    forecastCache: {},
    forecastRequest: null,
    forecastRequestId: 0,
    failedForecastDays: null,
    chartAnimation: null,
    chartAnimationFrame: null,
    chartAnimationProgress: 1,
    chartAnimating: false,

    init() {
        if (this.forecast) {
            this.forecastCache[this.forecastCacheKey(this.forecast.forecast_days)] = this.forecast;
        }
        this.$nextTick(() => {
            this.resetActivePointToTransition();
        });
        ['selectedItemId', 'category', 'risk', 'search'].forEach((property) => {
            this.$watch(property, () => {
                this.$nextTick(() => this.clearActivePoint());
            });
        });
    },

    clearActivePoint() {
        this.activePoint = null;
    },

    activeFilterCount() {
        return [this.category, this.risk, this.search.trim()].filter(Boolean).length;
    },

    clearFilters() {
        this.selectedItemId = '';
        this.category = '';
        this.risk = '';
        this.search = '';
        this.clearActivePoint();
        this.$nextTick(() => {
            this.resetActivePointToTransition();
        });
    },

    toggleSeries(type) {
        if (type === 'actual') {
            this.showActual = !this.showActual;
            if (!this.showActual && !this.showForecast) {
                this.showForecast = true;
            }
        } else if (type === 'forecast') {
            this.showForecast = !this.showForecast;
            if (!this.showForecast && !this.showActual) {
                this.showActual = true;
            }
        }
        this.resetActivePointToTransition();
    },

    resetActivePointToTransition() {
        const points = this.allChartPoints();
        if (points.length === 0) {
            this.activePoint = null;
            return;
        }

        const hist = this.historicalPoints();
        const target = hist.length > 0 ? hist[hist.length - 1] : points[0];
        this.setActivePoint(target);
    },

    allItems() {
        return Array.isArray(this.forecast?.items) ? this.forecast.items : [];
    },

    filteredItems() {
        const needle = this.search.trim().toLocaleLowerCase();

        return this.allItems().filter((item) => {
            const matchesCategory = this.category === ''
                || String(item.category_id ?? '') === String(this.category);
            const matchesRisk = this.risk === '' || item.risk_level === this.risk;
            const searchable = `${item.item_name ?? ''} ${item.sku ?? ''} ${item.category ?? ''}`
                .toLocaleLowerCase();

            return matchesCategory && matchesRisk && (needle === '' || searchable.includes(needle));
        });
    },

    selectedItem() {
        if (!this.selectedItemId) return null;
        return this.allItems().find((item) => String(item.item_id) === String(this.selectedItemId)) || null;
    },

    selectItem(id) {
        const newId = String(id || '');
        this.selectedItemId = this.selectedItemId === newId ? '' : newId;
        this.$nextTick(() => {
            this.resetActivePointToTransition();
        });
    },

    topItems() {
        const priorityOrder = { critical: 0, high: 1, medium: 2, low: 3, none: 4 };
        return [...this.filteredItems()].sort((a, b) => {
            const prioA = priorityOrder[a.reorder_priority] ?? (a.risk_level === 'high' ? 1 : (a.risk_level === 'medium' ? 2 : 3));
            const prioB = priorityOrder[b.reorder_priority] ?? (b.risk_level === 'high' ? 1 : (b.risk_level === 'medium' ? 2 : 3));
            if (prioA !== prioB) return prioA - prioB;
            return (b.predicted_demand || 0) - (a.predicted_demand || 0);
        }).slice(0, 6);
    },

    highRiskCount() {
        return this.filteredItems().filter((item) => item.risk_level === 'high').length;
    },

    lowStockRiskCount() {
        return this.filteredItems().filter((item) => (
            item.projected_stock_status === 'low_stock'
            || item.projected_stock_status === 'out_of_stock'
        )).length;
    },

    predictedDemand() {
        return this.filteredItems().reduce(
            (total, item) => total + Number(item.predicted_demand || 0),
            0,
        );
    },

    recommendedReorder() {
        return this.filteredItems().reduce(
            (total, item) => total + Number(item.recommended_reorder_quantity || 0),
            0,
        );
    },

    confidenceLabel() {
        const items = this.filteredItems();
        if (items.length === 0) return 'No data';

        const threshold = Math.ceil(items.length / 2);
        const high = items.filter((item) => item.confidence === 'high').length;
        const low = items.filter((item) => item.confidence === 'low').length;

        if (high >= threshold) return 'High';
        if (low >= threshold) return 'Low';

        return 'Medium';
    },

    summaryPredictedDemand() {
        const sel = this.selectedItem();
        return sel ? Number(sel.predicted_demand || 0) : this.predictedDemand();
    },

    summaryPredictedDailyDemand() {
        return this.summaryPredictedDemand()
            / Math.max(1, Number(this.forecast?.forecast_days || 1));
    },

    summaryCurrentStock() {
        const sel = this.selectedItem();
        if (sel) return Number(sel.current_stock || 0);
        return this.filteredItems().reduce((total, item) => total + Number(item.current_stock || 0), 0);
    },

    summaryStockRisk() {
        const sel = this.selectedItem();
        if (sel) return sel.risk_level || 'low';
        if (this.highRiskCount() > 0) return 'high';
        if (this.lowStockRiskCount() > 0) return 'medium';
        return 'low';
    },

    summaryRecommendedReorder() {
        const sel = this.selectedItem();
        return sel ? Number(sel.recommended_reorder_quantity || 0) : this.recommendedReorder();
    },

    aggregateSeries(field) {
        const totals = new Map();
        const windowDays = field === 'historical_series'
            ? Number(this.forecast?.analysis_days || 90)
            : Number(this.forecast?.forecast_days || 30);

        this.filteredItems().forEach((item) => {
            const series = Array.isArray(item[field]) ? item[field] : [];
            const fallbackDays = Math.max(1, Math.ceil(windowDays / Math.max(1, series.length)));

            series.forEach((point, index) => {
                const date = String(point.period_start || '');
                if (date === '') return;

                const inferredDays = Math.max(1, Math.min(fallbackDays, windowDays - (index * fallbackDays)));
                const days = Math.max(1, Number(point.days || inferredDays));
                const current = totals.get(date) || { quantity: 0, days };
                current.quantity += Number(point.quantity || 0);
                current.days = Math.max(current.days, days);
                totals.set(date, current);
            });
        });

        return Array.from(totals, ([date, point]) => ({
            date,
            quantity: point.quantity,
            days: point.days,
            rate: point.quantity / point.days,
        })).sort((left, right) => left.date.localeCompare(right.date));
    },

    currentHistoricalSeries() {
        const sel = this.selectedItem();
        if (sel) {
            const series = Array.isArray(sel.historical_series) ? sel.historical_series : [];
            const windowDays = Number(this.forecast?.analysis_days || 90);
            const fallbackDays = Math.max(1, Math.ceil(windowDays / Math.max(1, series.length)));

            return series
                .filter((p) => p.period_start)
                .map((p, index) => {
                    const inferredDays = Math.max(1, Math.min(fallbackDays, windowDays - (index * fallbackDays)));
                    const days = Math.max(1, Number(p.days || inferredDays));
                    const quantity = Number(p.quantity || 0);
                    return {
                        date: p.period_start,
                        quantity,
                        days,
                        rate: quantity / days,
                    };
                })
                .sort((a, b) => a.date.localeCompare(b.date));
        }
        return this.aggregateSeries('historical_series');
    },

    currentForecastSeries() {
        const sel = this.selectedItem();
        if (sel) {
            const series = Array.isArray(sel.forecast_series) ? sel.forecast_series : [];
            const windowDays = Number(this.forecast?.forecast_days || 30);
            const fallbackDays = Math.max(1, Math.ceil(windowDays / Math.max(1, series.length)));

            return series
                .filter((p) => p.period_start)
                .map((p, index) => {
                    const inferredDays = Math.max(1, Math.min(fallbackDays, windowDays - (index * fallbackDays)));
                    const days = Math.max(1, Number(p.days || inferredDays));
                    const quantity = Number(p.quantity || 0);
                    return {
                        date: p.period_start,
                        quantity,
                        days,
                        rate: quantity / days,
                    };
                })
                .sort((a, b) => a.date.localeCompare(b.date));
        }
        return this.aggregateSeries('forecast_series');
    },

    historicalSeries() {
        return this.currentHistoricalSeries();
    },

    forecastSeries() {
        return this.currentForecastSeries();
    },

    hasChartData() {
        return this.currentHistoricalSeries().length > 0 && this.currentForecastSeries().length > 0;
    },

    chartMaximum() {
        const hist = this.currentHistoricalSeries();
        const fore = this.currentForecastSeries();
        const histMax = Math.max(...hist.map((point) => Number(point.rate || 0)), 0);
        const foreMax = Math.max(...fore.map((point) => Number(point.rate || 0)), 0);
        const rawMaximum = Math.max(histMax, foreMax, 1);

        return this.niceAxisStep(rawMaximum / 4) * 4;
    },

    niceAxisStep(value) {
        const magnitude = 10 ** Math.floor(Math.log10(Math.max(Number(value) || 0, Number.EPSILON)));
        const normalized = value / magnitude;
        const niceNormalized = normalized <= 1
            ? 1
            : (normalized <= 2 ? 2 : (normalized <= 2.5 ? 2.5 : (normalized <= 5 ? 5 : 10)));

        return niceNormalized * magnitude;
    },

    chartTicks() {
        const maximum = this.chartMaximum();
        const step = maximum / 4;

        return Array.from({ length: 5 }, (_, index) => {
            const y = 40 + ((165 / 4) * index);

            return {
                value: maximum - (step * index),
                y,
                top: (y / 240) * 100,
            };
        });
    },

    chartY(value) {
        return Math.round((205 - ((Number(value) / this.chartMaximum()) * 165)) * 10) / 10;
    },

    captureChartGeometry() {
        return {
            historical: this.historicalPoints(),
            forecast: this.forecastPoints(),
            historicalLine: this.historicalRenderPoints(),
            transitionX: this.transitionX(),
            ticks: this.chartTicks(),
        };
    },

    sampleChartPoints(points, count) {
        if (!Array.isArray(points) || points.length === 0 || count <= 0) return [];
        if (points.length === 1 || count === 1) {
            return Array.from({ length: count }, () => ({ ...points[0] }));
        }

        return Array.from({ length: count }, (_, index) => {
            const position = (index / (count - 1)) * (points.length - 1);
            const leftIndex = Math.floor(position);
            const rightIndex = Math.min(points.length - 1, Math.ceil(position));
            const fraction = position - leftIndex;
            const left = points[leftIndex];
            const right = points[rightIndex];

            return {
                ...right,
                x: left.x + ((right.x - left.x) * fraction),
                y: left.y + ((right.y - left.y) * fraction),
            };
        });
    },

    interpolateChartPoints(fromPoints, toPoints) {
        if (!this.chartAnimation) return toPoints;

        const count = Math.max(fromPoints.length, toPoints.length);
        if (count === 0) return [];

        const from = this.sampleChartPoints(fromPoints.length > 0 ? fromPoints : toPoints, count);
        const to = this.sampleChartPoints(toPoints.length > 0 ? toPoints : fromPoints, count);
        const progress = this.chartAnimationProgress;

        return to.map((point, index) => ({
            ...point,
            index,
            x: Math.round((from[index].x + ((point.x - from[index].x) * progress)) * 10) / 10,
            y: Math.round((from[index].y + ((point.y - from[index].y) * progress)) * 10) / 10,
        }));
    },

    displayHistoricalPoints() {
        if (!this.chartAnimation) return this.historicalPoints();
        return this.interpolateChartPoints(
            this.chartAnimation.from.historical,
            this.chartAnimation.to.historical,
        );
    },

    displayForecastPoints() {
        if (!this.chartAnimation) return this.forecastPoints();
        return this.interpolateChartPoints(
            this.chartAnimation.from.forecast,
            this.chartAnimation.to.forecast,
        );
    },

    displayForecastRenderPoints() {
        return this.forecastRenderPoints(
            this.displayForecastPoints(),
            this.displayHistoricalRenderPoints(),
            this.displayTransitionX(),
        );
    },

    displayHistoricalRenderPoints() {
        if (!this.chartAnimation) return this.historicalRenderPoints();
        return this.interpolateChartPoints(
            this.chartAnimation.from.historicalLine,
            this.chartAnimation.to.historicalLine,
        );
    },

    displayTransitionX() {
        if (!this.chartAnimation) return this.transitionX();

        const { from, to } = this.chartAnimation;
        return from.transitionX + ((to.transitionX - from.transitionX) * this.chartAnimationProgress);
    },

    displayChartTicks() {
        if (!this.chartAnimation) return this.chartTicks();

        const { from, to } = this.chartAnimation;
        return to.ticks.map((tick, index) => ({
            ...tick,
            value: from.ticks[index].value
                + ((tick.value - from.ticks[index].value) * this.chartAnimationProgress),
            top: from.ticks[index].top
                + ((tick.top - from.ticks[index].top) * this.chartAnimationProgress),
        }));
    },

    displayHistoricalAreaPath() {
        const shape = this.displayHistoricalRenderPoints();
        if (shape.length === 0) return '';

        const baseline = 205;
        return `M ${shape[0].x} ${baseline} L ${shape[0].x} ${shape[0].y} ${shape
            .slice(1)
            .map((point) => `L ${point.x} ${point.y}`)
            .join(' ')} L ${shape[shape.length - 1].x} ${baseline} Z`;
    },

    displayForecastAreaPath() {
        const historical = this.displayHistoricalRenderPoints();
        const forecast = this.chartLinePoints(this.displayForecastRenderPoints(), 725);
        if (forecast.length === 0) return '';

        const baseline = 205;
        const startX = this.displayTransitionX();
        const startY = historical.length > 0 ? historical[historical.length - 1].y : forecast[0].y;

        return `M ${startX} ${baseline} L ${startX} ${startY} ${forecast
            .map((point) => `L ${point.x} ${point.y}`)
            .join(' ')} L ${forecast[forecast.length - 1].x} ${baseline} Z`;
    },

    applyForecast(forecast) {
        if (!forecast) return;

        const from = this.chartAnimation
            ? {
                historical: this.displayHistoricalPoints(),
                forecast: this.displayForecastPoints(),
                historicalLine: this.displayHistoricalRenderPoints(),
                transitionX: this.displayTransitionX(),
                ticks: this.displayChartTicks(),
            }
            : this.captureChartGeometry();
        if (this.chartAnimationFrame) cancelAnimationFrame(this.chartAnimationFrame);

        // Hold the old geometry through Alpine's update, then interpolate it
        // into the newly calculated period rather than replacing the SVG.
        this.chartAnimation = { from, to: from };
        this.chartAnimationProgress = 0;
        this.chartAnimating = true;
        this.clearActivePoint();
        this.forecast = forecast;

        this.$nextTick(() => {
            const to = this.captureChartGeometry();
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduceMotion) {
                this.chartAnimation = null;
                this.chartAnimationProgress = 1;
                this.chartAnimating = false;
                this.resetActivePointToTransition();
                return;
            }

            this.chartAnimation = { from, to };
            const startedAt = performance.now();
            const duration = 280;

            const animate = (now) => {
                const elapsed = Math.min(1, (now - startedAt) / duration);
                this.chartAnimationProgress = 1 - ((1 - elapsed) ** 3);

                if (elapsed < 1) {
                    this.chartAnimationFrame = requestAnimationFrame(animate);
                    return;
                }

                this.chartAnimation = null;
                this.chartAnimationFrame = null;
                this.chartAnimationProgress = 1;
                this.chartAnimating = false;
                this.$nextTick(() => this.resetActivePointToTransition());
            };

            this.chartAnimationFrame = requestAnimationFrame(animate);
        });
    },

    chartAnalysisDays() {
        return Math.max(1, Number(this.forecast?.analysis_days || 90));
    },

    chartForecastDays() {
        return Math.max(1, Number(this.forecast?.forecast_days || 30));
    },

    /**
     * One horizontal scale for the whole chart.
     *
     * The two regions used to be handed half the plot each whatever they
     * measured: ninety days of history across 420px and ninety days of forecast
     * across 255px. A given slope therefore drew 1.65x steeper on the left, so a
     * level forecast read as a fall and a real climb read as flat. Both windows
     * now share a single px-per-day, which puts the boundary wherever the two
     * window lengths put it rather than at a fixed halfway mark.
     */
    chartPxPerDay() {
        const plotWidth = 675; // 50 to 725, matching the grid lines.

        return plotWidth / (this.chartAnalysisDays() + this.chartForecastDays());
    },

    transitionX() {
        return 50 + (this.chartPxPerDay() * this.chartAnalysisDays());
    },

    historicalPoints() {
        const series = this.currentHistoricalSeries();
        if (series.length === 0) return [];

        const originX = 50;
        const pxPerDay = this.chartPxPerDay();
        let elapsedDays = 0;

        return series.map((point) => {
            const days = Math.max(1, Number(point.days || 1));
            const quantity = Number(point.quantity || 0);
            const rate = point.rate != null ? Number(point.rate) : (quantity / days);

            // Each bucket is plotted where its period opens, so the first point
            // lands on the window start and the line begins at the left edge of
            // the plot. Plotting at the period end instead left the first
            // bucket's whole width empty and the line looked cut off.
            const x = originX + (pxPerDay * elapsedDays);
            elapsedDays += days;

            return {
                x: Math.round(x * 10) / 10,
                y: this.chartY(rate),
                date: point.date,
                formattedDate: this.formatPeriodLabel(point.date, days),
                quantity,
                days,
                value: rate,
                type: 'actual',
                label: 'Historical Demand',
            };
        });
    },

    forecastPoints() {
        const series = this.currentForecastSeries();
        if (series.length === 0) return [];

        const originX = 50;
        const pxPerDay = this.chartPxPerDay();

        // Each value represents a complete forecast bucket, so plot it at the
        // bucket end. forecastRenderPoints() adds the explicit boundary anchor.
        let elapsedDays = this.chartAnalysisDays();

        return series.map((point) => {
            const days = Math.max(1, Number(point.days || 1));
            const quantity = Number(point.quantity || 0);
            const rate = point.rate != null ? Number(point.rate) : (quantity / days);

            elapsedDays += days;
            const x = originX + (pxPerDay * elapsedDays);

            return {
                x: Math.round(x * 10) / 10,
                y: this.chartY(rate),
                date: point.date,
                formattedDate: this.formatPeriodLabel(point.date, days),
                quantity,
                days,
                value: rate,
                type: 'forecast',
                label: 'AI Forecast',
                confidence: this.selectedItem()?.confidence ?? this.confidenceLabel().toLowerCase(),
            };
        });
    },

    forecastRenderPoints(
        forecast = this.forecastPoints(),
        historical = this.historicalRenderPoints(),
        transition = this.transitionX(),
    ) {
        if (forecast.length === 0) return [];

        const latestHistorical = historical[historical.length - 1];
        if (!latestHistorical) return forecast;

        return [{
            ...forecast[0],
            x: transition,
            y: latestHistorical.y,
            value: latestHistorical.value,
            quantity: 0,
            type: 'forecast-start',
        }, ...forecast];
    },

    historicalRenderPoints() {
        const hist = this.historicalPoints();
        if (hist.length === 0) return [];

        const fore = this.forecastPoints();
        if (fore.length === 0) return hist;

        const last = hist[hist.length - 1];
        return [...hist, {
            ...last,
            x: this.transitionX(),
            date: fore[0].date,
            type: 'history-end',
            label: 'Historical Demand',
        }];
    },

    allChartPoints() {
        const hist = this.historicalPoints();
        const fore = this.forecastPoints();

        const timelineMap = new Map();

        // 1. Add historical points
        if (this.showActual || !this.showForecast) {
            hist.forEach((p) => timelineMap.set(p.date, p));
        }

        // 2. Add future points: prioritize forecast point for primary positioning if forecast is visible
        if (this.showForecast) {
            fore.forEach((p) => timelineMap.set(p.date, p));
        }

        const points = Array.from(timelineMap.values()).sort((a, b) => a.x - b.x);
        return points.length > 0 ? points : [...hist, ...fore];
    },

    seriesPath(points) {
        if (!Array.isArray(points) || points.length === 0) return '';
        return points.map((point, index) => (
            `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`
        )).join(' ');
    },

    /**
     * Keep animated stroke and fill geometry pinned to the window edge.
     * Markers and hover continue to use the actual bucket arrays, so any
     * temporary edge point never becomes selectable.
     */
    chartLinePoints(points, edgeX) {
        if (!Array.isArray(points) || points.length === 0) return [];

        const last = points[points.length - 1];
        if (last.x >= edgeX) return points;

        return [...points, { ...last, x: edgeX }];
    },

    chartStartLabel(series) {
        const s = series || this.currentHistoricalSeries();
        return s.length > 0 ? this.formatShortDate(s[0].date) : '';
    },

    chartEndLabel(series) {
        const s = series || this.currentForecastSeries();
        if (s.length === 0) return '';

        // The horizon ends on the final bucket's last day, not on the day that
        // bucket opens. Reading the last period_start labelled the chart with a
        // date up to a fortnight short of where the forecast actually stops.
        const last = s[s.length - 1];
        const end = new Date(`${last.date}T00:00:00`);
        if (Number.isNaN(end.getTime())) return this.formatShortDate(last.date);

        end.setDate(end.getDate() + Math.max(1, Number(last.days || 1)) - 1);

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
        }).format(end);
    },

    chartTransitionLabel() {
        const forecast = this.currentForecastSeries();
        if (forecast.length > 0) return this.formatShortDate(forecast[0].date);

        const hist = this.currentHistoricalSeries();
        return hist.length > 0 ? this.formatShortDate(hist[hist.length - 1].date) : 'Today';
    },

    onChartPointerDown(event) {
        this.isDragging = true;
        this.isHovering = true;
        try {
            event.currentTarget.setPointerCapture(event.pointerId);
        } catch {}
        this.handlePointerPosition(event);
    },

    onChartPointerMove(event) {
        this.isHovering = true;
        this.handlePointerPosition(event);
    },

    onChartPointerUp(event) {
        this.isDragging = false;
        try {
            if (event.currentTarget?.hasPointerCapture?.(event.pointerId)) {
                event.currentTarget.releasePointerCapture(event.pointerId);
            }
        } catch {}
    },

    onChartPointerCancel(event) {
        this.onChartPointerUp(event);
    },

    onChartMouseMove(event) {
        this.isHovering = true;
        this.handlePointerPosition(event);
    },

    onChartMouseLeave() {
        this.isDragging = false;
        this.isHovering = false;
    },

    onChartPointerLeave(event) {
        if (event && event.pointerType === 'touch') {
            this.isDragging = false;
            return;
        }
        this.isDragging = false;
        this.isHovering = false;
    },

    handlePointerPosition(event) {
        const svg = event.currentTarget;
        if (!svg) return;
        const rect = svg.getBoundingClientRect();
        if (rect.width <= 0) return;

        const clientX = event.clientX;
        const rawX = ((clientX - rect.left) / rect.width) * 760;
        const svgX = Math.max(50, Math.min(725, rawX));

        const closest = this.getNearestPoint(svgX);
        if (closest) {
            this.setActivePoint(closest);
        }
    },

    getNearestPoint(svgX) {
        const points = this.allChartPoints();
        if (points.length === 0) return null;

        let closest = points[0];
        let minDiff = Math.abs(svgX - points[0].x);

        for (let i = 1; i < points.length; i++) {
            const diff = Math.abs(svgX - points[i].x);
            if (diff < minDiff) {
                minDiff = diff;
                closest = points[i];
            }
        }

        return closest;
    },

    setActivePoint(point) {
        if (!point) return;

        const isFuture = point.x >= (this.transitionX() - 2) || point.type === 'forecast';
        const forecastPoints = this.forecastPoints();

        const forecastPoint = forecastPoints.find((p) => p.date === point.date)
            || (point.type === 'forecast' ? point : null);

        const primary = (isFuture && this.showForecast && forecastPoint) ? forecastPoint : point;

        this.activePoint = {
            ...primary,
            isFuture,
            forecastPoint,
            formattedDate: this.formatPeriodLabel(point.date, point.days),
            percentageX: Math.round((primary.x / 760) * 1000) / 10,
            percentageY: Math.round((primary.y / 240) * 1000) / 10,
        };
    },

    tooltipStyle() {
        if (!this.activePoint) return 'display: none;';
        const pctX = this.activePoint.percentageX ?? 50;
        if (pctX > 55) {
            return `left: ${pctX}%; top: 8px; transform: translateX(calc(-100% - 14px));`;
        }
        return `left: ${pctX}%; top: 8px; transform: translateX(14px);`;
    },

    stepPoint(direction) {
        this.isFocused = true;
        this.isHovering = true;
        const points = this.allChartPoints();
        if (points.length === 0) return;
        const currentIndex = points.findIndex(
            (p) => p.date === this.activePoint?.date && p.type === this.activePoint?.type
        );
        const nextIndex = Math.max(
            0,
            Math.min(points.length - 1, (currentIndex >= 0 ? currentIndex : 0) + direction)
        );
        this.setActivePoint(points[nextIndex]);
    },

    historicalDailyRate() {
        const series = this.currentHistoricalSeries();
        return series.reduce((total, point) => total + point.quantity, 0)
            / Math.max(1, Number(this.forecast?.analysis_days || 1));
    },

    predictedDailyRate() {
        const series = this.currentForecastSeries();
        return series.reduce((total, point) => total + point.quantity, 0)
            / Math.max(1, Number(this.forecast?.forecast_days || 1));
    },

    trendLabel() {
        const historical = this.historicalDailyRate();
        const predicted = this.predictedDailyRate();

        if (historical === 0) return predicted > 0 ? 'New recorded demand signal' : 'No demand change';

        const percent = Math.round(Math.abs(((predicted - historical) / historical) * 100));
        if (predicted > historical) return `${percent}% higher predicted daily demand`;
        if (predicted < historical) return `${percent}% lower predicted daily demand`;

        return 'Predicted daily demand is stable';
    },

    insight() {
        const sel = this.selectedItem();
        if (sel) {
            return sel.explanation || `${sel.item_name} has predicted demand of ${this.formatNumber(sel.predicted_demand)} units. Current stock is ${this.formatNumber(sel.current_stock)} units.`;
        }

        const count = this.filteredItems().length;
        if (count === 0) return 'No forecast items match the current filters.';

        const highRisk = this.highRiskCount();
        const lowStock = this.lowStockRiskCount();
        const reorder = this.recommendedReorder();

        if (highRisk > 0) {
            return `${highRisk} ${highRisk === 1 ? 'item is' : 'items are'} at high demand risk; ${lowStock} ${lowStock === 1 ? 'item is' : 'items are'} projected to have low or no stock.`;
        }

        if (lowStock > 0) {
            return `${lowStock} ${lowStock === 1 ? 'item is' : 'items are'} projected to have low stock, with ${this.formatNumber(reorder)} suggested reorder units.`;
        }

        return `No filtered items are projected to run low. Predicted demand is ${this.formatNumber(this.predictedDemand())} units for this period.`;
    },

    clearFilters() {
        this.selectedItemId = '';
        this.category = '';
        this.risk = '';
        this.search = '';
        this.$nextTick(() => {
            this.resetActivePointToTransition();
        });
    },

    riskClasses(risk) {
        return {
            high: 'bg-danger-50 text-danger-700 ring-danger-600/20',
            medium: 'bg-warning-50 text-warning-700 ring-warning-600/20',
            low: 'bg-success-50 text-success-700 ring-success-600/20',
        }[risk] || 'bg-neutral-100 text-neutral-700 ring-neutral-500/20';
    },

    sourceClasses() {
        return this.forecast?.source === 'ai'
            ? 'bg-primary-50 text-primary-700 ring-primary-600/20'
            : 'bg-warning-50 text-warning-700 ring-warning-600/20';
    },

    formatNumber(value, maximumFractionDigits = 0) {
        return new Intl.NumberFormat(undefined, { maximumFractionDigits }).format(Number(value || 0));
    },

    formatDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Unknown time';

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(date);
    },

    formatDateLabel(value) {
        if (!value) return '';
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return String(value);

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    },

    formatPeriodLabel(value, days = 1) {
        const start = new Date(`${value}T00:00:00`);
        if (Number.isNaN(start.getTime())) return String(value || '');

        const duration = Math.max(1, Number(days || 1));
        if (duration <= 1) return this.formatDateLabel(value);

        const end = new Date(start);
        end.setDate(end.getDate() + duration - 1);

        return `${this.formatDateLabel(value)} – ${new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(end)}`;
    },

    formatShortDate(value) {
        if (!value) return '';
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return '';

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
        }).format(date);
    },

    forecastCacheKey(days) {
        return `${Number(this.analysisDays)}:${Number(days)}`;
    },

    loadingPeriodLabel() {
        return this.loading ? `Updating ${Number(this.forecastDays)}-day forecast` : '';
    },

    retryForecast() {
        if (!this.failedForecastDays) return;
        this.forecastDays = String(this.failedForecastDays);
        this.updateForecastPeriod(this.failedForecastDays, true);
    },

    async updateForecastPeriod(value, force = false) {
        const days = Number(value);
        const allowedPeriods = [7, 14, 30, 60, 90];
        if (!allowedPeriods.includes(days)) {
            this.forecastDays = String(this.forecast?.forecast_days ?? 30);
            return;
        }

        this.forecastDays = String(days);
        if (!force && Number(this.forecast?.forecast_days) === days) {
            if (this.forecastRequest) {
                this.forecastRequest.abort();
                this.forecastRequest = null;
                this.forecastRequestId += 1;
                this.loading = false;
            }
            this.error = '';
            this.failedForecastDays = null;
            return;
        }

        const cacheKey = this.forecastCacheKey(days);
        if (!force && this.forecastCache[cacheKey]) {
            this.forecastRequest?.abort();
            this.forecastRequest = null;
            this.forecastRequestId += 1;
            this.loading = false;
            this.error = '';
            this.success = `${days}-day forecast updated.`;
            this.failedForecastDays = null;
            this.applyForecast(this.forecastCache[cacheKey]);
            return;
        }

        this.forecastRequest?.abort();
        const request = new AbortController();
        const requestId = ++this.forecastRequestId;
        this.forecastRequest = request;
        this.loading = true;
        this.error = '';
        this.success = '';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                signal: request.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    analysis_days: Number(this.analysisDays),
                    forecast_days: days,
                    return_to: 'dashboard',
                    reuse_cached: !force,
                }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || !payload.forecast) {
                throw new Error(payload.message || 'The forecast could not be generated.');
            }
            if (requestId !== this.forecastRequestId) return;

            this.forecastCache[cacheKey] = payload.forecast;
            this.failedForecastDays = null;
            this.success = `${days}-day forecast updated.`;
            this.applyForecast(payload.forecast);
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') return;
            if (requestId !== this.forecastRequestId) return;

            this.failedForecastDays = days;
            this.forecastDays = String(this.forecast?.forecast_days ?? 30);
            this.error = `Unable to update the ${days}-day forecast. Please try again.`;
        } finally {
            if (requestId === this.forecastRequestId) {
                this.loading = false;
                this.forecastRequest = null;
            }
        }
    },
}));

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

Alpine.data('himsAiAssistant', ({
    endpoint,
    conversationsEndpoint = '/dashboard/ai-assistant/conversations',
    activeEndpoint = '/dashboard/ai-assistant/conversations/active',
    conversationShowBase = '/dashboard/ai-assistant/conversations',
    knownItems = []
}) => ({
    isOpen: false,
    endpoint,
    conversationsEndpoint,
    activeEndpoint,
    conversationShowBase,
    knownItems: Array.isArray(knownItems) ? knownItems : [],

    // Active conversation state
    conversationId: null,
    conversationTitle: '',
    messages: [],
    input: '',
    isLoading: false,
    loadingStatus: 'Looking into that...',
    errorMessage: '',
    selectedFile: null,
    isDraggingOver: false,
    allowedExtensions: ['pdf', 'csv', 'xlsx', 'docx', 'txt', 'jpg', 'jpeg', 'png'],
    maxFileSize: 35 * 1024 * 1024, // 35MB

    // History and navigation state
    viewMode: 'chat', // 'chat' | 'history'
    conversationsList: [],
    isHistoryLoading: false,
    showNewChatConfirm: false,
    hasRestoredActive: false,

    init() {
        this.restoreActiveConversation();
    },

    async restoreActiveConversation() {
        if (this.hasRestoredActive) return;
        try {
            const resp = await fetch(this.activeEndpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                const data = await resp.json();
                if (data.status === 'success' && data.conversation) {
                    this.conversationId = data.conversation.id;
                    this.conversationTitle = data.conversation.title;
                    this.messages = Array.isArray(data.conversation.messages) ? data.conversation.messages : [];
                }
            }
        } catch (e) {
            // Silently fallback to clean empty chat state
        } finally {
            this.hasRestoredActive = true;
        }
    },

    toggle() {
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            if (!this.hasRestoredActive) {
                this.restoreActiveConversation();
            }
            this.$nextTick(() => {
                this.scrollToBottom();
                if (this.viewMode === 'chat') {
                    this.$refs.chatInput?.focus();
                }
            });
        }
    },

    close() {
        // CLOSE CHATBOT ≠ NEW CHAT: Only hide the UI, preserve current conversation
        this.isOpen = false;
        this.viewMode = 'chat';
        this.showNewChatConfirm = false;
    },

    requestNewChat() {
        if (this.messages.length > 0) {
            this.showNewChatConfirm = true;
        } else {
            this.startNewChat();
        }
    },

    cancelNewChat() {
        this.showNewChatConfirm = false;
    },

    startNewChat() {
        this.showNewChatConfirm = false;
        this.conversationId = null;
        this.conversationTitle = '';
        this.messages.forEach(m => {
            if (m.attachment?.previewUrl && m.attachment.previewUrl.startsWith('blob:') && typeof URL !== 'undefined' && URL.revokeObjectURL) {
                try {
                    URL.revokeObjectURL(m.attachment.previewUrl);
                } catch (e) {}
            }
        });
        this.messages = [];
        this.errorMessage = '';
        this.removeFile(true);
        this.viewMode = 'chat';
        this.$nextTick(() => {
            this.$refs.chatInput?.focus();
        });
    },

    clearChat() {
        this.requestNewChat();
    },

    async toggleHistory() {
        if (this.viewMode === 'history') {
            this.viewMode = 'chat';
            this.$nextTick(() => this.scrollToBottom());
        } else {
            this.viewMode = 'history';
            await this.loadConversations();
        }
    },

    async loadConversations() {
        this.isHistoryLoading = true;
        try {
            const resp = await fetch(this.conversationsEndpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                const data = await resp.json();
                this.conversationsList = Array.isArray(data.conversations) ? data.conversations : [];
            }
        } catch (e) {
            console.error('Failed to load conversations', e);
        } finally {
            this.isHistoryLoading = false;
        }
    },

    get groupedConversations() {
        const groups = {};
        for (const conv of this.conversationsList) {
            const group = conv.date_group || 'Previous';
            if (!groups[group]) {
                groups[group] = [];
            }
            groups[group].push(conv);
        }
        return groups;
    },

    async selectConversation(id) {
        if (this.conversationId === id && this.messages.length > 0) {
            this.viewMode = 'chat';
            this.$nextTick(() => this.scrollToBottom());
            return;
        }

        this.isLoading = true;
        this.viewMode = 'chat';
        try {
            const url = `${this.conversationShowBase}/${id}`;
            const resp = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                const data = await resp.json();
                if (data.status === 'success' && data.conversation) {
                    this.conversationId = data.conversation.id;
                    this.conversationTitle = data.conversation.title;
                    this.messages = Array.isArray(data.conversation.messages) ? data.conversation.messages : [];
                    this.$nextTick(() => this.scrollToBottom());
                }
            }
        } catch (e) {
            this.errorMessage = 'Failed to load selected conversation.';
        } finally {
            this.isLoading = false;
        }
    },

    scrollToBottom() {
        this.$nextTick(() => {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    },

    formatTime() {
        return new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date());
    },

    copiedIndex: null,

    async copyMessage(content, index) {
        if (!content) return;
        try {
            await navigator.clipboard.writeText(content);
            this.copiedIndex = index;
            setTimeout(() => {
                if (this.copiedIndex === index) {
                    this.copiedIndex = null;
                }
            }, 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    },

    formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    validateAndSetFile(file) {
        if (!file) return;

        if (file.size === 0) {
            this.errorMessage = 'The attached file is empty (0 bytes).';
            return;
        }

        if (file.size > this.maxFileSize) {
            this.errorMessage = 'File size exceeds the 35MB limit.';
            return;
        }

        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (!this.allowedExtensions.includes(ext)) {
            this.errorMessage = `Unsupported file type (.${ext}). Supported: PDF, CSV, XLSX, DOCX, TXT, JPG, PNG.`;
            return;
        }

        let typeCategory = 'document';
        let previewUrl = null;
        if (['csv', 'xlsx'].includes(ext)) {
            typeCategory = 'spreadsheet';
        } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
            typeCategory = 'image';
            if (typeof URL !== 'undefined' && URL.createObjectURL) {
                try {
                    previewUrl = URL.createObjectURL(file);
                } catch (e) {}
            }
        } else if (ext === 'txt') {
            typeCategory = 'text';
        }

        this.selectedFile = {
            file,
            name: file.name,
            size: this.formatBytes(file.size),
            rawSize: file.size,
            type: typeCategory,
            extension: ext,
            previewUrl,
        };
        this.errorMessage = '';
    },

    handleFileSelect(event) {
        const file = event.target.files?.[0];
        if (file) {
            this.validateAndSetFile(file);
        }
        if (this.$refs.fileInput) {
            this.$refs.fileInput.value = '';
        }
    },

    handleFileDrop(event) {
        this.isDraggingOver = false;
        const dt = event.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            this.validateAndSetFile(dt.files[0]);
        }
    },

    removeFile(revokeUrl = true) {
        if (revokeUrl && this.selectedFile?.previewUrl && typeof URL !== 'undefined' && URL.revokeObjectURL) {
            try {
                URL.revokeObjectURL(this.selectedFile.previewUrl);
            } catch (e) {}
        }
        this.selectedFile = null;
        if (this.$refs.fileInput) {
            this.$refs.fileInput.value = '';
        }
    },

    triggerFileInput() {
        this.$refs.fileInput?.click();
    },

    formatMarkdown(text) {
        if (!text) return '';

        // 1. Strip emojis, symbols, and pictographs, then sanitize HTML entities
        let escaped = text
            .replace(/[\p{Extended_Pictographic}\uFE0E\uFE0F\u{1F300}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F000}-\u{1F02F}\u{1F0A0}-\u{1F0FF}\u{1F100}-\u{1F64F}\u{1F680}-\u{1F6FF}]/gu, '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        // 2. Protect multi-line code blocks
        const codeBlocks = [];
        escaped = escaped.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
            const id = `___CODEBLOCK_${codeBlocks.length}___`;
            codeBlocks.push(`<pre class="my-2.5 overflow-x-auto rounded-xl bg-neutral-900 p-3 text-[11px] font-mono leading-relaxed text-neutral-100 shadow-inner border border-neutral-800"><code>${code.trim()}</code></pre>`);
            return id;
        });

        // 3. Render inline codes / identifiers as seamless consistent text (no badge, pill, or background)
        const inlineCodes = [];
        escaped = escaped.replace(/`([^`]+)`/g, (match, code) => {
            const id = `___INLINECODE_${inlineCodes.length}___`;
            inlineCodes.push(`<span class="font-medium text-neutral-900">${code}</span>`);
            return id;
        });

        // 4. Parse Tables
        escaped = escaped.replace(/((?:^|\n)\|[^\n]+\|\r?\n\|[\s:-|-]+\|\r?\n(?:\|[^\n]+\|\r?\n?)+)/g, (match) => {
            const lines = match.trim().split('\n').map(l => l.trim()).filter(l => l.length > 0);
            if (lines.length < 2) return match;

            const headerCells = lines[0].split('|').slice(1, -1).map(c => c.trim());
            const bodyRows = lines.slice(2);

            let html = '<div class="my-3 overflow-x-auto rounded-xl border border-neutral-200/80 shadow-xs"><table class="min-w-full divide-y divide-neutral-200 text-left text-xs">';
            html += '<thead class="bg-neutral-100/80 font-semibold text-neutral-900"><tr>';
            for (const h of headerCells) {
                html += `<th class="px-3 py-2 text-[10px] font-bold tracking-wider text-neutral-700 uppercase">${h}</th>`;
            }
            html += '</tr></thead><tbody class="divide-y divide-neutral-100 bg-white">';
            for (const row of bodyRows) {
                const cells = row.split('|').slice(1, -1).map(c => c.trim());
                html += '<tr class="hover:bg-neutral-50/70 transition-colors">';
                for (const cell of cells) {
                    html += `<td class="px-3 py-2 text-neutral-800 text-[11px]">${cell}</td>`;
                }
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            return '\n' + html + '\n';
        });

        // 5. Headings:
        // # H1
        escaped = escaped.replace(/^#\s+(.*?)$/gm, '<h3 class="mt-4 mb-2 text-sm font-bold text-neutral-900 tracking-tight flex items-center gap-1.5 border-b border-neutral-200/60 pb-1.5">$1</h3>');
        // ## H2
        escaped = escaped.replace(/^##\s+(.*?)$/gm, '<h4 class="mt-3.5 mb-1.5 text-xs sm:text-sm font-bold text-neutral-900 tracking-tight flex items-center gap-1.5">$1</h4>');
        // ### H3
        escaped = escaped.replace(/^###\s+(.*?)$/gm, '<h5 class="mt-3 mb-1 text-xs font-bold text-neutral-900 flex items-center gap-1.5 text-primary-950">$1</h5>');
        // #### H4
        escaped = escaped.replace(/^####\s+(.*?)$/gm, '<h6 class="mt-2.5 mb-1 text-xs font-semibold text-neutral-800 uppercase tracking-wider">$1</h6>');

        // 6. Blockquotes: > quote
        escaped = escaped.replace(/^>\s+(.*?)$/gm, '<blockquote class="my-2 border-l-3 border-primary-500 bg-primary-50/40 py-1.5 px-3 rounded-r-lg text-xs italic text-neutral-700">$1</blockquote>');

        // 7. Lists (Must be parsed before bold & italic so asterisks in bullets do not interfere with inline markup):
        // Process unordered lists (- item or * item)
        escaped = escaped.replace(/(?:^|\n)((?:[ \t]*[-*]\s+.*(?:\n|$))+)/g, (match) => {
            const items = match.trim().split('\n').map(line => {
                const content = line.replace(/^[ \t]*[-*]\s+/, '').trim();
                return `<li class="relative pl-1 leading-relaxed">${content}</li>`;
            });
            return '\n<ul class="my-2 ml-4 list-disc space-y-1 text-neutral-700 text-xs leading-relaxed marker:text-primary-500">' + items.join('') + '</ul>\n';
        });

        // Process ordered lists (1. item, 2. item)
        escaped = escaped.replace(/(?:^|\n)((?:[ \t]*\d+\.\s+.*(?:\n|$))+)/g, (match) => {
            const items = match.trim().split('\n').map(line => {
                const content = line.replace(/^[ \t]*\d+\.\s+/, '').trim();
                return `<li class="relative pl-1 leading-relaxed">${content}</li>`;
            });
            return '\n<ol class="my-2 ml-4 list-decimal space-y-1 text-neutral-700 text-xs leading-relaxed marker:font-semibold marker:text-neutral-500">' + items.join('') + '</ol>\n';
        });

        // 8. Bold & Italic & Strikethrough (Selective emphasis on necessary terms):
        // Bold + Italic: ***text***
        escaped = escaped.replace(/\*\*\*([^\*\n]+?)\*\*\*/g, '<strong class="font-bold text-neutral-950 font-semibold"><em class="italic">$1</em></strong>');
        // Bold: **text** or __text__
        escaped = escaped.replace(/\*\*([^\*\n]+?)\*\*/g, '<strong class="font-bold text-neutral-950 font-semibold">$1</strong>');
        escaped = escaped.replace(/__([^_\n]+?)__/g, '<strong class="font-bold text-neutral-950 font-semibold">$1</strong>');
        // Italic: *text* or _text_ (non-whitespace bounded)
        escaped = escaped.replace(/(?<!\*)\*([^\*\n\s](?:[^\*\n]*?[^\*\n\s])?)\*(?!\*)/g, '<em class="italic text-neutral-800">$1</em>');
        escaped = escaped.replace(/(?<!_)_([^_\n\s](?:[^_\n]*?[^_\n\s])?)_(?!_)/g, '<em class="italic text-neutral-800">$1</em>');
        // Strikethrough: ~~text~~
        escaped = escaped.replace(/~~([^~\n]+?)~~/g, '<del class="line-through text-neutral-500">$1</del>');

        // 9. Links: [Title](url)
        escaped = escaped.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, title, url) => {
            const safeUrl = url.startsWith('/') || url.startsWith('http') ? url : '#';
            return `<a href="${safeUrl}" class="inline-flex items-center gap-1 font-semibold text-primary-600 underline hover:text-primary-800 transition-colors" target="_self">${title}</a>`;
        });

        // 10. Process paragraphs and line breaks:
        const paragraphs = escaped.split(/\n\s*\n/);
        const formatted = paragraphs.map(para => {
            const trimmed = para.trim();
            if (!trimmed) return '';
            if (/^<(h[1-6]|ul|ol|table|blockquote|pre|div)\b/i.test(trimmed)) {
                return trimmed;
            }
            const withBreaks = trimmed.replace(/\n/g, '<br>');
            return `<p class="my-1.5 leading-relaxed text-neutral-700">${withBreaks}</p>`;
        }).filter(p => p.length > 0).join('\n');

        // 11. Restore protected inline codes and code blocks
        let finalHtml = formatted;
        inlineCodes.forEach((codeHtml, i) => {
            finalHtml = finalHtml.replace(`___INLINECODE_${i}___`, codeHtml);
        });
        codeBlocks.forEach((codeHtml, i) => {
            finalHtml = finalHtml.replace(`___CODEBLOCK_${i}___`, codeHtml);
        });

        return finalHtml;
    },

    /**
     * Extract an item name from the prompt or from known items catalog.
     */
    extractItemName(text) {
        if (!text) return null;
        const trimmed = text.trim();

        // 1. Check known items list if available
        if (Array.isArray(this.knownItems) && this.knownItems.length > 0) {
            const sorted = [...this.knownItems].sort((a, b) => b.length - a.length);
            for (const item of sorted) {
                if (!item || item.length < 3) continue;
                const regex = new RegExp(`\\b${item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');
                if (regex.test(trimmed)) {
                    return item;
                }
                const simplified = item.replace(/\s*\([^)]*\)/g, '').trim();
                if (simplified && simplified.length >= 3 && simplified !== item) {
                    const simpleRegex = new RegExp(`\\b${simplified.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');
                    if (simpleRegex.test(trimmed)) {
                        return simplified;
                    }
                }
            }
        }

        // 2. Pattern extraction from common query templates
        const patterns = [
            /\b(?:why is|why are)\s+(.+?)\s+(?:at high risk|considered high risk|considered low stock|high risk|low stock|low in stock|critical|at risk|failing|delayed|short)\b/i,
            /\b(?:what is|what\'s|check|show|get)\s+(?:the\s+)?(?:predicted\s+demand|stock|quantity|level|status|lead time|details|record|info|history)\s+(?:for|of|on)\s+(.+?)(?:\?|\.|$)/i,
            /\b(?:tell me about|information on|details on|status of|status on|update on)\s+(.+?)(?:\?|\.|$)/i,
            /\bhow many\s+(.+?)\s+(?:do we have|are left|in stock|are in warehouse|available|on hand)\b/i,
            /\b(?:review|inspect|check)\s+(.+?)(?:\?|\.|$)/i,
        ];

        const genericExclusions = [
            'this item', 'that item', 'the item', 'these items', 'those items', 'an item', 'item', 'items',
            'this', 'that', 'our inventory', 'the inventory', 'inventory', 'stock', 'it', 'everything', 'anything',
        ];

        for (const pattern of patterns) {
            const match = trimmed.match(pattern);
            if (match && match[1]) {
                let candidate = match[1].trim().replace(/[?!.,;:]+$/, '').trim();
                const lower = candidate.toLowerCase();
                if (!genericExclusions.includes(lower) && candidate.length >= 2 && candidate.length <= 40) {
                    return candidate;
                }
            }
        }

        return null;
    },

    /**
     * Determine context-aware status message matching user's intent and attachment.
     */
    determineStatusMessage(text, attachment = null) {
        const trimmed = (text || '').trim();
        const normalized = trimmed.toLowerCase();

        if (attachment) {
            if (attachment.type === 'image') {
                const detectedItem = this.extractItemName(trimmed);
                if (detectedItem) return `Reviewing ${detectedItem}...`;
                return 'Analyzing the attached image...';
            }

            if (attachment.type === 'spreadsheet') {
                if (/\b(reorder|order|restock|replenish|procure|purchase|mag-reorder|i-reorder)\b/i.test(normalized)) {
                    return 'Reviewing reorder needs...';
                }
                if (/\b(low stock|low in stock|low on stock|running out|paubos|critical stock|shortage)\b/i.test(normalized)) {
                    return 'Checking low-stock items...';
                }
                if (/\b(forecast|demand|predicted|projection|trend)\b/i.test(normalized)) {
                    return 'Analyzing demand data...';
                }
                const detectedItem = this.extractItemName(trimmed);
                if (detectedItem) return `Reviewing ${detectedItem}...`;
                return 'Reviewing your inventory file...';
            }

            if (['document', 'text'].includes(attachment.type)) {
                if (/\b(summar(?:y|ize|ise|ies|izing)?|overview|status|sitwasyon|kalagayan|kabuuan|lagom)\b/i.test(normalized)) {
                    return 'Preparing your inventory summary...';
                }
                const detectedItem = this.extractItemName(trimmed);
                if (detectedItem) return `Reviewing ${detectedItem}...`;
                return 'Reviewing your report...';
            }
        }

        if (!trimmed) return 'Looking into that...';

        // 1. Item-specific inquiry
        const detectedItem = this.extractItemName(trimmed);
        if (detectedItem) {
            return `Reviewing ${detectedItem}...`;
        }

        // 2. Expiry
        if (/\b(expir(?:y|e|ed|ing)?|shelf life|spoiled|panis)\b/i.test(normalized)) {
            return 'Checking expiring inventory...';
        }

        // 3. Reorder
        if (/\b(reorder|order|restock|replenish|procure|purchase|mag-reorder|i-reorder)\b/i.test(normalized)) {
            return 'Reviewing reorder needs...';
        }

        // 4. Out of stock / unavailable
        if (/\b(out of stock|zero stock|depleted|walang stock|ubos|unavailable)\b/i.test(normalized)) {
            return 'Checking unavailable items...';
        }

        // 5. Low stock / running out
        if (/\b(low stock|low in stock|low on stock|running out|paubos|critical stock|shortage)\b/i.test(normalized)) {
            return 'Checking low-stock items...';
        }

        // 6. Stock movement
        if (/\b(movement|movements|stock movement|stock in|stock out|issued|dispensed|transferred)\b/i.test(normalized)) {
            if (/\b(this month|monthly|buwan)\b/i.test(normalized)) {
                return 'Reviewing recent stock movements...';
            }
            if (/\b(this week|weekly|linggo)\b/i.test(normalized)) {
                return "Reviewing this week's inventory activity...";
            }
            return 'Reviewing recent stock movements...';
        }

        if (/\b(this week|what happened.*week|nangyari.*linggo)\b/i.test(normalized)) {
            return "Reviewing this week's inventory activity...";
        }

        // 7. Demand forecast explanation
        if (/\b(explain.*forecast|paliwanag.*forecast|meaning.*forecast)\b/i.test(normalized)) {
            return 'Reviewing the demand forecast...';
        }

        // 8. Demand forecast / predicted demand
        if (/\b(forecast|predicted demand|projection|projected|demand trend)\b/i.test(normalized)) {
            return 'Checking demand forecast...';
        }

        // 9. Risk analysis
        if (/\b(risk|at-risk|high risk|peligro|delikado)\b/i.test(normalized)) {
            return 'Checking inventory risk...';
        }

        // 10. Inventory summary
        if (/\b(summar(?:y|ize|ise|ies|izing)?|overview|status|sitwasyon|kalagayan|kabuuan|lagom)\b/i.test(normalized)) {
            return 'Preparing your inventory summary...';
        }

        // 11. General inventory question
        if (/\b(inventory|stock|supplies|gamot|items|bodega)\b/i.test(normalized)) {
            return 'Reviewing inventory data...';
        }

        return 'Looking into that...';
    },

    async sendSuggested(text) {
        this.input = text;
        await this.sendMessage();
    },

    async sendMessage() {
        const text = this.input.trim();
        const attached = this.selectedFile ? { ...this.selectedFile } : null;

        if ((!text && !attached) || this.isLoading) return;

        this.errorMessage = '';
        this.input = '';
        this.removeFile(false);

        this.loadingStatus = this.determineStatusMessage(text, attached);
        this.isLoading = true;

        const userMsg = {
            role: 'user',
            content: text || (attached ? `Please analyze this attached file (${attached.name}).` : ''),
            attachment: attached,
            time: this.formatTime(),
        };
        this.messages.push(userMsg);
        this.scrollToBottom();

        const historyPayload = this.messages.slice(-6).map((m) => ({
            role: m.role,
            content: m.content,
        }));

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            let response;

            if (attached && attached.file) {
                const formData = new FormData();
                if (text) {
                    formData.append('message', text);
                }
                if (this.conversationId) {
                    formData.append('conversation_id', this.conversationId);
                }
                formData.append('attachment', attached.file);
                formData.append('history', JSON.stringify(historyPayload));

                response = await fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Activity': 'passive',
                    },
                    body: formData,
                });
            } else {
                response = await fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Activity': 'passive',
                    },
                    body: JSON.stringify({
                        message: text,
                        conversation_id: this.conversationId,
                        history: historyPayload,
                    }),
                });
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || (data.errors && data.errors.attachment ? data.errors.attachment[0] : null) || 'The assistant is temporarily unavailable. Please try again.');
            }

            if (data.conversation_id) {
                this.conversationId = data.conversation_id;
            }
            if (data.conversation_title) {
                this.conversationTitle = data.conversation_title;
            }

            this.messages.push({
                role: 'assistant',
                content: data.reply || 'No response generated.',
                time: this.formatTime(),
                source: data.source || 'ai',
                statusHint: data.status_hint || null,
            });
        } catch (err) {
            this.errorMessage = err instanceof Error ? err.message : 'Unable to get an AI response right now.';
            this.messages.push({
                role: 'assistant',
                content: "I'm unable to generate an AI response right now. Please try again.",
                time: this.formatTime(),
                isError: true,
            });
        } finally {
            this.isLoading = false;
            this.scrollToBottom();
        }
    },
}));

Alpine.data('himsAiAssistant', ({
    endpoint,
    conversationsEndpoint = '/dashboard/ai-assistant/conversations',
    activeEndpoint = '/dashboard/ai-assistant/conversations/active',
    conversationShowBase = '/dashboard/ai-assistant/conversations',
    knownItems = []
}) => ({
    isOpen: false,
    endpoint,
    conversationsEndpoint,
    activeEndpoint,
    conversationShowBase,
    knownItems: Array.isArray(knownItems) ? knownItems : [],

    // Active conversation state
    conversationId: null,
    conversationTitle: '',
    messages: [],
    input: '',
    isLoading: false,
    loadingStatus: 'Looking into that...',
    errorMessage: '',
    selectedFile: null,
    isDraggingOver: false,
    allowedExtensions: ['pdf', 'csv', 'xlsx', 'docx', 'txt', 'jpg', 'jpeg', 'png'],
    maxFileSize: 35 * 1024 * 1024, // 35MB

    // History and navigation state
    viewMode: 'chat', // 'chat' | 'history'
    conversationsList: [],
    isHistoryLoading: false,
    showNewChatConfirm: false,
    hasRestoredActive: false,

    init() {
        this.restoreActiveConversation();
    },

    async restoreActiveConversation() {
        if (this.hasRestoredActive) return;
        try {
            const resp = await fetch(this.activeEndpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                const data = await resp.json();
                if (data.status === 'success' && data.conversation) {
                    this.conversationId = data.conversation.id;
                    this.conversationTitle = data.conversation.title;
                    this.messages = Array.isArray(data.conversation.messages) ? data.conversation.messages : [];
                }
            }
        } catch (e) {
            // Silently fallback to clean empty chat state
        } finally {
            this.hasRestoredActive = true;
        }
    },

    toggle() {
        this.isOpen = !this.isOpen;
        if (this.isOpen) {
            if (!this.hasRestoredActive) {
                this.restoreActiveConversation();
            }
            this.$nextTick(() => {
                this.scrollToBottom();
                if (this.viewMode === 'chat') {
                    this.$refs.chatInput?.focus();
                }
            });
        }
    },

    close() {
        // CLOSE CHATBOT ≠ NEW CHAT: Only hide the UI, preserve current conversation
        this.isOpen = false;
        this.viewMode = 'chat';
        this.showNewChatConfirm = false;
    },

    requestNewChat() {
        if (this.messages.length > 0) {
            this.showNewChatConfirm = true;
        } else {
            this.startNewChat();
        }
    },

    cancelNewChat() {
        this.showNewChatConfirm = false;
    },

    startNewChat() {
        this.showNewChatConfirm = false;
        this.conversationId = null;
        this.conversationTitle = '';
        this.messages.forEach(m => {
            if (m.attachment?.previewUrl && m.attachment.previewUrl.startsWith('blob:') && typeof URL !== 'undefined' && URL.revokeObjectURL) {
                try {
                    URL.revokeObjectURL(m.attachment.previewUrl);
                } catch (e) {}
            }
        });
        this.messages = [];
        this.errorMessage = '';
        this.removeFile(true);
        this.viewMode = 'chat';
        this.$nextTick(() => {
            this.$refs.chatInput?.focus();
        });
    },

    clearChat() {
        this.requestNewChat();
    },

    async toggleHistory() {
        if (this.viewMode === 'history') {
            this.viewMode = 'chat';
            this.$nextTick(() => this.scrollToBottom());
        } else {
            this.viewMode = 'history';
            await this.loadConversations();
        }
    },

    async loadConversations() {
        this.isHistoryLoading = true;
        try {
            const resp = await fetch(this.conversationsEndpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                const data = await resp.json();
                this.conversationsList = Array.isArray(data.conversations) ? data.conversations : [];
            }
        } catch (e) {
            console.error('Failed to load conversations', e);
        } finally {
            this.isHistoryLoading = false;
        }
    },

    get groupedConversations() {
        const groups = {};
        for (const conv of this.conversationsList) {
            const group = conv.date_group || 'Previous';
            if (!groups[group]) {
                groups[group] = [];
            }
            groups[group].push(conv);
        }
        return groups;
    },

    async selectConversation(id) {
        if (this.conversationId === id && this.messages.length > 0) {
            this.viewMode = 'chat';
            this.$nextTick(() => this.scrollToBottom());
            return;
        }

        this.isLoading = true;
        this.viewMode = 'chat';
        try {
            const url = `${this.conversationShowBase}/${id}`;
            const resp = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (resp.ok) {
                const data = await resp.json();
                if (data.status === 'success' && data.conversation) {
                    this.conversationId = data.conversation.id;
                    this.conversationTitle = data.conversation.title;
                    this.messages = Array.isArray(data.conversation.messages) ? data.conversation.messages : [];
                    this.$nextTick(() => this.scrollToBottom());
                }
            }
        } catch (e) {
            this.errorMessage = 'Failed to load selected conversation.';
        } finally {
            this.isLoading = false;
        }
    },

    scrollToBottom() {
        this.$nextTick(() => {
            const container = this.$refs.messagesContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    },

    formatTime() {
        return new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date());
    },

    copiedIndex: null,

    async copyMessage(content, index) {
        if (!content) return;
        try {
            await navigator.clipboard.writeText(content);
            this.copiedIndex = index;
            setTimeout(() => {
                if (this.copiedIndex === index) {
                    this.copiedIndex = null;
                }
            }, 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    },

    formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    },

    validateAndSetFile(file) {
        if (!file) return;

        if (file.size === 0) {
            this.errorMessage = 'The attached file is empty (0 bytes).';
            return;
        }

        if (file.size > this.maxFileSize) {
            this.errorMessage = 'File size exceeds the 35MB limit.';
            return;
        }

        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (!this.allowedExtensions.includes(ext)) {
            this.errorMessage = `Unsupported file type (.${ext}). Supported: PDF, CSV, XLSX, DOCX, TXT, JPG, PNG.`;
            return;
        }

        let typeCategory = 'document';
        let previewUrl = null;
        if (['csv', 'xlsx'].includes(ext)) {
            typeCategory = 'spreadsheet';
        } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
            typeCategory = 'image';
            if (typeof URL !== 'undefined' && URL.createObjectURL) {
                try {
                    previewUrl = URL.createObjectURL(file);
                } catch (e) {}
            }
        } else if (ext === 'txt') {
            typeCategory = 'text';
        }

        this.selectedFile = {
            file,
            name: file.name,
            size: this.formatBytes(file.size),
            rawSize: file.size,
            type: typeCategory,
            extension: ext,
            previewUrl,
        };
        this.errorMessage = '';
    },

    handleFileSelect(event) {
        const file = event.target.files?.[0];
        if (file) {
            this.validateAndSetFile(file);
        }
        if (this.$refs.fileInput) {
            this.$refs.fileInput.value = '';
        }
    },

    handleFileDrop(event) {
        this.isDraggingOver = false;
        const dt = event.dataTransfer;
        if (dt && dt.files && dt.files.length > 0) {
            this.validateAndSetFile(dt.files[0]);
        }
    },

    removeFile(revokeUrl = true) {
        if (revokeUrl && this.selectedFile?.previewUrl && typeof URL !== 'undefined' && URL.revokeObjectURL) {
            try {
                URL.revokeObjectURL(this.selectedFile.previewUrl);
            } catch (e) {}
        }
        this.selectedFile = null;
        if (this.$refs.fileInput) {
            this.$refs.fileInput.value = '';
        }
    },

    triggerFileInput() {
        this.$refs.fileInput?.click();
    },

    formatMarkdown(text) {
        if (!text) return '';

        // 1. Strip emojis, symbols, and pictographs, then sanitize HTML entities
        let escaped = text
            .replace(/[\p{Extended_Pictographic}\uFE0E\uFE0F\u{1F300}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F000}-\u{1F02F}\u{1F0A0}-\u{1F0FF}\u{1F100}-\u{1F64F}\u{1F680}-\u{1F6FF}]/gu, '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        // 2. Protect multi-line code blocks
        const codeBlocks = [];
        escaped = escaped.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
            const id = `___CODEBLOCK_${codeBlocks.length}___`;
            codeBlocks.push(`<pre class="my-2.5 overflow-x-auto rounded-xl bg-neutral-900 p-3 text-[11px] font-mono leading-relaxed text-neutral-100 shadow-inner border border-neutral-800"><code>${code.trim()}</code></pre>`);
            return id;
        });

        // 3. Render inline codes / identifiers as seamless consistent text (no badge, pill, or background)
        const inlineCodes = [];
        escaped = escaped.replace(/`([^`]+)`/g, (match, code) => {
            const id = `___INLINECODE_${inlineCodes.length}___`;
            inlineCodes.push(`<span class="font-medium text-neutral-900">${code}</span>`);
            return id;
        });

        // 4. Parse Tables
        escaped = escaped.replace(/((?:^|\n)\|[^\n]+\|\r?\n\|[\s:-|-]+\|\r?\n(?:\|[^\n]+\|\r?\n?)+)/g, (match) => {
            const lines = match.trim().split('\n').map(l => l.trim()).filter(l => l.length > 0);
            if (lines.length < 2) return match;

            const headerCells = lines[0].split('|').slice(1, -1).map(c => c.trim());
            const bodyRows = lines.slice(2);

            let html = '<div class="my-3 overflow-x-auto rounded-xl border border-neutral-200/80 shadow-xs"><table class="min-w-full divide-y divide-neutral-200 text-left text-xs">';
            html += '<thead class="bg-neutral-100/80 font-semibold text-neutral-900"><tr>';
            for (const h of headerCells) {
                html += `<th class="px-3 py-2 text-[10px] font-bold tracking-wider text-neutral-700 uppercase">${h}</th>`;
            }
            html += '</tr></thead><tbody class="divide-y divide-neutral-100 bg-white">';
            for (const row of bodyRows) {
                const cells = row.split('|').slice(1, -1).map(c => c.trim());
                html += '<tr class="hover:bg-neutral-50/70 transition-colors">';
                for (const cell of cells) {
                    html += `<td class="px-3 py-2 text-neutral-800 text-[11px]">${cell}</td>`;
                }
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            return '\n' + html + '\n';
        });

        // 5. Headings:
        // # H1
        escaped = escaped.replace(/^#\s+(.*?)$/gm, '<h3 class="mt-4 mb-2 text-sm font-bold text-neutral-900 tracking-tight flex items-center gap-1.5 border-b border-neutral-200/60 pb-1.5">$1</h3>');
        // ## H2
        escaped = escaped.replace(/^##\s+(.*?)$/gm, '<h4 class="mt-3.5 mb-1.5 text-xs sm:text-sm font-bold text-neutral-900 tracking-tight flex items-center gap-1.5">$1</h4>');
        // ### H3
        escaped = escaped.replace(/^###\s+(.*?)$/gm, '<h5 class="mt-3 mb-1 text-xs font-bold text-neutral-900 flex items-center gap-1.5 text-primary-950">$1</h5>');
        // #### H4
        escaped = escaped.replace(/^####\s+(.*?)$/gm, '<h6 class="mt-2.5 mb-1 text-xs font-semibold text-neutral-800 uppercase tracking-wider">$1</h6>');

        // 6. Blockquotes: > quote
        escaped = escaped.replace(/^>\s+(.*?)$/gm, '<blockquote class="my-2 border-l-3 border-primary-500 bg-primary-50/40 py-1.5 px-3 rounded-r-lg text-xs italic text-neutral-700">$1</blockquote>');

        // 7. Lists (Must be parsed before bold & italic so asterisks in bullets do not interfere with inline markup):
        // Process unordered lists (- item or * item)
        escaped = escaped.replace(/(?:^|\n)((?:[ \t]*[-*]\s+.*(?:\n|$))+)/g, (match) => {
            const items = match.trim().split('\n').map(line => {
                const content = line.replace(/^[ \t]*[-*]\s+/, '').trim();
                return `<li class="relative pl-1 leading-relaxed">${content}</li>`;
            });
            return '\n<ul class="my-2 ml-4 list-disc space-y-1 text-neutral-700 text-xs leading-relaxed marker:text-primary-500">' + items.join('') + '</ul>\n';
        });

        // Process ordered lists (1. item, 2. item)
        escaped = escaped.replace(/(?:^|\n)((?:[ \t]*\d+\.\s+.*(?:\n|$))+)/g, (match) => {
            const items = match.trim().split('\n').map(line => {
                const content = line.replace(/^[ \t]*\d+\.\s+/, '').trim();
                return `<li class="relative pl-1 leading-relaxed">${content}</li>`;
            });
            return '\n<ol class="my-2 ml-4 list-decimal space-y-1 text-neutral-700 text-xs leading-relaxed marker:font-semibold marker:text-neutral-500">' + items.join('') + '</ol>\n';
        });

        // 8. Bold & Italic & Strikethrough (Selective emphasis on necessary terms):
        // Bold + Italic: ***text***
        escaped = escaped.replace(/\*\*\*([^\*\n]+?)\*\*\*/g, '<strong class="font-bold text-neutral-950 font-semibold"><em class="italic">$1</em></strong>');
        // Bold: **text** or __text__
        escaped = escaped.replace(/\*\*([^\*\n]+?)\*\*/g, '<strong class="font-bold text-neutral-950 font-semibold">$1</strong>');
        escaped = escaped.replace(/__([^_\n]+?)__/g, '<strong class="font-bold text-neutral-950 font-semibold">$1</strong>');
        // Italic: *text* or _text_ (non-whitespace bounded)
        escaped = escaped.replace(/(?<!\*)\*([^\*\n\s](?:[^\*\n]*?[^\*\n\s])?)\*(?!\*)/g, '<em class="italic text-neutral-800">$1</em>');
        escaped = escaped.replace(/(?<!_)_([^_\n\s](?:[^_\n]*?[^_\n\s])?)_(?!_)/g, '<em class="italic text-neutral-800">$1</em>');
        // Strikethrough: ~~text~~
        escaped = escaped.replace(/~~([^~\n]+?)~~/g, '<del class="line-through text-neutral-500">$1</del>');

        // 9. Links: [Title](url)
        escaped = escaped.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, title, url) => {
            const safeUrl = url.startsWith('/') || url.startsWith('http') ? url : '#';
            return `<a href="${safeUrl}" class="inline-flex items-center gap-1 font-semibold text-primary-600 underline hover:text-primary-800 transition-colors" target="_self">${title}</a>`;
        });

        // 10. Process paragraphs and line breaks:
        const paragraphs = escaped.split(/\n\s*\n/);
        const formatted = paragraphs.map(para => {
            const trimmed = para.trim();
            if (!trimmed) return '';
            if (/^<(h[1-6]|ul|ol|table|blockquote|pre|div)\b/i.test(trimmed)) {
                return trimmed;
            }
            const withBreaks = trimmed.replace(/\n/g, '<br>');
            return `<p class="my-1.5 leading-relaxed text-neutral-700">${withBreaks}</p>`;
        }).filter(p => p.length > 0).join('\n');

        // 11. Restore protected inline codes and code blocks
        let finalHtml = formatted;
        inlineCodes.forEach((codeHtml, i) => {
            finalHtml = finalHtml.replace(`___INLINECODE_${i}___`, codeHtml);
        });
        codeBlocks.forEach((codeHtml, i) => {
            finalHtml = finalHtml.replace(`___CODEBLOCK_${i}___`, codeHtml);
        });

        return finalHtml;
    },

    /**
     * Extract an item name from the prompt or from known items catalog.
     */
    extractItemName(text) {
        if (!text) return null;
        const trimmed = text.trim();

        // 1. Check known items list if available
        if (Array.isArray(this.knownItems) && this.knownItems.length > 0) {
            const sorted = [...this.knownItems].sort((a, b) => b.length - a.length);
            for (const item of sorted) {
                if (!item || item.length < 3) continue;
                const regex = new RegExp(`\\b${item.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');
                if (regex.test(trimmed)) {
                    return item;
                }
                const simplified = item.replace(/\s*\([^)]*\)/g, '').trim();
                if (simplified && simplified.length >= 3 && simplified !== item) {
                    const simpleRegex = new RegExp(`\\b${simplified.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');
                    if (simpleRegex.test(trimmed)) {
                        return simplified;
                    }
                }
            }
        }

        // 2. Pattern extraction from common query templates
        const patterns = [
            /\b(?:why is|why are)\s+(.+?)\s+(?:at high risk|considered high risk|considered low stock|high risk|low stock|low in stock|critical|at risk|failing|delayed|short)\b/i,
            /\b(?:what is|what\'s|check|show|get)\s+(?:the\s+)?(?:predicted\s+demand|stock|quantity|level|status|lead time|details|record|info|history)\s+(?:for|of|on)\s+(.+?)(?:\?|\.|$)/i,
            /\b(?:tell me about|information on|details on|status of|status on|update on)\s+(.+?)(?:\?|\.|$)/i,
            /\bhow many\s+(.+?)\s+(?:do we have|are left|in stock|are in warehouse|available|on hand)\b/i,
            /\b(?:review|inspect|check)\s+(.+?)(?:\?|\.|$)/i,
        ];

        const genericExclusions = [
            'this item', 'that item', 'the item', 'these items', 'those items', 'an item', 'item', 'items',
            'this', 'that', 'our inventory', 'the inventory', 'inventory', 'stock', 'it', 'everything', 'anything',
        ];

        for (const pattern of patterns) {
            const match = trimmed.match(pattern);
            if (match && match[1]) {
                let candidate = match[1].trim().replace(/[?!.,;:]+$/, '').trim();
                const lower = candidate.toLowerCase();
                if (!genericExclusions.includes(lower) && candidate.length >= 2 && candidate.length <= 40) {
                    return candidate;
                }
            }
        }

        return null;
    },

    /**
     * Determine context-aware status message matching user's intent and attachment.
     */
    determineStatusMessage(text, attachment = null) {
        const trimmed = (text || '').trim();
        const normalized = trimmed.toLowerCase();

        if (attachment) {
            if (attachment.type === 'image') {
                const detectedItem = this.extractItemName(trimmed);
                if (detectedItem) return `Reviewing ${detectedItem}...`;
                return 'Analyzing the attached image...';
            }

            if (attachment.type === 'spreadsheet') {
                if (/\b(reorder|order|restock|replenish|procure|purchase|mag-reorder|i-reorder)\b/i.test(normalized)) {
                    return 'Reviewing reorder needs...';
                }
                if (/\b(low stock|low in stock|low on stock|running out|paubos|critical stock|shortage)\b/i.test(normalized)) {
                    return 'Checking low-stock items...';
                }
                if (/\b(forecast|demand|predicted|projection|trend)\b/i.test(normalized)) {
                    return 'Analyzing demand data...';
                }
                const detectedItem = this.extractItemName(trimmed);
                if (detectedItem) return `Reviewing ${detectedItem}...`;
                return 'Reviewing your inventory file...';
            }

            if (['document', 'text'].includes(attachment.type)) {
                if (/\b(summar(?:y|ize|ise|ies|izing)?|overview|status|sitwasyon|kalagayan|kabuuan|lagom)\b/i.test(normalized)) {
                    return 'Preparing your inventory summary...';
                }
                const detectedItem = this.extractItemName(trimmed);
                if (detectedItem) return `Reviewing ${detectedItem}...`;
                return 'Reviewing your report...';
            }
        }

        if (!trimmed) return 'Looking into that...';

        // 1. Item-specific inquiry
        const detectedItem = this.extractItemName(trimmed);
        if (detectedItem) {
            return `Reviewing ${detectedItem}...`;
        }

        // 2. Expiry
        if (/\b(expir(?:y|e|ed|ing)?|shelf life|spoiled|panis)\b/i.test(normalized)) {
            return 'Checking expiring inventory...';
        }

        // 3. Reorder
        if (/\b(reorder|order|restock|replenish|procure|purchase|mag-reorder|i-reorder)\b/i.test(normalized)) {
            return 'Reviewing reorder needs...';
        }

        // 4. Out of stock / unavailable
        if (/\b(out of stock|zero stock|depleted|walang stock|ubos|unavailable)\b/i.test(normalized)) {
            return 'Checking unavailable items...';
        }

        // 5. Low stock / running out
        if (/\b(low stock|low in stock|low on stock|running out|paubos|critical stock|shortage)\b/i.test(normalized)) {
            return 'Checking low-stock items...';
        }

        // 6. Stock movement
        if (/\b(movement|movements|stock movement|stock in|stock out|issued|dispensed|transferred)\b/i.test(normalized)) {
            if (/\b(this month|monthly|buwan)\b/i.test(normalized)) {
                return 'Reviewing recent stock movements...';
            }
            if (/\b(this week|weekly|linggo)\b/i.test(normalized)) {
                return "Reviewing this week's inventory activity...";
            }
            return 'Reviewing recent stock movements...';
        }

        if (/\b(this week|what happened.*week|nangyari.*linggo)\b/i.test(normalized)) {
            return "Reviewing this week's inventory activity...";
        }

        // 7. Demand forecast explanation
        if (/\b(explain.*forecast|paliwanag.*forecast|meaning.*forecast)\b/i.test(normalized)) {
            return 'Reviewing the demand forecast...';
        }

        // 8. Demand forecast / predicted demand
        if (/\b(forecast|predicted demand|projection|projected|demand trend)\b/i.test(normalized)) {
            return 'Checking demand forecast...';
        }

        // 9. Risk analysis
        if (/\b(risk|at-risk|high risk|peligro|delikado)\b/i.test(normalized)) {
            return 'Checking inventory risk...';
        }

        // 10. Inventory summary
        if (/\b(summar(?:y|ize|ise|ies|izing)?|overview|status|sitwasyon|kalagayan|kabuuan|lagom)\b/i.test(normalized)) {
            return 'Preparing your inventory summary...';
        }

        // 11. General inventory question
        if (/\b(inventory|stock|supplies|gamot|items|bodega)\b/i.test(normalized)) {
            return 'Reviewing inventory data...';
        }

        return 'Looking into that...';
    },

    async sendSuggested(text) {
        this.input = text;
        await this.sendMessage();
    },

    async sendMessage() {
        const text = this.input.trim();
        const attached = this.selectedFile ? { ...this.selectedFile } : null;

        if ((!text && !attached) || this.isLoading) return;

        this.errorMessage = '';
        this.input = '';
        this.removeFile(false);

        this.loadingStatus = this.determineStatusMessage(text, attached);
        this.isLoading = true;

        const userMsg = {
            role: 'user',
            content: text || (attached ? `Please analyze this attached file (${attached.name}).` : ''),
            attachment: attached,
            time: this.formatTime(),
        };
        this.messages.push(userMsg);
        this.scrollToBottom();

        const historyPayload = this.messages.slice(-6).map((m) => ({
            role: m.role,
            content: m.content,
        }));

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            let response;

            if (attached && attached.file) {
                const formData = new FormData();
                if (text) {
                    formData.append('message', text);
                }
                if (this.conversationId) {
                    formData.append('conversation_id', this.conversationId);
                }
                formData.append('attachment', attached.file);
                formData.append('history', JSON.stringify(historyPayload));

                response = await fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Activity': 'passive',
                    },
                    body: formData,
                });
            } else {
                response = await fetch(this.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Activity': 'passive',
                    },
                    body: JSON.stringify({
                        message: text,
                        conversation_id: this.conversationId,
                        history: historyPayload,
                    }),
                });
            }

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || (data.errors && data.errors.attachment ? data.errors.attachment[0] : null) || 'The assistant is temporarily unavailable. Please try again.');
            }

            if (data.conversation_id) {
                this.conversationId = data.conversation_id;
            }
            if (data.conversation_title) {
                this.conversationTitle = data.conversation_title;
            }

            this.messages.push({
                role: 'assistant',
                content: data.reply || 'No response generated.',
                time: this.formatTime(),
                source: data.source || 'ai',
                statusHint: data.status_hint || null,
            });
        } catch (err) {
            this.errorMessage = err instanceof Error ? err.message : 'Unable to get an AI response right now.';
            this.messages.push({
                role: 'assistant',
                content: "I'm unable to generate an AI response right now. Please try again.",
                time: this.formatTime(),
                isError: true,
            });
        } finally {
            this.isLoading = false;
            this.scrollToBottom();
        }
    },
}));


Alpine.data('procurementWorkspace', ({
    activeTab = 'orders_revisions',
    items = [],
    suppliers = [],
    supplierTerms = {},
    initial = {},
} = {}) => ({
    activeTab,
    items,
    suppliers,
    supplierTerms,
    itemId: String(initial.itemId || ''),
    supplierId: String(initial.supplierId || ''),
    quantity: Number(initial.quantity || 1),
    deliveryDate: String(initial.deliveryDate || ''),
    selectedPo: null,
    selectedPoCxml: '',
    selectedPoNumber: '',
    showCxmlModal: false,

    selectedItem() {
        return this.items.find((item) => String(item.id) === String(this.itemId)) || null;
    },

    selectedSupplier() {
        return this.suppliers.find((supplier) => String(supplier.id) === String(this.supplierId)) || null;
    },

    selectedTerms() {
        const item = this.selectedItem();
        const supplier = this.selectedSupplier();
        if (!item || !supplier) return null;

        return this.supplierTerms[`${supplier.id}:${item.id}`] || {
            unit_cost: Number(item.catalog_unit_cost || 0),
            currency: 'PHP',
            minimum_order_quantity: 1,
            lead_time_days: Number(supplier.lead_time_days || item.lead_time_days || 7),
            price_source: 'item_catalog',
        };
    },

    trustedUnitCost() {
        return Number(this.selectedTerms()?.unit_cost || 0);
    },

    orderTotal() {
        return Math.max(0, Number(this.quantity || 0)) * this.trustedUnitCost();
    },

    minimumOrderQuantity() {
        return Math.max(1, Number(this.selectedTerms()?.minimum_order_quantity || 1));
    },

    expectedDeliveryLabel() {
        if (this.deliveryDate) return this.formatDate(this.deliveryDate);

        const date = new Date();
        date.setHours(12, 0, 0, 0);
        date.setDate(date.getDate() + Math.max(0, Number(this.selectedTerms()?.lead_time_days || 7)));

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    },

    isStockLow() {
        const item = this.selectedItem();
        if (!item) return false;
        return Number(item.current_stock || 0) <= Number(item.reorder_point || 0);
    },


    useSuggestedQuantity() {
        const suggested = Number(this.selectedItem()?.suggested_order_quantity || 0);
        if (suggested > 0) this.quantity = Math.max(suggested, this.minimumOrderQuantity());
    },

    openPurchaseOrderReview(form) {
        if (!form || !form.reportValidity()) return;
        this.$dispatch('open-modal', 'review-purchase-order');
    },

    openPurchaseOrderDetails(order) {
        this.selectedPo = order;
        this.$dispatch('open-modal', 'purchase-order-details');
    },

    formatCurrency(value, currency = 'PHP') {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: currency || 'PHP',
            minimumFractionDigits: 2,
        }).format(Number(value || 0));
    },

    formatNumber(value, digits = 0) {
        return new Intl.NumberFormat(undefined, {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        }).format(Number(value || 0));
    },

    formatDate(value) {
        if (!value) return 'Not specified';
        const date = new Date(`${String(value).slice(0, 10)}T12:00:00`);
        if (Number.isNaN(date.getTime())) return String(value);

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(date);
    },
}));

Alpine.data('himsToastNotifications', (initialToasts = []) => ({
    toasts: [],

    init() {
        if (Array.isArray(initialToasts)) {
            initialToasts.forEach((toast) => {
                this.addToast(toast);
            });
        }
    },

    addToast(payload) {
        if (!payload || !payload.message) return;

        const duration = Number(payload.duration || 5000);
        const toast = {
            id: payload.id || `toast-${Date.now()}-${Math.random().toString(36).substring(2, 9)}`,
            type: payload.type || 'success',
            title: payload.title || '',
            message: payload.message,
            visible: true,
            progress: 100,
            duration,
            remaining: duration,
            interval: null,
            isPaused: false,
        };

        this.startTimer(toast);
        this.toasts.push(toast);
    },

    startTimer(toast) {
        const step = 50;
        toast.interval = setInterval(() => {
            if (toast.isPaused) return;

            toast.remaining -= step;
            toast.progress = Math.max(0, (toast.remaining / toast.duration) * 100);

            if (toast.remaining <= 0) {
                this.dismiss(toast);
            }
        }, step);
    },

    pauseTimer(toast) {
        toast.isPaused = true;
    },

    resumeTimer(toast) {
        toast.isPaused = false;
    },

    dismiss(toast) {
        if (toast.interval) clearInterval(toast.interval);
        toast.visible = false;
        setTimeout(() => {
            this.toasts = this.toasts.filter((t) => t.id !== toast.id);
        }, 300);
    },
}));

Alpine.data('himsCameraScanner', himsCameraScanner);

Alpine.start();
