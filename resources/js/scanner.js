import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

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
 * Alpine component for camera-based barcode and QR code scanning.
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
    scanner: null,
    errorMessage: null,
    hasCamera: true,
    cameras: [],
    selectedCameraIndex: 0,
    lastScannedCode: null,
    facingMode: 'environment', // Prioritize rear warehouse camera
    manualCode: '',

    init() {
        // Ensure camera is stopped when navigating away or leaving page
        window.addEventListener('pagehide', () => this.stopScanner());
        window.addEventListener('beforeunload', () => this.stopScanner());
    },

    async open() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.errorMessage = null;
        this.lastScannedCode = null;
        this.manualCode = '';

        // Allow Alpine's modal animation to mount container into the DOM and calculate viewport dimensions
        this.$nextTick(() => {
            setTimeout(async () => {
                if (this.isOpen && !this.isScanning && !this.isStarting) {
                    await this.startScanner();
                }
            }, 60);
        });
    },

    async close() {
        this.isOpen = false;
        await this.stopScanner();
        this.errorMessage = null;
        this.manualCode = '';
    },

    async startScanner() {
        if (this.isScanning || this.isStarting) return;
        this.isStarting = true;
        this.errorMessage = null;

        const containerEl = document.getElementById(containerId);
        if (!containerEl) {
            this.errorMessage = 'Scanner viewport container not found in DOM.';
            this.isStarting = false;
            return;
        }

        // Check for secure context (HTTPS or localhost)
        const isLocal = window.location.hostname === 'localhost' ||
                        window.location.hostname === '127.0.0.1' ||
                        window.location.hostname === '[::1]';
        if (!window.isSecureContext && !isLocal) {
            this.errorMessage = 'Camera access requires a secure connection (HTTPS). Please access HIMS over HTTPS or localhost.';
            this.hasCamera = false;
            this.isStarting = false;
            return;
        }

        // Check MediaDevices API
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.errorMessage = 'Camera access is not supported by this browser. Please enter the code manually below.';
            this.hasCamera = false;
            this.isStarting = false;
            return;
        }

        try {
            // Clean up any stale scanner instance
            if (this.scanner) {
                try {
                    if (this.scanner.isScanning) {
                        await this.scanner.stop();
                    }
                    this.scanner.clear();
                } catch (_) {}
                this.scanner = null;
            }

            this.releaseActiveMediaTracks();

            // Query available camera devices
            if (this.cameras.length === 0) {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    if (Array.isArray(devices) && devices.length > 0) {
                        this.cameras = devices;
                    }
                } catch (enumErr) {
                    console.warn('Initial camera enumeration notice:', enumErr);
                }
            }

            // Instantiate Html5Qrcode with all standard enterprise barcodes and QR formats
            this.scanner = new Html5Qrcode(containerId, {
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

            // Safe dynamic qrbox sizing that strictly stays within actual viewfinder dimensions
            const scanConfig = {
                fps: 15,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minDim = Math.min(viewfinderWidth, viewfinderHeight);
                    const width = Math.min(Math.floor(viewfinderWidth * 0.8), Math.floor(minDim * 0.85));
                    const height = Math.min(Math.floor(viewfinderHeight * 0.6), Math.floor(minDim * 0.55));
                    return {
                        width: Math.max(50, Math.min(width, viewfinderWidth - 10)),
                        height: Math.max(50, Math.min(height, viewfinderHeight - 10)),
                    };
                },
                aspectRatio: 1.333333,
            };

            // Choose camera device: prefer explicit deviceId if enumerated, else use facingMode
            let cameraChoice;
            if (this.cameras.length > 0) {
                if (this.selectedCameraIndex >= this.cameras.length) {
                    this.selectedCameraIndex = 0;
                }
                cameraChoice = this.cameras[this.selectedCameraIndex].id;
            } else {
                cameraChoice = { facingMode: this.facingMode };
            }

            try {
                await this.scanner.start(
                    cameraChoice,
                    scanConfig,
                    (decodedText, decodedResult) => this.onScanSuccess(decodedText, decodedResult),
                    () => {
                        // Scan loop tick; no code detected in current frame
                    }
                );
            } catch (startErr) {
                // If environment camera failed (common on desktop/laptops with only a user-facing webcam),
                // automatically fallback to 'user' facing mode
                const errText = String(startErr || '');
                if (
                    (errText.includes('OverconstrainedError') || errText.includes('NotFoundError') || errText.includes('Requested device not found')) &&
                    typeof cameraChoice === 'object' &&
                    cameraChoice.facingMode === 'environment'
                ) {
                    console.info('Falling back to user webcam...');
                    this.facingMode = 'user';
                    await this.scanner.start(
                        { facingMode: 'user' },
                        scanConfig,
                        (decodedText, decodedResult) => this.onScanSuccess(decodedText, decodedResult),
                        () => {}
                    );
                } else {
                    throw startErr;
                }
            }

            this.isScanning = true;
            this.hasCamera = true;
            this.errorMessage = null;

            // Refresh enumerated cameras now that camera permission has definitely been granted
            if (this.cameras.length === 0) {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    if (Array.isArray(devices) && devices.length > 0) {
                        this.cameras = devices;
                    }
                } catch (_) {}
            }
        } catch (error) {
            console.error('Camera initialization failed:', error);
            this.handleCameraError(error);
        } finally {
            this.isStarting = false;
        }
    },

    handleCameraError(error) {
        this.hasCamera = false;
        this.isScanning = false;

        this.stopScanner();

        const errStr = typeof error === 'string'
            ? error
            : (error?.message ? `${error.name || ''}: ${error.message}` : String(error || ''));
        const errName = error?.name || '';

        if (
            errName === 'NotAllowedError' ||
            errName === 'PermissionDeniedError' ||
            errStr.includes('NotAllowedError') ||
            errStr.includes('Permission denied') ||
            errStr.includes('Permission dismissed') ||
            errStr.includes('denied')
        ) {
            this.errorMessage = 'Camera access was denied. Please click the camera or lock icon in your browser address bar, allow camera permissions, and reopen the scanner.';
        } else if (
            errName === 'NotFoundError' ||
            errName === 'DevicesNotFoundError' ||
            errStr.includes('NotFoundError') ||
            errStr.includes('Requested device not found') ||
            errStr.includes('no camera') ||
            errStr.includes('No Cameras')
        ) {
            this.errorMessage = 'No camera hardware detected on this device. Please connect a webcam or use the manual entry field below.';
        } else if (
            errName === 'NotReadableError' ||
            errName === 'TrackStartError' ||
            errStr.includes('NotReadableError') ||
            errStr.includes('Could not start video source') ||
            errStr.includes('Device in use')
        ) {
            this.errorMessage = 'Camera is currently in use by another application or tab (e.g. Zoom, Teams, or another window). Please close other camera apps and retry.';
        } else if (
            errName === 'OverconstrainedError' ||
            errStr.includes('OverconstrainedError') ||
            errStr.includes('Constraints could not be satisfied')
        ) {
            this.errorMessage = 'The requested camera configuration or resolution is not supported by your hardware. Manual entry is available below.';
        } else if (errStr.includes('secure context') || errStr.includes('https')) {
            this.errorMessage = 'Camera scanning requires a secure connection (HTTPS). Please access HIMS over HTTPS or localhost.';
        } else if (errStr.includes('not supported') || errStr.includes('streaming not supported')) {
            this.errorMessage = 'Camera scanning is not supported by this browser. Please use Chrome, Edge, or Firefox, or enter the code manually.';
        } else {
            const cleanMsg = errStr
                .replace(/^Error getting userMedia,\s*error\s*=\s*/i, '')
                .replace(/^QR code parse error,\s*error\s*=\s*/i, '')
                .trim();
            this.errorMessage = cleanMsg
                ? `Could not start camera scanner: ${cleanMsg}. Manual entry is available below.`
                : 'Could not initialize camera hardware. Please check your camera connection or use manual entry below.';
        }
    },

    async stopScanner() {
        this.isScanning = false;
        this.isStarting = false;

        if (this.scanner) {
            try {
                if (this.scanner.isScanning) {
                    await this.scanner.stop();
                }
            } catch (err) {
                console.warn('Error stopping html5-qrcode:', err);
            }

            try {
                this.scanner.clear();
            } catch (_) {}

            this.scanner = null;
        }

        this.releaseActiveMediaTracks();
    },

    releaseActiveMediaTracks() {
        try {
            const containerEl = document.getElementById(containerId);
            if (containerEl) {
                const videos = containerEl.querySelectorAll('video');
                videos.forEach((video) => {
                    if (video.srcObject && typeof video.srcObject.getTracks === 'function') {
                        video.srcObject.getTracks().forEach((track) => track.stop());
                        video.srcObject = null;
                    }
                });
            }
        } catch (_) {}
    },

    async switchCamera() {
        if (this.cameras.length > 1) {
            this.selectedCameraIndex = (this.selectedCameraIndex + 1) % this.cameras.length;
        } else {
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
        }

        await this.stopScanner();
        await this.startScanner();
    },

    async onScanSuccess(decodedText) {
        if (!decodedText || this.lastScannedCode === decodedText) return;
        this.lastScannedCode = decodedText;

        let finalCode = decodedText.trim();

        // Validate format if required (e.g. GS1 SSCC)
        if (validateFormat === 'sscc') {
            const ssccResult = validateGs1Sscc(finalCode);
            if (!ssccResult.valid) {
                playScanAudio(false);
                this.errorMessage = `Scanned code is not a valid GS1 SSCC: ${ssccResult.message}`;
                return;
            }
            finalCode = ssccResult.code;
        }

        playScanAudio(true);

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

    applyManualCode() {
        const val = String(this.manualCode || '').trim();
        if (!val) {
            this.errorMessage = 'Please enter a code before applying.';
            return;
        }

        let finalCode = val;
        if (validateFormat === 'sscc') {
            const ssccResult = validateGs1Sscc(val);
            if (!ssccResult.valid) {
                playScanAudio(false);
                this.errorMessage = `Invalid GS1 SSCC: ${ssccResult.message}`;
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
                rawCode: val,
            },
            bubbles: true,
        }));

        this.close();

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

