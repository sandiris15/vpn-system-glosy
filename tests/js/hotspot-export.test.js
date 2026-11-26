import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import md5 from 'js-md5';
import { createHotspotFiles } from '../../resources/js/hotspot-export.js';

const config = {
    background: '#20364b', background2: '#15233a', accent: '#0284c7',
    brand: 'WiFi', brandCaption: 'Internet', welcomeTitle: 'Terhubung',
    promoTitle: 'Promo', promoDescription: 'Lanjut', promoButton: 'Login',
    footerText: 'Terima kasih', mode: 'voucher', model: 'clean', packages: [],
};

test('exports configured packages safely without allowing RouterOS template injection', () => {
    const files = createHotspotFiles({
        ...config,
        brand: '<script>$(if error)</script>',
        packages: [{ name: '1 hari & malam', price: 'Rp 5.000' }],
    }, 'img/logo.png', 'img/promo.jpg');
    const html = files['login.html'];
    assert.ok(html.includes('&lt;script&gt;&#36;(if error)&lt;/script&gt;'));
    assert.ok(html.includes('1 hari &amp; malam'));
    assert.ok(html.includes('Rp 5.000'));
    assert.ok(html.includes('img/logo.png'));
    assert.match(html, /<div class="brand"><img class="logo"[^>]*><div><h1>/);
    assert.ok(html.includes('img/promo.jpg'));
    assert.ok(html.includes('$(link-login-only)'));
    assert.ok(html.includes("id: '$(chap-id)', challenge: '$(chap-challenge)'"));
    for (const name of ['status.html', 'logout.html', 'alogin.html', 'error.html', 'redirect.html']) {
        assert.ok(files[name].includes('css/hotspot.css'));
    }
});

test('exports ad slides only for the ad model and leaves empty galleries out', () => {
    const ads = ['img/iklan-1.png', 'img/iklan-2.jpg'];
    const clean = createHotspotFiles(config, '', '', ads)['login.html'];
    const empty = createHotspotFiles({ ...config, model: 'ads' }, '', '', [])['login.html'];
    const single = createHotspotFiles({ ...config, model: 'ads' }, '', '', ads.slice(0, 1))['login.html'];
    const html = createHotspotFiles({ ...config, model: 'ads' }, '', '', ads)['login.html'];
    assert.ok(!clean.includes('img/iklan-1.png'));
    assert.ok(!empty.includes('data-ad-track'));
    assert.ok(single.includes('img/iklan-1.png'));
    assert.ok(!single.includes('data-ad-next'));
    assert.ok(html.includes('img/iklan-1.png'));
    assert.ok(html.includes('img/iklan-2.jpg'));
    assert.ok(html.includes('data-ad-prev'));
    assert.ok(html.includes('data-ad-next'));
    assert.ok(!html.includes('Pilih metode login'));
    assert.ok(!html.includes('Selamat datang'));
});

test('rejects invalid colors and incomplete packages instead of exporting', () => {
    assert.throws(() => createHotspotFiles({ ...config, accent: 'red; }' }, '', ''), /Warna/);
    assert.throws(() => createHotspotFiles({
        ...config, packages: [{ name: '1 hari', price: ' ' }],
    }, '', ''), /nama dan harga/);
    assert.throws(() => createHotspotFiles({ ...config, model: 'invalid' }, '', ''), /Model template/);
});

test('CHAP uses raw challenge bytes and the selected voucher or member password', () => {
    const script = readFileSync(new URL('../../resources/hotspot/hotspot.js', import.meta.url), 'utf8');
    const events = {};
    const controls = Object.fromEntries(
        ['voucher-tab', 'member-tab', 'password-label', 'username-label', 'voucher-hint', 'login-error', 'close-promo', 'submit']
            .map(id => [id, {
                addEventListener: (type, handler) => { events[`${id}:${type}`] = handler; },
                setAttribute() {},
            }]),
    );
    const form = {
        elements: { username: { value: 'voucher', focus() {} }, password: { value: '' } },
        getAttribute: () => 'voucher',
        addEventListener: (type, handler) => { events[`form:${type}`] = handler; },
    };
    const sendin = { elements: { username: {}, password: {} }, submit() {} };
    const window = { hotspotChap: { id: '\xF9', challenge: '\x80\xFF' }, md5 };
    const document = {
        querySelectorAll: () => [],
        querySelector: () => null,
        getElementById: id => id === 'login' ? form : controls[id],
        forms: { sendin },
    };
    runInNewContext(script, { window, document, console });
    const hash = value => createHash('md5').update(Buffer.from([0xf9, ...Buffer.from(value, 'latin1'), 0x80, 0xff])).digest('hex');
    let prevented = false;
    events['form:submit']({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(sendin.elements.username.value, 'voucher');
    assert.equal(sendin.elements.password.value, hash('voucher'));

    events['member-tab:click']();
    form.elements.username.value = 'member';
    form.elements.password.value = 's\xE9cret';
    events['form:submit']({ preventDefault() {} });
    assert.equal(sendin.elements.username.value, 'member');
    assert.equal(sendin.elements.password.value, hash('s\xE9cret'));
});

test('RouterOS escaped values decode spaces and literal plus signs correctly', () => {
    const script = readFileSync(new URL('../../resources/hotspot/hotspot.js', import.meta.url), 'utf8');
    const text = { getAttribute: () => 'Nama+user%2B1' };
    const document = {
        querySelectorAll: selector => selector === '[data-router-text]' ? [text] : [],
        querySelector: () => null,
        getElementById: () => null,
    };
    runInNewContext(script, { document, console });
    assert.equal(text.textContent, 'Nama user+1');
});
