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
 * Alpine component for camera-based barcode and QR code scanning.
 */
export const himsCameraScanner = ({
    containerId,
    targetInputId = null,
    autoSubmit = false,
    autoClose = true,
    eventName = 'hims-code-scanned',
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

    init() {
        // Ensure camera is stopped when leaving page or navigating away
        window.addEventListener('pagehide', () => this.stopScanner());
        window.addEventListener('beforeunload', () => this.stopScanner());
    },

    async open() {
        this.isOpen = true;
        this.errorMessage = null;
        this.lastScannedCode = null;

        this.$nextTick(async () => {
            await this.startScanner();
        });
    },

    async close() {
        await this.stopScanner();
        this.isOpen = false;
        this.errorMessage = null;
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

        // Check for secure context and mediaDevices availability
        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            this.errorMessage = 'Camera scanning requires a secure connection (HTTPS) or localhost.';
            this.isStarting = false;
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            this.errorMessage = 'Camera access is not supported by your browser or device.';
            this.hasCamera = false;
            this.isStarting = false;
            return;
        }

        try {
            // Instantiate scanner with comprehensive barcode/QR/DataMatrix format support
            if (!this.scanner) {
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
            }

            // Enumerate available cameras if not yet loaded
            try {
                this.cameras = await Html5Qrcode.getCameras();
            } catch {
                this.cameras = [];
            }

            const scanConfig = {
                fps: 12,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minDim = Math.min(viewfinderWidth, viewfinderHeight);
                    const width = Math.floor(minDim * 0.75);
                    const height = Math.floor(minDim * 0.55); // Rectangular aspect fits standard 1D/GS1 barcodes better
                    return { width: Math.max(width, 220), height: Math.max(height, 160) };
                },
                aspectRatio: 1.333333,
            };

            const cameraChoice = this.cameras.length > 0 && this.cameras[this.selectedCameraIndex]
                ? { deviceId: { exact: this.cameras[this.selectedCameraIndex].id } }
                : { facingMode: this.facingMode };

            await this.scanner.start(
                cameraChoice,
                scanConfig,
                (decodedText, decodedResult) => this.onScanSuccess(decodedText, decodedResult),
                () => {
                    // Frame scan failed or no code in view; intentionally ignored during continuous scanning
                }
            );

            this.isScanning = true;
            this.hasCamera = true;
        } catch (error) {
            console.error('Camera initialization failed:', error);
            this.hasCamera = false;

            const name = error?.name || '';
            const msg = error?.message || '';

            if (name === 'NotAllowedError' || msg.includes('Permission denied')) {
                this.errorMessage = 'Camera access denied. Please allow camera permissions in your browser settings to scan.';
            } else if (name === 'NotFoundError' || msg.includes('Requested device not found')) {
                this.errorMessage = 'No camera found on this device. You can still enter the code manually.';
            } else if (name === 'NotReadableError' || msg.includes('Could not start video source')) {
                this.errorMessage = 'Camera is already in use by another application or tab. Please close other camera apps and retry.';
            } else if (name === 'OverconstrainedError') {
                // Fallback to default constraints
                this.errorMessage = 'Requested camera resolution not supported. Trying default camera...';
                setTimeout(() => {
                    this.facingMode = 'user';
                    this.startScanner();
                }, 500);
            } else {
                this.errorMessage = `Could not start camera scanner: ${msg || 'Unknown hardware error'}. Manual entry is available below.`;
            }
        } finally {
            this.isStarting = false;
        }
    },

    async stopScanner() {
        if (this.scanner && this.isScanning) {
            try {
                await this.scanner.stop();
            } catch (err) {
                console.warn('Error stopping scanner:', err);
            }
        }
        this.isScanning = false;
        this.isStarting = false;
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
        // Prevent duplicate firing for same code in rapid succession
        if (this.lastScannedCode === decodedText) return;
        this.lastScannedCode = decodedText;

        // Play high success beep
        playScanAudio(true);

        // Dispatch global custom event with scan details
        window.dispatchEvent(new CustomEvent(eventName, {
            detail: {
                code: decodedText,
                targetInputId,
            },
            bubbles: true,
        }));

        // Populate target input element if specified
        if (targetInputId) {
            const input = document.getElementById(targetInputId);
            if (input) {
                input.value = decodedText;
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
                // Small delay to allow UI feedback and modal dismissal
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
