(function () {
    function decode(value) {
        try {
            return decodeURIComponent(value.replace(/\+/g, ' '));
        } catch (error) {
            console.warn('Nilai RouterOS tidak dapat didekode.', error);
            return value;
        }
    }

    document.querySelectorAll('[data-router-text]').forEach(function (element) {
        element.textContent = decode(element.getAttribute('data-router-text'));
    });

    var redirect = document.querySelector('[data-router-redirect]');
    if (redirect) {
        try {
            var value = decode(redirect.getAttribute('data-router-redirect'));
            var destination = value ? new URL(value, window.location.href) : null;
            if (destination && (destination.protocol === 'http:' || destination.protocol === 'https:')) {
                window.location.replace(destination.href);
            } else {
                redirect.textContent = 'Alamat tujuan tidak tersedia. Gunakan tautan di bawah.';
            }
        } catch (error) {
            redirect.textContent = 'Alamat tujuan tidak valid. Gunakan tautan di bawah.';
        }
    }

    var adTrack = document.querySelector('[data-ad-track]');
    if (adTrack) {
        var adPrev = document.querySelector('[data-ad-prev]');
        var adNext = document.querySelector('[data-ad-next]');
        if (adPrev && adNext) {
            adPrev.addEventListener('click', function () { adTrack.scrollBy({ left: -adTrack.clientWidth, behavior: 'smooth' }); });
            adNext.addEventListener('click', function () { adTrack.scrollBy({ left: adTrack.clientWidth, behavior: 'smooth' }); });
        }
    }

    var form = document.getElementById('login');
    if (!form) return;
    document.querySelectorAll('[data-escaped-url]').forEach(function (input) {
        input.value = decode(input.value);
    });
    var mode = form.getAttribute('data-mode');
    var voucher = document.getElementById('voucher-tab');
    var member = document.getElementById('member-tab');
    var passwordLabel = document.getElementById('password-label');
    var password = form.elements.password;

    function selectMode(next) {
        mode = next;
        voucher.setAttribute('aria-pressed', mode === 'voucher');
        member.setAttribute('aria-pressed', mode === 'separate');
        document.getElementById('username-label').textContent = mode === 'voucher' ? 'Kode voucher' : 'Username';
        passwordLabel.hidden = mode === 'voucher';
        password.required = mode === 'separate';
        password.value = '';
        document.getElementById('voucher-hint').hidden = mode !== 'voucher';
        document.getElementById('login-error').textContent = '';
    }

    voucher.addEventListener('click', function () { selectMode('voucher'); });
    member.addEventListener('click', function () { selectMode('separate'); });
    document.getElementById('close-promo').addEventListener('click', function () {
        document.getElementById('promo').hidden = true;
        document.getElementById('portal').inert = false;
        form.elements.username.focus();
    });
    form.addEventListener('submit', function (event) {
        var user = form.elements.username.value;
        var secret = mode === 'voucher' ? user : password.value;
        password.value = secret;
        if (window.hotspotChap) {
            event.preventDefault();
            if (typeof window.md5 !== 'function') {
                document.getElementById('login-error').textContent = 'File js/md5.js tidak ditemukan. Periksa folder Hotspot.';
                return;
            }
            var raw = window.hotspotChap.id + secret + window.hotspotChap.challenge;
            var bytes = [];
            for (var i = 0; i < raw.length; i++) bytes.push(raw.charCodeAt(i) & 255);
            var sendin = document.forms.sendin;
            sendin.elements.username.value = user;
            sendin.elements.password.value = window.md5(bytes);
            sendin.submit();
        }
    });
    selectMode(mode);
    document.getElementById('submit').disabled = false;
})();
