import JSZip from 'jszip';
import css from '../hotspot/hotspot.css?raw';
import script from '../hotspot/hotspot.js?raw';
import md5 from 'js-md5/build/md5.min.js?raw';
import md5License from '../../node_modules/js-md5/LICENSE.txt?raw';
import { createHotspotFiles } from './hotspot-export';

function imagePath(kind, file) {
    if (!file) return '';
    const extension = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp' }[file.type];
    if (!extension) throw new Error('Format gambar tidak didukung.');
    return `img/${kind}.${extension}`;
}

export async function createHotspotArchive(config, logoFile, logoData, promoFile, promoData, ads = []) {
    const logoPath = imagePath('logo', logoFile);
    const promoPath = imagePath('promo', promoFile);
    const adPaths = config.model === 'ads' ? ads.map((ad, index) => imagePath(`iklan-${index + 1}`, ad.file)) : [];
    const files = createHotspotFiles(config, logoPath, promoPath, adPaths);
    const zip = new JSZip();
    const hotspot = zip.folder('hotspot');
    for (const [name, content] of Object.entries(files)) hotspot.file(name, content);
    hotspot.file('css/hotspot.css', `:root{--background:${config.background};--background2:${config.background2};--accent:${config.accent}}\n${css}`);
    hotspot.file('js/hotspot.js', script);
    hotspot.file('js/md5.js', md5);
    hotspot.file('js/LICENSE-md5.txt', md5License);
    hotspot.folder('img');
    if (logoPath) hotspot.file(logoPath, logoData.split(',')[1], { base64: true });
    if (promoPath) hotspot.file(promoPath, promoData.split(',')[1], { base64: true });
    adPaths.forEach((path, index) => hotspot.file(path, ads[index].data.split(',')[1], { base64: true }));
    zip.file('PETUNJUK.txt', [
        'TEMPLATE HOTSPOT MIKROTIK',
        '',
        '1. Cadangkan dan salin folder Hotspot asli dari router ke komputer dengan nama baru.',
        '2. Ekstrak ZIP, lalu salin isi folder hotspot dari ZIP ke folder salinan tersebut dan ganti file yang sama.',
        '3. Unggah folder salinan ke router, lalu atur html-directory-override profil Hotspot ke folder itu.',
        '4. Coba login voucher, member, status dan logout pada router uji sebelum digunakan.',
        '',
        'Folder berisi halaman login, status, logout, alogin, redirect, error, CSS, JavaScript dan gambar pilihan.',
        'Pada model iklan, gambar iklan di bagian bawah dapat digeser dan disimpan di folder img.',
        'File RouterOS lain, termasuk errors.txt dan folder xml untuk WISPr, dipertahankan dari salinan folder asli.',
        'Voucher menggunakan kode yang sama untuk username dan password; akun harus sudah ada di router atau RADIUS.',
        'HTTP-PAP tanpa HTTPS mengirimkan password tanpa enkripsi transport; gunakan HTTPS bila memungkinkan.',
        'Jika router memakai folder flash, simpan folder Hotspot baru di dalam flash agar tetap ada setelah reboot.',
        'js/md5.js menggunakan js-md5; lisensinya ada di js/LICENSE-md5.txt.',
    ].join('\n'));
    return zip.generateAsync({ type: 'blob', compression: 'DEFLATE' });
}
