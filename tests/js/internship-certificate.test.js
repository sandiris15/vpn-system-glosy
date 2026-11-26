import { test } from 'node:test';
import assert from 'node:assert/strict';
import internshipCertificate from '../../resources/js/internship-certificate.js';
import internshipCertificatePreview from '../../resources/js/internship-certificate-preview.js';

test('renders selected program, custom location, corrected city and website', async () => {
    const originalImage = globalThis.Image;
    globalThis.Image = class {
        width = 768;
        height = 984;
        set src(value) { this.onload(); }
    };

    try {
        for (const program of ['magang', 'pkl']) {
            const text = [];
            const ctx = {
                fillText: value => text.push(value),
                measureText: () => ({ width: 100 }),
                fillRect() {}, beginPath() {}, moveTo() {}, bezierCurveTo() {},
                stroke() {}, strokeRect() {}, lineTo() {}, fill() {}, drawImage() {},
                roundRect() {}, arc() {},
            };
            const component = internshipCertificate({
                name: 'Peserta', number: '022/Sertifikat/PKL/X/2026',
                program, location: 'Kantor Pontianak', institution: 'SMK Negeri 1',
                birthDate: '01 Mei 2008', period: 'September - Oktober',
                issued: '31 Oktober 2026', logo: '/logo', signature: '/signature',
            });

            component.$refs = { canvas: { width: 1800, height: 1200, getContext: () => ctx } };
            await component.init();

            assert.equal(component.ready, true);
            assert.ok(text.includes(program === 'pkl' ? 'P K L' : 'M A G A N G'));
            assert.ok(text.includes(program === 'pkl' ? 'Periode PKL' : 'Periode Magang'));
            assert.ok(text.includes('PT Naomi Aurora Teknologi, yang telah menunjukkan dedikasi,'));
            assert.ok(text.includes('Kantor Pontianak'));
            assert.ok(text.includes('Asal instansi/sekolah: SMK Negeri 1'));
            assert.ok(text.includes('Tanggal lahir: 01 Mei 2008'));
            assert.ok(text.includes('Mempawah, 31 Oktober 2026'));
            assert.ok(text.includes('www.naomiaurora.com'));
        }
    } finally {
        globalThis.Image = originalImage;
    }
});

test('live preview updates form values without assigning a certificate number', async () => {
    const originalImage = globalThis.Image;
    globalThis.Image = class {
        width = 768;
        height = 984;
        set src(value) { this.onload(); }
    };

    try {
        const text = [];
        const ctx = {
            fillText: value => text.push(value),
            measureText: () => ({ width: 100 }),
            fillRect() {}, beginPath() {}, moveTo() {}, bezierCurveTo() {},
            stroke() {}, strokeRect() {}, lineTo() {}, fill() {}, drawImage() {},
            roundRect() {}, arc() {},
        };
        const values = {
            name: '', institution: '', birth_date: '', program: 'magang', location: '',
            start_date: '', end_date: '', issued_date: '',
        };
        const component = internshipCertificatePreview({ logo: '/logo', signature: '/signature' });
        component.$refs = {
            canvas: { width: 1800, height: 1200, getContext: () => ctx },
            form: { elements: { namedItem: name => ({ value: values[name] }) } },
        };
        await component.init();
        assert.ok(text.includes('Nama Peserta'));
        assert.ok(text.includes('No. Diberikan setelah disimpan'));
        assert.ok(text.includes('Tanggal mulai – Tanggal selesai'));

        text.length = 0;
        Object.assign(values, {
            name: 'Dewi', institution: 'SMK 1', birth_date: '2008-05-01', program: 'pkl',
            location: 'Mempawah', start_date: '2026-09-01', end_date: '2026-10-30',
            issued_date: '2026-10-31',
        });

        component.update();
        assert.equal(component.error, '');
        assert.ok(text.includes('Dewi'));
        assert.ok(text.includes('P K L'));
        assert.ok(text.includes('Mempawah'));
        assert.ok(text.includes('Tanggal lahir: 01 Mei 2008'));
        assert.ok(text.includes('01 September 2026 – 30 Oktober 2026'));
        assert.ok(text.includes('Mempawah, 31 Oktober 2026'));
    } finally {
        globalThis.Image = originalImage;
    }
});

test('long location names fit within the certificate location area', async () => {
    const originalImage = globalThis.Image;
    globalThis.Image = class {
        width = 768;
        height = 984;
        set src(value) { this.onload(); }
    };

    try {
        const drawn = [];
        const ctx = {
            font: '',
            fillText(text, x, y, maxWidth) { if (x === 1185 && maxWidth === 650) drawn.push({ text, width: this.measureText(text).width }); },
            measureText(text) { return { width: text.length * parseInt(this.font, 10) * 0.7 }; },
            fillRect() {}, beginPath() {}, moveTo() {}, bezierCurveTo() {},
            stroke() {}, strokeRect() {}, lineTo() {}, fill() {}, drawImage() {},
            roundRect() {}, arc() {},
        };
        const location = 'W'.repeat(120);
        const component = internshipCertificate({
            name: 'Peserta', number: '022', program: 'pkl', location,
            period: 'September - Oktober', issued: '31 Oktober 2026', logo: '/logo', signature: '/signature',
        });
        component.$refs = { canvas: { width: 1800, height: 1200, getContext: () => ctx } };
        await component.init();

        assert.equal(component.ready, true);
        assert.equal(drawn.length, 2);
        assert.equal(drawn.map(line => line.text).join(''), location);
        assert.ok(drawn.every(line => line.width <= 650));
    } finally {
        globalThis.Image = originalImage;
    }
});
