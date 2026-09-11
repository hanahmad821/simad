import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import 'flatpickr/dist/l10n/id.js';

window.flatpickr = flatpickr;

import { BrowserMultiFormatReader } from '@zxing/browser';

window.BrowserMultiFormatReader = BrowserMultiFormatReader;

document.addEventListener('alpine:init', () => {
    Alpine.data('studentBarcodeScanner', () => ({
        reader: null,
        controls: null,

        scanning: false,
        processing: false,
        lastCode: null,
        lastScanTime: 0,

        async start() {
            const video = this.$refs.video;

            if (!video) {
                console.error('Video element tidak ditemukan.');
                return;
            }

            try {
                this.reader = new BrowserMultiFormatReader();

                this.controls = await this.reader.decodeFromConstraints(
                    {
                        video: {
                            facingMode: { ideal: 'environment' }
                        },
                        audio: false,
                    },
                    video,
                    async (result, error) => {
                        if (!result || this.processing) {
                            return;
                        }

                        const studentCode = result.getText().trim();

                        const now = Date.now();

                        // Jangan proses barcode yang sama terlalu cepat
                        if (
                            studentCode === this.lastCode &&
                            now - this.lastScanTime < 2000
                        ) {
                            return;
                        }

                        this.processing = true;
                        this.lastCode = studentCode;
                        this.lastScanTime = now;

                        console.log('Barcode:', studentCode);

                        try {
                            await this.$wire.scanStudent(studentCode);
                        } catch (error) {
                            console.error(
                                'Gagal memproses barcode:',
                                error
                            );
                        }

                        // Beri waktu kamera sebelum scan berikutnya
                        setTimeout(() => {
                            this.processing = false;
                        }, 1000);
                    }
                );

                this.scanning = true;

            } catch (error) {
                console.error('Gagal menjalankan scanner:', error);
            }
        },

        stop() {
            if (this.controls) {
                this.controls.stop();
                this.controls = null;
            }

            if (this.reader) {
                this.reader.reset();
                this.reader = null;
            }

            this.scanning = false;
            this.processing = false;
        }
    }));
});

