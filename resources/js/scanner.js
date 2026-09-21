import { Html5Qrcode, Html5QrcodeScannerState, Html5QrcodeSupportedFormats } from 'html5-qrcode';

/**
 * Play an audible tone for scan feedback using Web Audio API.
 * 880Hz (high tone) for success, 220Hz (low tone) for error.
 */
export const playScanAudio = (success = true) => {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.type = success ? 'sine' : 'sawtooth';
        osc.frequency.value = success ? 880 : 220;
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (success ? 0.18 : 0.35));

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + (success ? 0.18 : 0.35));

        setTimeout(() => {
            if (ctx.state !== 'closed') ctx.close();
        }, 500);
    } catch {
        // Silently ignore audio context restrictions if blocked by browser policy
    }
};

/**
 * Validate GS1-128 Serial Shipping Container Code (SSCC-18).
 * Supports optional GS1 Application Identifier (00) prefix and validates Modulo 10 check digit.
 *
 * @param {string} rawCode
 * @returns {{ valid: boolean, code: string, message: string | null }}
 */
export const validateGs1Sscc = (rawCode) => {
    if (!rawCode) {
        return { valid: false, code: '', message: 'Please enter or scan an SSCC code.' };
    }

    let code = String(rawCode).trim().replace(/[\s-]/g, '');

    // Normalize GS1 Application Identifier (00) prefix
    if (code.startsWith('(00)')) {
        code = code.substring(4).trim();
    } else if (code.length === 20 && code.startsWith('00')) {
        code = code.substring(2);
    }

    if (!/^\d{18}$/.test(code)) {
        return {
            valid: false,
            code,
            message: `SSCC must be exactly 18 numeric digits (got ${code.length} characters).`,
        };
    }

    // Modulo 10 check digit validation matching COA GAM / GS1-128 standards
    const digits = code.split('').map(Number);
    const checkDigit = digits.pop();

    let sum = 0;
    const reversed = digits.reverse();
    for (let i = 0; i < reversed.length; i++) {
        const multiplier = (i % 2 === 0) ? 3 : 1;
        sum += reversed[i] * multiplier;
    }

    const calculatedCheck = (10 - (sum % 10)) % 10;
    if (calculatedCheck !== checkDigit) {
        return {
            valid: false,
            code,
            message: `Invalid GS1 Modulo-10 check digit: expected ${calculatedCheck}, got ${checkDigit}.`,
        };
    }

    return { valid: true, code, message: null };
};

/**
 * DOMException names that a different camera option can plausibly resolve: the
 * device is still being released, is shared with another application, or the
 * requested device id no longer exists. A refused permission is never in here,
 * because asking for a different camera cannot grant it.
 */
const RECOVERABLE_CAMERA_ERRORS = [
    'NotReadableError',
    'TrackStartError',
    'AbortError',
    'OverconstrainedError',
    'ConstraintNotSatisfiedError',
    'NotFoundError',
    'DevicesNotFoundError',
    'SourceUnavailableError',
];

/** DOMException names the browser uses for camera acquisition failures. */
const KNOWN_CAMERA_ERROR_NAMES = [
    ...RECOVERABLE_CAMERA_ERRORS,
    'NotAllowedError',
    'PermissionDeniedError',
    'SecurityError',
    'TypeError',
];

/**
 * Alpine component for camera-based barcode and QR code scanning.
 *
 * The camera lifecycle is explicit end to end - environment, device discovery,
 * permission state, acquisition, decoding and teardown each report their own
 * real outcome. The operator is only ever told what the browser actually said,
 * and the manual fallbacks stay available without being presented as the
 * primary way to scan.
 */
