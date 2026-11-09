import { letterEditor } from './letter-editor';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import 'flatpickr/dist/l10n/id.js';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { schoolLocationPicker } from './school-location-picker';

window.flatpickr = flatpickr;

import $ from 'jquery';

window.$ = $;
window.jQuery = $;

import select2 from 'select2';

select2($);

import 'select2/dist/css/select2.min.css';

import { BrowserQRCodeReader } from '@zxing/browser';

window.BrowserQRCodeReader = BrowserQRCodeReader;

document.addEventListener('alpine:init', () => {
    Alpine.data('letterEditor', letterEditor);
    Alpine.data('schoolLocationPicker', () => schoolLocationPicker(L));
    Alpine.store('studentScanSound', {
        context: null,

        prepare() {
            try {
                const AudioContext = globalThis.AudioContext || globalThis.webkitAudioContext;
                if (!AudioContext) return;
                this.context ??= new AudioContext();
                if (this.context.state === 'suspended') this.context.resume().catch(() => {});
            } catch (_) {
                // Visual feedback remains available when browser audio is blocked.
            }
        },

        play() {
            try {
                if (this.context?.state !== 'running') return;
                const oscillator = this.context.createOscillator();
                const gain = this.context.createGain();
                const now = this.context.currentTime;
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, now);
                gain.gain.setValueAtTime(0.08, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);
                oscillator.connect(gain);
                gain.connect(this.context.destination);
                oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
                oscillator.start(now);
                oscillator.stop(now + 0.12);
            } catch (_) {
                // Sound must never interrupt the next scan.
            }
        },
    });

    Alpine.data('attendanceSaveFeedback', () => ({
        toast: null,
        timer: null,

        notify(detail) {
            this.dismiss();
            this.toast = { type: detail.type, message: detail.message };
            this.timer = setTimeout(() => this.dismiss(), detail.type === 'success' ? 3000 : 8000);
        },

        dismiss() {
            clearTimeout(this.timer);
            this.timer = null;
            this.toast = null;
        },

        destroy() {
            this.dismiss();
        },
    }));

    Alpine.data('studentQrScanner', () => ({
        reader: null,
        controls: null,

        scanning: false,
        processing: false,
        lastCode: null,
        lastScanTime: 0,
        cameraError: '',
        runId: 0,
        toast: null,
        toastTimer: null,

        notify(type, message) {
            clearTimeout(this.toastTimer);
            this.toast = { type, message };
            this.toastTimer = setTimeout(() => {
                this.toast = null;
                this.toastTimer = null;
            }, type === 'success' ? 3000 : 5000);
        },

        async start() {
            this.stop();
            const runId = this.runId;
            const sessionId = this.$wire.scheduleId;
            const attendanceDate = this.$wire.attendanceDate;
            const video = this.$refs.video;

            this.cameraError = '';

            if (!navigator.mediaDevices?.getUserMedia) {
                this.cameraError = 'Kamera tidak tersedia. Buka aplikasi melalui HTTPS dan gunakan browser yang mendukung kamera, atau isi presensi manual.';
                return;
            }

            if (!video) {
                console.error('Video element tidak ditemukan.');
                return;
            }

            try {
                this.reader = new BrowserQRCodeReader();

                const controls = await this.reader.decodeFromConstraints(
                    {
                        video: {
                            facingMode: { ideal: 'environment' }
                        },
                        audio: false,
                    },
                    video,
                    async (result, error) => {
                        if (!result || this.processing || runId !== this.runId) {
                            return;
                        }

                        const studentCode = result.getText().trim();

                        const now = Date.now();

                        // Jangan proses QR code yang sama terlalu cepat
                        if (
                            studentCode === this.lastCode &&
                            now - this.lastScanTime < 2000
                        ) {
                            return;
                        }

                        this.processing = true;
                        this.lastCode = studentCode;
                        this.lastScanTime = now;

                        try {
                            const response = await this.$wire.scanStudent(studentCode, sessionId, attendanceDate);
                            if (runId !== this.runId) return;
                            this.cameraError = '';
                            this.notify(response?.type || (response?.success ? 'success' : 'error'), response?.message || 'Hasil scan tidak tersedia. Silakan pindai kembali.');
                            if (response?.success) {
                                this.$store.studentScanSound.play();
                                try { navigator.vibrate?.(100); } catch (_) {}
                            }
                        } catch (error) {
                            if (runId !== this.runId) return;
                            this.cameraError = 'Hasil scan belum dapat disimpan. Periksa koneksi lalu pindai kembali, atau isi presensi manual.';
                            this.notify('error', this.cameraError);
                            this.lastCode = null;
                        } finally {
                            if (runId === this.runId) this.processing = false;
                        }
                    }
                );

                if (runId !== this.runId) {
                    controls.stop();
                    return;
                }
                this.controls = controls;
                this.scanning = true;

            } catch (error) {
                if (runId !== this.runId) return;
                this.stop();
                this.cameraError = ['NotAllowedError', 'PermissionDeniedError'].includes(error.name)
                    ? 'Akses kamera ditolak. Izinkan kamera di pengaturan browser, lalu buka kembali scanner. Presensi manual tetap bisa digunakan.'
                    : 'Kamera tidak dapat dibuka. Pastikan kamera tersedia dan tidak digunakan aplikasi lain, atau isi presensi manual.';
            }
        },

        stop() {
            this.runId++;
            clearTimeout(this.toastTimer);
            this.toastTimer = null;
            this.toast = null;
            if (this.controls) {
                this.controls.stop();
                this.controls = null;
            }

            const video = this.$refs.video;
            video?.srcObject?.getTracks().forEach(track => track.stop());
            if (video) video.srcObject = null;
            this.reader = null;

            this.scanning = false;
            this.processing = false;
        },

        destroy() {
            this.stop();
        }
    }));
});

