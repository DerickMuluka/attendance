// QR Code functionality using html5-qrcode library
class QRCodeScanner {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            onResult: options.onResult || function() {},
            onError: options.onError || function() {},
            preferredCamera: 'environment',
            aspectRatio: 1.0
        };
        
        this.scanner = null;
        this.isScanning = false;
    }
    
    async start() {
        if (!this.container) {
            throw new Error('Container element not found');
        }
        
        try {
            // Load html5-qrcode library if not already loaded
            if (typeof Html5QrcodeScanner === 'undefined') {
                await this.loadQRCodeLibrary();
            }
            
            this.scanner = new Html5QrcodeScanner(
                this.container.id,
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: this.options.aspectRatio,
                    supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_QR_CODE]
                },
                false
            );
            
            this.scanner.render(
                (decodedText) => {
                    this.options.onResult(decodedText);
                },
                (errorMessage) => {
                    this.options.onError(errorMessage);
                }
            );
            
            this.isScanning = true;
            
        } catch (error) {
            console.error('QR Scanner initialization failed:', error);
            this.options.onError('Failed to initialize QR scanner: ' + error.message);
        }
    }
    
    stop() {
        if (this.scanner) {
            this.scanner.clear();
            this.scanner = null;
        }
        this.isScanning = false;
    }
    
    async loadQRCodeLibrary() {
        return new Promise((resolve, reject) => {
            if (typeof Html5QrcodeScanner !== 'undefined') {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    switchCamera() {
        if (!this.scanner) return;
        
        // Html5QrcodeScanner doesn't have a direct switch camera method
        // We need to stop and restart with the other camera
        this.stop();
        
        this.options.preferredCamera = this.options.preferredCamera === 'environment' ? 'user' : 'environment';
        this.start();
    }
    
    generateQRCode(text, elementId, options = {}) {
        const defaultOptions = {
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            // Clear previous QR code
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = '';
            }
            
            new QRCode(elementId, {
                text: text,
                width: finalOptions.width,
                height: finalOptions.height,
                colorDark: finalOptions.colorDark,
                colorLight: finalOptions.colorLight,
                correctLevel: finalOptions.correctLevel
            });
            
        } catch (error) {
            console.error('QR Code generation failed:', error);
        }
    }
}

// Initialize QR code scanner
window.qrCodeScanner = new QRCodeScanner();