export const himsCameraScanner = ({
    containerId,
    targetInputId = null,
    autoSubmit = false,
    autoClose = true,
    eventName = 'hims-code-scanned',
    validateFormat = null,
} = {}) => ({
    isOpen: false,
    isScanning: false,
    isStarting: false,
    isProcessingFile: false,
    isLocked: false,
    scanner: null,
    errorKind: null,
    errorTitle: null,
    errorMessage: null,
    errorDetail: null,
    notice: null,
    cameras: [],
    selectedCameraId: null,
    permissionState: 'unknown',
    lastScannedCode: null,
    manualCode: '',
    facingMode: (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent || '') ? 'environment' : 'user'),
    runId: 0,
    onPageHideHandler: null,
    onBeforeUnloadHandler: null,

    init() {
        // Releasing the device on unload has to be synchronous: an awaited stop()
        // never finishes once the page is being torn down.
        this.onPageHideHandler = () => this.releaseCamera();
        this.onBeforeUnloadHandler = () => this.releaseCamera();
        window.addEventListener('pagehide', this.onPageHideHandler);
        window.addEventListener('beforeunload', this.onBeforeUnloadHandler);
    },

    destroy() {
        // Alpine re-renders (task list changes, navigation away) recreate this
        // component. Without this the window listeners accumulate per instance
        // and keep stale streams alive behind a closed scanner.
        if (this.onPageHideHandler) {
            window.removeEventListener('pagehide', this.onPageHideHandler);
            this.onPageHideHandler = null;
        }
        if (this.onBeforeUnloadHandler) {
            window.removeEventListener('beforeunload', this.onBeforeUnloadHandler);
            this.onBeforeUnloadHandler = null;
        }
        this.releaseCamera();
    },

    /** Cameras whose label is readable, which only happens once permission is granted. */
    get labelledCameras() {
        return this.cameras.filter((camera) => camera.id && camera.label);
    },

    /** A device chooser is only worth showing when there is a real choice to make. */
    get showCameraSelector() {
        return this.labelledCameras.length > 1;
    },

    /**
     * The address-bar walkthrough is only correct when the browser has actually
     * blocked the site. A dismissed prompt or a refused request is not a blocked
     * site, and sending the operator into browser settings for those wastes their
     * time without fixing anything.
     */
    get showPermissionInstructions() {
        return this.errorKind === 'permission' && this.permissionState === 'denied';
    },

    clearError() {
        this.errorKind = null;
        this.errorTitle = null;
        this.errorMessage = null;
        this.errorDetail = null;
    },

    applyError({ kind, title, message, detail = null }) {
        this.isScanning = false;
        this.errorKind = kind;
        this.errorTitle = title;
        this.errorMessage = message;
        this.errorDetail = detail;
    },

    async open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.clearError();
        this.notice = null;
        this.lastScannedCode = null;
        this.manualCode = '';
        this.isProcessingFile = false;
        this.isLocked = false;

        // The camera surface is measured from the rendered viewfinder, so the
        // element has to be mounted and painted before acquisition starts.
        await this.$nextTick();
        if (!this.isOpen) return;

        // Move focus into the dialog so Escape and Tab work without a mouse.
        try {
            this.$refs.dialogClose?.focus();
        } catch (_) {}

        await new Promise((resolve) => requestAnimationFrame(resolve));
        await this.delay(60);
        if (!this.isOpen) return;

        await this.startScanner();
    },

    async close() {
        this.isOpen = false;
        await this.stopScanner();
        this.clearError();
        this.notice = null;
        this.manualCode = '';
        this.isProcessingFile = false;
        this.isLocked = false;
    },

    async startScanner() {
        if (this.isStarting || this.isScanning) return;

        const containerEl = document.getElementById(containerId);
        if (!containerEl) {
            this.applyError({
                kind: 'unknown',
                title: 'Scanner Unavailable',
                message: 'The scanner preview area is missing from this page. Reload the page and open the scanner again.',
            });
            return;
        }

        // Every attempt owns a run id. Teardown bumps it, which cancels whatever
        // start is still in flight instead of letting two acquisitions race for
        // the same device - the second one is what the browser refuses.
        const runId = ++this.runId;
        this.isStarting = true;
        this.clearError();
        this.notice = null;

        try {
            // 1. Environment. An insecure origin loses navigator.mediaDevices
            //    entirely, so this is answered before anything asks for a stream
            //    and is never reported as a permission problem.
            const environmentError = this.checkEnvironment();
            if (environmentError) {
                this.applyError(environmentError);
                return;
            }

            // 2. Permission state. Advisory only - the getUserMedia result stays
            //    authoritative - but it is what lets a dismissed prompt be told
            //    apart from a site the browser has actually blocked.
            this.permissionState = await this.readPermissionState();
            if (runId !== this.runId) return;

            // 3. Devices. enumerateDevices() lists video inputs whether or not
            //    permission has been granted - the labels stay blank, not the
            //    entries - so an empty list means there is no hardware and there
            //    is no point prompting for a camera that does not exist.
            const discovered = await this.listCameras();
            if (runId !== this.runId) return;

            if (discovered.ok && discovered.devices.length === 0) {
                this.applyError({
                    kind: 'no-device',
                    title: 'No Camera Found',
                    message: 'No camera hardware was detected on this device. Connect a webcam and press Retry Camera, or use the fallbacks below.',
                });
                return;
            }

            if (discovered.ok) {
                this.cameras = discovered.devices;
            }

            const scanConfig = {
                fps: 15,
                // Deliberately no aspectRatio constraint. html5-qrcode applies it
                // with applyConstraints() as a required value, and a camera that
                // cannot produce exactly that ratio rejects the whole start - the
                // preview is sized by CSS instead.
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minDim = Math.min(viewfinderWidth, viewfinderHeight);
                    const width = Math.min(Math.floor(viewfinderWidth * 0.8), Math.floor(minDim * 0.85));
                    const height = Math.min(Math.floor(viewfinderHeight * 0.6), Math.floor(minDim * 0.55));
                    return {
                        width: Math.max(50, Math.min(width, viewfinderWidth - 10)),
                        height: Math.max(50, Math.min(height, viewfinderHeight - 10)),
                    };
                },
            };

            const onDecoded = (decodedText) => this.onScanSuccess(decodedText, 'camera');
            const onFrame = () => {
                // Scan loop tick; no code detected in the current frame
            };

            const candidates = this.buildCameraCandidates();
            let startError = null;
            let feedStalled = false;

            for (let attempt = 0; attempt < candidates.length; attempt++) {
                if (runId !== this.runId) {
                    await this.disposeScanner();
                    return;
                }

                try {
                    this.scanner = this.createScanner();
                    await this.scanner.start(candidates[attempt], scanConfig, onDecoded, onFrame);

                    if (runId !== this.runId) {
                        await this.disposeScanner();
                        return;
                    }

                    // A live-picture check runs only for a virtual camera: that is
                    // the one device known to open cleanly and then show a still
                    // frame forever. Real hardware that opened is trusted at once,
                    // so a working scanner never waits on this or risks being
                    // flagged, and the reading is only ever reported - never used
                    // to abandon a camera that did start.
                    if (this.isVirtualCandidate(candidates[attempt]) && (await this.isFeedLive()) === false) {
                        feedStalled = true;
                        console.warn('Virtual camera started but the feed is not updating');
                    }

                    const chosen = candidates[attempt];
                    if (chosen && typeof chosen === 'object' && chosen.facingMode) {
                        this.facingMode = chosen.facingMode;
                    }
                    startError = null;
                    break;
                } catch (startErr) {
                    startError = startErr;
                    console.warn('Camera start attempt failed:', startErr);

                    // Always release before the next attempt. A half-started
                    // attempt leaves the device held, which makes every following
                    // candidate fail for a reason that is not its own.
                    await this.disposeScanner();

                    if (!this.isTransientCameraError(startErr)) break;
                    if (runId !== this.runId) return;

                    await this.delay(250 * (attempt + 1));
                }
            }

            if (runId !== this.runId) {
                await this.disposeScanner();
                return;
            }

            if (startError) throw startError;

            this.isScanning = true;
            this.isLocked = false;

            if (feedStalled) {
                this.notice = this.showCameraSelector
                    ? 'This camera started but is not sending a live picture - usually a virtual camera (OBS, ManyCam, NDI) with no active source. Choose another camera above, or press Retry Camera.'
                    : 'This camera started but is not sending a live picture - usually a virtual camera (OBS, ManyCam, NDI) with no active source. Press Retry Camera, or connect a different camera.';
            }

            // Labels become readable only once permission has been granted, so
            // the device list is refreshed now that a stream is running. This
            // never opens a camera, so it is safe while scanning.
            const refreshed = await this.listCameras();
            if (refreshed.ok && refreshed.devices.some((camera) => camera.label)) {
                this.cameras = refreshed.devices;
            }
        } catch (error) {
            console.error('Camera initialization failed:', error);
            this.applyError(this.describeCameraError(error));
        } finally {
            if (runId === this.runId) this.isStarting = false;
        }
    },

    /**
     * Everything that is knowable before a stream is requested. Returns null when
     * the environment can support camera capture.
     */
    checkEnvironment() {
        const hasApi = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        if (hasApi) return null;

        // localhost, 127.0.0.1 and [::1] are secure contexts, so a missing camera
        // API there is a real browser limitation rather than a delivery problem.
        if (window.isSecureContext) {
            return {
                kind: 'unsupported',
                title: 'Camera Not Supported Here',
                message: 'This browser does not expose camera access to web pages. Use Chrome, Edge, Firefox, or Safari, or use the fallbacks below.',
            };
        }

        return {
            kind: 'insecure',
            title: 'Secure Connection Required',
            message: `Camera access is only available over HTTPS or on localhost, and this page is served from ${window.location.origin}, so the browser withholds the camera API.`,
        };
    },

    /**
     * Read the browser's own camera permission state. Advisory only: browsers
     * differ in support, so every failure falls back to 'unknown' and the
     * getUserMedia result remains the authority.
     */
    async readPermissionState() {
        if (!navigator.permissions || typeof navigator.permissions.query !== 'function') {
            return 'unsupported';
        }

        try {
            const status = await navigator.permissions.query({ name: 'camera' });
            return status.state || 'unsupported';
        } catch (_) {
            // Firefox and Safari reject the 'camera' permission name.
            return 'unsupported';
        }
    },

    /**
     * List video input devices without opening the camera. enumerateDevices()
     * needs no stream; labels are only exposed once permission has been granted.
     * `ok` distinguishes "the browser says there is no camera" from "the browser
     * would not tell us", which need different messages.
     */
    async listCameras() {
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
            return { ok: false, devices: [] };
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();

            return {
                ok: true,
                devices: devices
                    .filter((device) => device.kind === 'videoinput')
                    .map((device) => ({ id: device.deviceId || '', label: device.label || '' })),
            };
        } catch (enumErr) {
            console.warn('Camera enumeration notice:', enumErr);
            return { ok: false, devices: [] };
        }
    },

    /**
     * Ordered camera options to attempt: the operator's choice first, then real
     * hardware, then virtual cameras with no source. A facing mode follows
     * because it needs no device id, and unlabelled ids come last because they
     * are rotated by the browser as soon as permission is granted.
     */
    buildCameraCandidates() {
        const withId = this.cameras.filter((camera) => camera.id);

        const chosen = this.selectedCameraId
            ? withId.filter((camera) => camera.id === this.selectedCameraId)
            : [];
        const others = withId.filter((camera) => !chosen.includes(camera));

        const ordered = [
            ...chosen,
            ...others.filter((camera) => !this.isVirtualCamera(camera)),
            ...others.filter((camera) => this.isVirtualCamera(camera)),
        ];

        const labelsKnown = ordered.some((camera) => camera.label);
        const candidates = [];

        // Device ids are only trustworthy once permission has exposed labels.
        if (labelsKnown) {
            ordered.filter((camera) => camera.label).forEach((camera) => candidates.push(camera.id));
        }

        // A facing mode needs no device id, so it is the dependable option
        // whenever no labelled device is known yet. Both modes are offered: a
        // desktop webcam usually reports no facing mode at all and accepts
        // whichever is asked for first, so the preferred one only has to come
        // first, not be the only one tried.
        candidates.push({ facingMode: this.facingMode });
        candidates.push({ facingMode: this.facingMode === 'environment' ? 'user' : 'environment' });

        // Add any remaining camera device IDs (even without labels) as fallbacks
        // in case the browser or driver rejects facingMode constraints.
        ordered.forEach((camera) => {
            if (camera.id && !candidates.includes(camera.id)) {
                candidates.push(camera.id);
            }
        });

        return candidates;
    },

    /**
     * Virtual cameras (OBS, ManyCam, NDI, phone-as-webcam tools) open successfully
     * but usually deliver a frozen or blank picture, so real hardware is preferred
     * until the operator deliberately picks a device.
     */
    isVirtualCamera(camera) {
        return /virtual|obs|manycam|ndi|snap ?camera|droidcam|epoccam|xsplit|screen ?capture|iriun|camtwist/i
            .test(camera?.label || '');
    },

    /**
     * Whether a camera candidate is one of the virtual devices above. Only a
     * device id can be resolved to a label, so a facing-mode candidate answers
     * false - the conservative answer, since a real camera must never be doubted.
     */
    isVirtualCandidate(candidate) {
        if (typeof candidate !== 'string') return false;

        const label = this.cameras.find((camera) => camera.id === candidate)?.label || '';

        return this.isVirtualCamera({ label });
    },

    /**
     * Errors that another camera option can plausibly resolve.
     */
    isTransientCameraError(error) {
        const { name, message } = this.readErrorDetails(error);

        if (RECOVERABLE_CAMERA_ERRORS.includes(name)) return true;

        return /NotReadableError|Could not start video source|Device in use|OverconstrainedError|Constraints could not be satisfied|NotFoundError|Requested device not found|AbortError/i
            .test(message);
    },

    /**
     * Recover the DOMException name and message from whatever shape the error
     * arrived in. html5-qrcode rejects with `Error getting userMedia, error = ${error}`,
     * which is a string, so the name has to be read back out of the text.
     */
    readErrorDetails(error) {
        if (!error) return { name: '', message: '' };

        if (typeof error === 'string') {
            const text = error
                .replace(/^Error getting userMedia,\s*error\s*=\s*/i, '')
                .replace(/^QR code parse error,\s*error\s*=\s*/i, '')
                .trim();

            const matched = KNOWN_CAMERA_ERROR_NAMES.find((candidate) => new RegExp(`^${candidate}\\b`).test(text));

            if (matched) {
                return {
                    name: matched,
                    message: text.slice(matched.length).replace(/^:\s*/, '').trim() || text,
                };
            }

            return { name: '', message: text };
        }

        return { name: error.name || '', message: error.message || String(error) };
    },

    /**
     * Map a real browser failure onto an accurate explanation. Nothing here
     * guesses: an error is only described as a blocked site when the browser
     * reports a refusal, and never when the permission was merely dismissed or
     * the request failed for an unrelated reason.
     */
    describeCameraError(error) {
        const { name, message } = this.readErrorDetails(error);
        const detail = [name, message].filter(Boolean).join(': ') || null;
        const blockedByBrowser = this.permissionState === 'denied';

        if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || name === 'SecurityError') {
            // Chrome reports "Permission dismissed" when the prompt is closed
            // without a choice. The site is not blocked and asking again
            // re-prompts, so this must not become a settings lecture. Safari's
            // single generic refusal message is matched here too, because asking
            // again is also the right next step there.
            if (!blockedByBrowser && /dismiss|user gesture|cancel|\buser denied\b/i.test(message)) {
                return {
                    kind: 'permission-prompt',
                    title: 'Camera Permission Not Granted',
                    message: 'Camera access was not allowed. Press Retry Camera and choose Allow in the browser prompt to start scanning.',
                    detail,
                };
            }

            // Chrome reports "Permission denied by system" when the operating
            // system, not the browser, is holding the camera back. Matched
            // narrowly: Safari's generic refusal mentions "the platform" without
            // meaning an OS-level block at all.
            const blockedBySystem = /by system|system (policy|settings)|operating system/i.test(message);

            if (blockedBySystem || (!blockedByBrowser && this.permissionState === 'granted')) {
                return {
                    kind: 'permission-system',
                    title: 'Camera Blocked by the System',
                    message: 'This browser already allows camera access for this site, so the refusal is coming from outside it. Camera access is normally switched off for the browser in the operating system privacy settings - re-enable it there and press Retry Camera.',
                    detail,
                };
            }

            return {
                kind: 'permission',
                title: 'Camera Access Blocked',
                message: blockedByBrowser
                    ? 'Camera access for this site is currently blocked in the browser.'
                    : 'The browser refused camera access without exposing a reason to the page. Press Retry Camera to ask again.',
                detail,
            };
        }

        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return {
                kind: 'no-device',
                title: 'No Camera Found',
                message: 'No camera hardware was detected on this device. Connect a webcam and press Retry Camera, or use the fallbacks below.',
                detail,
            };
        }

        if (name === 'NotReadableError' || name === 'TrackStartError' || name === 'AbortError' || name === 'SourceUnavailableError') {
            return {
                kind: 'busy',
                title: 'Camera In Use',
                message: 'The camera could not be started because the operating system or another application is holding it - Zoom, Teams, OBS, or the Windows Camera app. Close whatever is using it and press Retry Camera.',
                detail,
            };
        }

        if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
            return {
                kind: 'constraints',
                title: 'Camera Mode Unavailable',
                message: 'This camera cannot provide the video mode the scanner asked for. Press Retry Camera to try the other cameras on this device, or use the fallbacks below.',
                detail,
            };
        }

        if (name === 'TypeError' || /not supported|not a function/i.test(message)) {
            return {
                kind: 'unsupported',
                title: 'Camera Not Supported Here',
                message: 'This browser does not expose camera access to web pages. Use Chrome, Edge, Firefox, or Safari, or use the fallbacks below.',
                detail,
            };
        }

        return {
            kind: 'unknown',
            title: 'Camera Could Not Start',
            message: message
                ? `The browser reported: ${message}`
                : 'The browser did not report a reason for the failure. Press Retry Camera, or use the fallbacks below.',
            detail,
        };
    },

    delay(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    },

    async retryCamera() {
        await this.stopScanner();
        this.clearError();
        this.notice = null;
        this.isLocked = false;
        if (!this.isOpen) this.isOpen = true;
        await this.startScanner();
    },

    /**
     * Switch to a specific device. The current stream is released first, so the
     * new device is never asked for while the old one is still open.
     */
    async selectCamera(deviceId) {
        if (!deviceId) return;

        this.selectedCameraId = deviceId;

        await this.stopScanner();
        this.clearError();
        this.notice = null;
        this.isLocked = false;

        if (this.isOpen) await this.startScanner();
    },

    async onFileSelected(event) {
        const input = event?.target;
        const file = input?.files?.[0];
        if (!file) return;

        this.clearError();
        this.notice = null;

        // accept="image/*" is a hint the file picker may ignore, so the real type
        // is checked before handing the file to the decoder.
        if (!file.type || !file.type.startsWith('image/')) {
            playScanAudio(false);
            this.applyError({
                kind: 'file',
                title: 'Unsupported File',
                message: 'Choose an image file (PNG, JPG, WEBP, or a screenshot) that contains a barcode.',
            });
            if (input) input.value = '';
            return;
        }

        this.isProcessingFile = true;

        try {
            if (this.isScanning) {
                await this.stopScanner();
            }

            // A fresh driver on an emptied container: a previous image scan leaves
            // its own elements behind, and reusing that instance would run the
            // decode against stale DOM.
            await this.disposeScanner();
            this.scanner = this.createScanner();

            const decodedText = await this.scanner.scanFile(file, false);

            if (!decodedText) {
                throw new Error('No barcode could be read from the image.');
            }

            // Image and camera input share one processing path from here.
            await this.onScanSuccess(decodedText, 'file');
        } catch (err) {
            console.warn('Image barcode scan failed:', err);
            playScanAudio(false);
            this.applyError({
                kind: 'file',
                title: 'Barcode Not Detected',
                message: 'No 1D or 2D barcode could be read from that image. Try a sharper, closer, well-lit picture, or type the identifier below.',
            });
        } finally {
            this.isProcessingFile = false;
            if (input) input.value = '';
        }
    },

    /**
     * Build an html5-qrcode driver configured for all standard enterprise barcode formats.
     */
    createScanner() {
        return new Html5Qrcode(containerId, {
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.DATA_MATRIX,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.CODE_93,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.AZTEC,
                Html5QrcodeSupportedFormats.PDF_417,
            ],
            verbose: false,
        });
    },

    /**
     * Release the html5-qrcode driver, the camera tracks it holds, and any DOM it
     * left behind, without touching the component's display state.
     */
    async disposeScanner() {
        const scanner = this.scanner;
        this.scanner = null;

        // Stop the tracks the browser handed us first. This is the step that
        // actually frees the device, and it must not depend on html5-qrcode's
        // internal state machine cooperating.
        this.stopContainerStreams();

        if (scanner) {
            try {
                if (typeof scanner.getState === 'function'
                    && scanner.getState() !== Html5QrcodeScannerState.NOT_STARTED) {
                    await scanner.stop();
                }
            } catch (err) {
                console.warn('Scanner stop notice:', err);
            }

            try {
                scanner.clear();
            } catch (_) {}
        }

        // A start attempt that failed part way through leaves its video surface in
        // the container. That leftover holds a live stream open, which is what
        // makes the next attempt fail with "camera in use".
        this.emptyContainer();
    },

    emptyContainer() {
        try {
            const containerEl = document.getElementById(containerId);
            if (containerEl) containerEl.innerHTML = '';
        } catch (_) {}
    },

    stopContainerStreams() {
        try {
            const containerEl = document.getElementById(containerId);
            if (!containerEl) return;

            containerEl.querySelectorAll('video').forEach((video) => {
                const stream = video.srcObject;
                if (stream && typeof stream.getTracks === 'function') {
                    stream.getTracks().forEach((track) => track.stop());
                    video.srcObject = null;
                }
            });
        } catch (_) {}
    },

    async stopScanner() {
        this.isStarting = false;
        this.isScanning = false;
        this.runId++;
        await this.disposeScanner();
    },

    /**
     * Synchronous teardown for unload, where no await can complete. The device is
     * released directly and the in-flight start is cancelled.
     */
    releaseCamera() {
        this.isStarting = false;
        this.isScanning = false;
        this.runId++;

        const scanner = this.scanner;
        this.scanner = null;

        this.stopContainerStreams();

        if (scanner) {
            try {
                const stopped = scanner.stop();
                if (stopped && typeof stopped.catch === 'function') stopped.catch(() => {});
            } catch (_) {}
        }
    },

    /**
     * Confirm the running scanner is receiving a changing picture. A virtual camera
     * with no active source - or a driver that never delivers frames - starts
     * cleanly and reports a healthy live track while showing a frozen image, which
     * looks exactly like a working scanner except that nothing ever decodes.
     * Returns false only when frames are provably static, and null when the feed
     * cannot be judged.
     */
    async isFeedLive() {
        const video = document.querySelector(`#${containerId} video`);
        if (!video) return null;

        // Wait for the first frame to arrive; without dimensions nothing can be judged.
        for (let i = 0; i < 10 && !video.videoWidth; i++) {
            await this.delay(100);
        }
        if (!video.videoWidth) return null;

        const canvas = document.createElement('canvas');
        canvas.width = 32;
        canvas.height = 24;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });

        const sample = () => {
            try {
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                let sum = 0;
                for (let i = 0; i < pixels.length; i += 4) {
                    sum += pixels[i] + pixels[i + 1] + pixels[i + 2];
                }
                return sum;
            } catch (_) {
                return null;
            }
        };

        let previous = sample();
        for (let i = 0; i < 3; i++) {
            await this.delay(400);
            const current = sample();

            // A blank frame proves nothing: a covered lens looks identical to a dead feed.
            if (previous === null || current === null || previous === 0) return null;

            // Sensor noise alone is enough to differ, so any change means real frames.
            if (current !== previous) return true;
            previous = current;
        }

        return false;
    },

    /**
     * One decoded value, processed once, whichever input produced it. Camera
     * frames repeat the same label across consecutive frames, so the lock is
     * taken synchronously before any await - otherwise a second frame slips
     * through and the scan is submitted twice.
     */
    async onScanSuccess(decodedText, source = 'camera') {
        if (!decodedText) return;

        if (source === 'camera') {
            if (this.isLocked || this.lastScannedCode === decodedText) return;
            this.isLocked = true;
            this.lastScannedCode = decodedText;
        }

        let finalCode = String(decodedText).trim();

        // Validate format if required (e.g. GS1 SSCC)
        if (validateFormat === 'sscc') {
            const ssccResult = validateGs1Sscc(finalCode);
            if (!ssccResult.valid) {
                playScanAudio(false);
                this.isLocked = false;
                this.applyError({
                    kind: 'scan',
                    title: 'Code Not Accepted',
                    message: `That code is not a valid GS1 SSCC. ${ssccResult.message}`,
                });
                return;
            }
            finalCode = ssccResult.code;
        }

        playScanAudio(true);

        // Release the camera as soon as the value is captured; everything after
        // this works from the decoded string.
        if (source === 'camera') {
            await this.stopScanner();
        }

        // Dispatch global custom event with scan details
        window.dispatchEvent(new CustomEvent(eventName, {
            detail: {
                code: finalCode,
                targetInputId,
                rawCode: decodedText,
            },
            bubbles: true,
        }));

        // Populate target input element if specified
        if (targetInputId) {
            const input = document.getElementById(targetInputId);
            if (input) {
                input.value = finalCode;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        if (autoClose) {
            await this.close();
        }

        if (autoSubmit && targetInputId) {
            const form = document.getElementById(targetInputId)?.closest('form');
            if (form) {
                setTimeout(() => {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }, 200);
            }
        }
    },

    /**
     * Manual entry stays a fallback, but it goes through the same validation and
     * the same lookup path as a scanned code - the server remains the authority
     * on whether the identifier exists and whether the operator may use it.
     */
    async applyManualCode() {
        const value = String(this.manualCode || '').trim();

        if (!value) {
            this.applyError({
                kind: 'scan',
                title: 'Identifier Required',
                message: 'Type or paste a barcode, SKU, or location code before applying.',
            });
            return;
        }

        this.clearError();

        let finalCode = value;

        if (validateFormat === 'sscc') {
            const ssccResult = validateGs1Sscc(value);
            if (!ssccResult.valid) {
                playScanAudio(false);
                this.applyError({
                    kind: 'scan',
                    title: 'Invalid GS1 SSCC',
                    message: ssccResult.message,
                });
                return;
            }
            finalCode = ssccResult.code;
        }

        playScanAudio(true);

        if (targetInputId) {
            const input = document.getElementById(targetInputId);
            if (input) {
                input.value = finalCode;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        window.dispatchEvent(new CustomEvent(eventName, {
            detail: {
                code: finalCode,
                targetInputId,
                rawCode: value,
            },
            bubbles: true,
        }));

        await this.close();

        if (autoSubmit && targetInputId) {
            const form = document.getElementById(targetInputId)?.closest('form');
            if (form) {
                setTimeout(() => {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }, 200);
            }
        }
    },
});
