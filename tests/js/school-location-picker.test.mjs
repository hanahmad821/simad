import { test } from 'node:test';
import assert from 'node:assert/strict';
import { schoolLocationPicker } from '../../resources/js/school-location-picker.js';

function fixture({ lat = '', lng = '', secure = true, throwGps = false } = {}) {
    const timers = new Map();
    const callbacks = [];
    const listeners = {};
    let mapClick, markerDrag, removed = false, nextTimer = 0;
    const form = {
        isConnected: true,
        elements: { latitude: { value: lat }, longitude: { value: lng }, attendance_radius: { value: '100' } },
        addEventListener(name, callback) { listeners[name] = callback; },
        removeEventListener(name) { delete listeners[name]; },
    };
    const layer = () => ({
        addTo() { return this; }, on() { return this; }, remove() {},
        setLatLng(point) { this.point = point; return this; }, setRadius(radius) { this.radius = radius; return this; },
        getBounds() { return []; }, redraw() {},
    });
    const map = { setView() { return this; }, on(name, callback) { mapClick = callback; }, invalidateSize() {}, fitBounds() {}, remove() { removed = true; } };
    const L = {
        map: () => map, tileLayer: layer, divIcon: options => options,
        marker(point) { const result = layer(); result.point = point; result.on = (event, callback) => { if (event === 'dragend') markerDrag = callback; }; result.getLatLng = () => ({ lat: -7.61, lng: 109.31 }); return result; },
        circle(point, options) { const result = layer(); result.point = point; result.radius = options.radius; return result; },
    };
    const env = {
        isSecureContext: secure,
        setTimeout(callback, ms) { const id = ++nextTimer; timers.set(id, { callback, ms }); return id; },
        clearTimeout(id) { timers.delete(id); },
        navigator: { geolocation: { getCurrentPosition(success, error, options) {
            if (throwGps) throw new Error('Unavailable');
            callbacks.push({ success, error, options });
        } } },
    };
    const picker = schoolLocationPicker(L, env);
    picker.$el = form;
    picker.$refs = { locationMap: {} };
    picker.$nextTick = callback => callback();
    picker.init();
    return { picker, timers, callbacks, form, listeners, click: point => mapClick({ latlng: point }), drag: () => markerDrag(), removed: () => removed };
}

test('a pending permission request always stops after the independent deadline and ignores late callbacks', () => {
    const { picker, timers, callbacks, form } = fixture();
    picker.locate();
    assert.equal(picker.locating, true);
    assert.equal([...timers.values()][0].ms, 20000);
    [...timers.values()][0].callback();
    assert.equal(picker.locating, false);
    assert.equal(picker.locationError, true);
    callbacks[0].success({ coords: { latitude: -7.6, longitude: 109.3, accuracy: 15 } });
    assert.equal(form.elements.latitude.value, '');
    picker.locate();
    assert.equal(callbacks.length, 2);
    callbacks[1].success({ coords: { latitude: -7.6, longitude: 109.3, accuracy: 15 } });
    assert.equal(form.elements.latitude.value, '-7.6000000');
    assert.equal(picker.locating, false);
    assert.equal(timers.size, 0);
});

test('pin selection cancels GPS and cannot be overwritten by its delayed result', () => {
    const f = fixture();
    f.picker.locate();
    f.click({ lat: -7.5, lng: 109.5 });
    f.callbacks[0].success({ coords: { latitude: -8, longitude: 110, accuracy: 100 } });
    assert.equal(f.form.elements.latitude.value, '-7.5000000');
    assert.equal(f.form.elements.longitude.value, '109.5000000');
    assert.equal(f.picker.locating, false);
    assert.equal(f.picker.hasPoint, true);
    f.drag();
    assert.equal(f.form.elements.latitude.value, '-7.6100000');
});

test('zero coordinates are valid; blank or invalid manual fields remove the pin', () => {
    const f = fixture({ lat: '0', lng: '0' });
    assert.equal(f.picker.hasPoint, true);
    f.form.elements.latitude.value = '';
    f.listeners.input({ target: { name: 'latitude' } });
    assert.equal(f.picker.hasPoint, false);
    f.form.elements.latitude.value = '95';
    f.listeners.input({ target: { name: 'latitude' } });
    assert.equal(f.picker.hasPoint, false);
    f.picker.resetPoint();
    assert.equal(f.picker.hasPoint, true);
    assert.equal(f.form.elements.latitude.value, '0');
});

test('denied, unavailable, timed out, insecure and throwing GPS all leave manual selection usable', () => {
    for (const code of [1, 2, 3]) {
        const f = fixture();
        f.picker.locate();
        f.callbacks[0].error({ code });
        assert.equal(f.picker.locating, false);
        assert.equal(f.picker.locationError, true);
        assert.equal(f.timers.size, 0);
        f.click({ lat: -7, lng: 109 });
        assert.equal(f.picker.locationError, false);
    }
    for (const options of [{ secure: false }, { throwGps: true }]) {
        const f = fixture(options);
        f.picker.locate();
        assert.equal(f.picker.locating, false);
        assert.equal(f.picker.locationError, true);
        assert.equal(f.timers.size, 0);
    }
});

test('cancel and page navigation clean up GPS deadlines and ignore obsolete callbacks', () => {
    const f = fixture();
    f.picker.locate();
    f.picker.cancelLocate();
    assert.equal(f.picker.locating, false);
    assert.equal(f.timers.size, 0);
    f.picker.locate();
    f.picker.destroy();
    f.callbacks[1].success({ coords: { latitude: -7, longitude: 109, accuracy: 20 } });
    assert.equal(f.form.elements.latitude.value, '');
    assert.equal(f.removed(), true);
    assert.equal(f.timers.size, 0);
    assert.deepEqual(f.listeners, {});
});

test('manual coordinate changes stop the searching message and supersede a pending GPS request', () => {
    const f = fixture();
    f.picker.locate();
    f.form.elements.latitude.value = '-7.4';
    f.form.elements.longitude.value = '109.4';
    f.listeners.input({ target: { name: 'latitude' } });
    assert.equal(f.picker.locating, false);
    assert.equal(f.picker.locationMessage.includes('Mencari'), false);
    f.callbacks[0].success({ coords: { latitude: -8, longitude: 110, accuracy: 20 } });
    assert.equal(f.form.elements.latitude.value, '-7.4');
});
