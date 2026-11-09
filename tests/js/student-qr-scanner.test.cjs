const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function scannerFixture(decode, audioAvailable = true) {
    let factory;
    let callback;
    let stopped = 0;
    let trackStopped = 0;
    let calls = 0;
    let sounds = 0;
    let vibrations = 0;
    let nextTimer = 0;
    const timers = new Map();
    const stores = {};
    const factories = {};
    const controls = { stop() { stopped++; } };
    const context = {
        Alpine: { data(name, value) { factory = value; factories[name] = value; }, store(name, value) { stores[name] = value; } },
        document: { addEventListener(name, handler) { handler(); } },
        navigator: { mediaDevices: { getUserMedia() {} }, vibrate() { vibrations++; } },
        setTimeout(callback, duration) { const id = ++nextTimer; timers.set(id, { callback, duration }); return id; },
        clearTimeout(id) { timers.delete(id); },
        console,
        BrowserQRCodeReader: class {
            async decodeFromConstraints(options, video, handler) {
                callback = handler;
                if (decode) return decode(controls);
                video.srcObject = { getTracks: () => [{ stop() { trackStopped++; } }] };
                return controls;
            }
        },
    };
    if (audioAvailable) context.AudioContext = class {
        state = 'suspended';
        currentTime = 0;
        destination = {};
        async resume() { this.state = 'running'; }
        createOscillator() { return { frequency: { setValueAtTime() {} }, connect() {}, disconnect() {}, start() { sounds++; }, stop() { this.onended(); } }; }
        createGain() { return { gain: { setValueAtTime() {}, exponentialRampToValueAtTime() {} }, connect() {}, disconnect() {} }; }
    };
    const source = fs.readFileSync('resources/js/app.js', 'utf8');
    vm.runInNewContext(source.slice(source.indexOf("document.addEventListener('alpine:init'")), context);
    const scanner = factory();
    scanner.$store = stores;
    scanner.$refs = { video: { srcObject: null } };
    scanner.$wire = { scheduleId: 12, attendanceDate: '2026-10-26', async scanStudent(code, session, date) {
        calls++;
        assert.equal(code, 'S-001');
        assert.equal(session, 12);
        assert.equal(date, '2026-10-26');
        return { success: true, type: 'success', message: 'Siswa Satu — Hadir, berhasil disimpan.' };
    } };
    return { scanner, read: () => callback({ getText: () => 'S-001' }), calls: () => calls,
        stopped: () => stopped, trackStopped: () => trackStopped,
        sound: stores.studentScanSound, sounds: () => sounds, vibrations: () => vibrations, timers,
        feedback: factories.attendanceSaveFeedback() };
}

test('reads cards with a session snapshot and suppresses repeated frames', async () => {
    const fixture = scannerFixture();
    await fixture.scanner.start();
    assert.equal(fixture.scanner.scanning, true);
    await fixture.read();
    await fixture.read();
    assert.equal(fixture.calls(), 1);
    fixture.scanner.destroy();
    assert.equal(fixture.stopped(), 1);
    assert.equal(fixture.trackStopped(), 1);
    assert.equal(fixture.scanner.$refs.video.srcObject, null);
    await fixture.read();
    assert.equal(fixture.calls(), 1);
});

test('shows save errors for eight seconds and replaces them with successful save feedback', () => {
    const fixture = scannerFixture();
    fixture.feedback.notify({ type: 'error', message: 'Masih ada siswa yang belum dipresensi.' });
    assert.match(fixture.feedback.toast.message, /belum dipresensi/);
    assert.equal([...fixture.timers.values()][0].duration, 8000);
    fixture.feedback.notify({ type: 'success', message: 'Presensi berhasil disimpan.' });
    assert.equal(fixture.timers.size, 1);
    assert.equal(fixture.feedback.toast.type, 'success');
    assert.equal([...fixture.timers.values()][0].duration, 3000);
    fixture.feedback.dismiss();
    assert.equal(fixture.feedback.toast, null);
    assert.equal(fixture.timers.size, 0);
});

test('closes a camera that finishes starting after its component was removed', async () => {
    let finish;
    const fixture = scannerFixture(controls => new Promise(resolve => { finish = () => resolve(controls); }));
    const starting = fixture.scanner.start();
    fixture.scanner.destroy();
    finish();
    await starting;
    assert.equal(fixture.stopped(), 1);
    assert.equal(fixture.scanner.scanning, false);
});

test('shows a useful camera permission error', async () => {
    const fixture = scannerFixture(() => { throw Object.assign(new Error('denied'), { name: 'NotAllowedError' }); });
    await fixture.scanner.start();
    assert.match(fixture.scanner.cameraError, /Akses kamera ditolak/);
    assert.equal(fixture.scanner.scanning, false);
});

test('allows retrying the same card after a failed save', async () => {
    const fixture = scannerFixture();
    let attempts = 0;
    fixture.scanner.$wire.scanStudent = async () => {
        if (++attempts === 1) throw new Error('offline');
        return { success: true, type: 'success', message: 'Siswa Satu — Hadir, berhasil disimpan.' };
    };
    await fixture.scanner.start();
    await fixture.read();
    assert.match(fixture.scanner.cameraError, /belum dapat disimpan/);
    await fixture.read();
    assert.equal(attempts, 2);
    assert.equal(fixture.scanner.cameraError, '');
});

test('shows a three second success notification with sound and vibration after saving', async () => {
    const fixture = scannerFixture();
    fixture.sound.prepare();
    await fixture.scanner.start();
    await fixture.read();
    assert.equal(fixture.scanner.toast.type, 'success');
    assert.match(fixture.scanner.toast.message, /Siswa Satu/);
    assert.equal(fixture.sounds(), 1);
    assert.equal(fixture.vibrations(), 1);
    assert.equal(fixture.scanner.scanning, true);
    const timer = [...fixture.timers.values()][0];
    assert.equal(timer.duration, 3000);
    timer.callback();
    assert.equal(fixture.scanner.toast, null);
    assert.equal(fixture.scanner.scanning, true);
});

test('shows duplicate and rejected scans without playing a success signal', async () => {
    const fixture = scannerFixture();
    fixture.sound.prepare();
    await fixture.scanner.start();
    fixture.scanner.$wire.scanStudent = async () => ({ success: false, type: 'info', message: 'Siswa sudah dipindai.' });
    await fixture.read();
    assert.equal(fixture.scanner.toast.type, 'info');
    assert.equal(fixture.sounds(), 0);
    assert.equal(fixture.vibrations(), 0);
    fixture.scanner.lastCode = null;
    fixture.scanner.$wire.scanStudent = async () => ({ success: false, type: 'error', message: 'Siswa tidak ditemukan.' });
    await fixture.read();
    assert.equal(fixture.scanner.toast.type, 'error');
    assert.equal(fixture.timers.size, 1);
    assert.equal(fixture.sounds(), 0);
    fixture.scanner.destroy();
    assert.equal(fixture.timers.size, 0);
});

test('keeps scanning when audio is unsupported or vibration is blocked', async () => {
    const fixture = scannerFixture(undefined, false);
    fixture.sound.prepare();
    await fixture.scanner.start();
    await fixture.read();
    assert.equal(fixture.scanner.toast.type, 'success');
    assert.equal(fixture.scanner.scanning, true);
    assert.equal(fixture.scanner.cameraError, '');
});
