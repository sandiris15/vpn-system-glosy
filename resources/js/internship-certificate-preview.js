import { drawCertificate, loadImage } from './internship-certificate.js';

function formatDate(value, placeholder) {
    if (!value) return placeholder;

    const date = new Date(`${value}T00:00:00Z`);
    if (Number.isNaN(date.getTime())) return placeholder;
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit', month: 'long', year: 'numeric', timeZone: 'UTC',
    }).format(date);
}

export default assets => ({
    error: '',
    logo: null,
    signature: null,
    async init() {
        try {
            [this.logo, this.signature] = await Promise.all([loadImage(assets.logo), loadImage(assets.signature)]);
            this.update();
        } catch (error) {
            this.error = error.message;
        }
    },
    update() {
        if (!this.logo || !this.signature) return;

        const value = name => this.$refs.form.elements.namedItem(name).value;
        const data = {
            name: value('name').trim() || 'Nama Peserta',
            number: 'Diberikan setelah disimpan',
            program: value('program'),
            location: value('location').trim() || 'Lokasi kegiatan',
            institution: value('institution').trim() || 'Asal instansi/sekolah',
            birthDate: formatDate(value('birth_date'), 'Tanggal lahir'),
            period: `${formatDate(value('start_date'), 'Tanggal mulai')} – ${formatDate(value('end_date'), 'Tanggal selesai')}`,
            issued: formatDate(value('issued_date'), 'Tanggal terbit'),
        };
        try {
            drawCertificate(this.$refs.canvas, data, this.logo, this.signature);
            this.error = '';
        } catch (error) {
            this.error = error.message;
        }
    },
});
