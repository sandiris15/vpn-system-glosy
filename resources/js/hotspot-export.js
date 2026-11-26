function escapeHtml(value) {
    return String(value).replace(/[&<>"'$]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '$': '&#36;',
    })[character]);
}

function documentPage(title, content, extraHead = '') {
    return `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${escapeHtml(title)}</title>
<link rel="stylesheet" href="css/hotspot.css">
${extraHead}
<script src="js/hotspot.js" defer></script>
</head>
<body>${content}</body>
</html>`;
}

export function createHotspotFiles(config, logo, promo, ads = []) {
    for (const color of [config.background, config.background2, config.accent]) {
        if (!/^#[0-9a-f]{6}$/i.test(color)) throw new Error('Warna tidak valid.');
    }
    if (!['voucher', 'separate'].includes(config.mode)) throw new Error('Metode login tidak valid.');
    if (!['clean', 'ads'].includes(config.model)) throw new Error('Model template tidak valid.');
    if (config.packages.some(item => !item.name.trim() || !item.price.trim())) {
        throw new Error('Isi nama dan harga setiap paket, atau hapus paket yang kosong.');
    }
    const text = value => escapeHtml(value);
    const brand = text(config.brand || 'Hotspot WiFi');
    const logoHtml = logo
        ? `<img class="logo" src="${text(logo)}" alt="Logo usaha">`
        : '<div class="logo-placeholder">WiFi</div>';
    const promoHtml = promo ? `<img class="promo-image" src="${text(promo)}" alt="Gambar promo">` : '';
    const packagesHtml = config.packages.length
        ? `<section class="packages"><h2>Daftar paket</h2>${config.packages.map(item => `<div class="package"><span>${text(item.name)}</span><strong>${text(item.price)}</strong></div>`).join('')}</section>`
        : '';
    const adsHtml = config.model === 'ads' && ads.length
        ? `<section class="advertisements" aria-label="Iklan"><h2>Iklan</h2><div class="ad-slides" data-ad-track tabindex="0" aria-label="Gambar iklan">${ads.map((path, index) => `<img src="${text(path)}" alt="Iklan ${index + 1}">`).join('')}</div>${ads.length > 1 ? '<div class="ad-controls"><button type="button" data-ad-prev aria-label="Iklan sebelumnya">←</button><button type="button" data-ad-next aria-label="Iklan berikutnya">→</button></div>' : ''}</section>`
        : '';
    const chapHead = `$(if chap-id)
<script src="js/md5.js"></script>
<script>window.hotspotChap = {id: '$(chap-id)', challenge: '$(chap-challenge)'};</script>
$(endif)`;
    const header = `<header class="hero"><div class="brand">${logoHtml}<div><h1>${brand}</h1><p>${text(config.brandCaption)}</p></div></div>
<h2>${text(config.welcomeTitle)}</h2></header>`;
    const footer = `<footer class="footer">${text(config.footerText)}</footer>`;
    const login = documentPage(config.brand || 'Hotspot WiFi', `
<main class="page" id="portal" inert>${header}
<section class="card"><h2>Login Internet</h2>
<div class="tabs" aria-label="Metode login"><button type="button" id="voucher-tab" aria-pressed="false">Voucher</button><button type="button" id="member-tab" aria-pressed="false">Member</button></div>
$(if error)<p class="error" role="alert" data-router-text="$(error-esc)"></p>$(endif)
$(if chap-id)
<form name="sendin" action="$(link-login-only)" method="post" hidden><input name="username"><input name="password"><input name="dst" value="$(link-orig-esc)" data-escaped-url><input name="popup" value="false"></form>
$(endif)
<form id="login" action="$(link-login-only)" method="post" data-mode="${config.mode}">
<input type="hidden" name="dst" value="$(link-orig-esc)" data-escaped-url><input type="hidden" name="popup" value="false">
<label><span id="username-label">Kode voucher</span><input name="username" autocomplete="username" required></label>
<label id="password-label" hidden>Password<input name="password" type="password" autocomplete="current-password"></label>
<p id="voucher-hint" class="hint">Kode voucher digunakan sebagai username dan password.</p>
<p id="login-error" class="error" role="alert"></p>
<button id="submit" class="submit" type="submit" disabled>Masuk Hotspot</button>
<noscript><p class="error">Aktifkan JavaScript untuk menggunakan halaman login.</p></noscript>
</form></section>
${packagesHtml}${adsHtml}${footer}</main>
<div class="overlay" id="promo" role="dialog" aria-modal="true" aria-label="Promo"><div class="promo">${promoHtml}${config.promoTitle ? `<h2>${text(config.promoTitle)}</h2>` : ''}<p>${text(config.promoDescription)}</p><button class="promo-button" id="close-promo" type="button">${text(config.promoButton)} →</button></div></div>`, chapHead);

    const status = documentPage('Status Hotspot', `<main class="page">${header}
<section class="card"><h2>Status koneksi</h2><dl class="stats">
<div><dt>Username</dt><dd data-router-text="$(username-esc)"></dd></div>
<div><dt>IP</dt><dd>$(ip)</dd></div><div><dt>Terhubung</dt><dd>$(uptime)</dd></div>
<div><dt>Upload</dt><dd>$(bytes-in-nice)</dd></div><div><dt>Download</dt><dd>$(bytes-out-nice)</dd></div>
$(if session-time-left)<div><dt>Sisa waktu</dt><dd>$(session-time-left)</dd></div>$(endif)
</dl>
$(if advert-pending == yes)<p><a class="link" href="$(link-advert)">Lihat iklan Hotspot</a></p>$(endif)
<form action="$(link-logout)" method="post"><input type="hidden" name="erase-cookie" value="on"><button class="submit" type="submit">Keluar Hotspot</button></form></section>${footer}</main>`);
    const logout = documentPage('Logout Hotspot', `<main class="page">${header}
<section class="card"><h2>Anda telah keluar</h2><dl class="stats">
<div><dt>Username</dt><dd data-router-text="$(username-esc)"></dd></div>
<div><dt>Durasi</dt><dd>$(uptime)</dd></div>
<div><dt>Upload</dt><dd>$(bytes-in-nice)</dd></div><div><dt>Download</dt><dd>$(bytes-out-nice)</dd></div>
</dl><a class="link" href="$(link-login)">Masuk kembali</a></section>${footer}</main>`);
    const alogin = documentPage('Login berhasil', `<main class="page">${header}
<section class="card"><h2>Login berhasil</h2><p data-router-redirect="$(link-redirect-esc)">Mengarahkan ke tujuan...</p>
<a class="link" href="$(link-status)">Lihat status koneksi</a></section>${footer}</main>`);
    const error = documentPage('Kesalahan Hotspot', `<main class="page">${header}
<section class="card"><h2>Koneksi gagal</h2><p class="error" data-router-text="$(error-esc)"></p>
<a class="link" href="$(link-login)">Kembali ke login</a></section>${footer}</main>`);
    const redirect = `$(if http-status == 302)Hotspot login required$(endif)
$(if http-header == "Location")$(link-redirect)$(endif)
${documentPage('Menghubungkan', `<main class="page">${header}
<section class="card"><h2>Mengarahkan...</h2><p data-router-redirect="$(link-redirect-esc)">Tunggu sebentar.</p>
<a class="link" href="$(link-login)">Buka halaman login</a></section>${footer}</main>`)}`;

    return {
        'login.html': login,
        'status.html': status,
        'logout.html': logout,
        'alogin.html': alogin,
        'error.html': error,
        'redirect.html': redirect,
    };
}
