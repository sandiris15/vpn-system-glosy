function readImage(file) {
    if (!file) return Promise.resolve('');
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const image = new Image();
            image.onload = () => resolve(reader.result);
            image.onerror = () => reject(new Error('Gambar tidak dapat dibuka. Pilih file yang valid.'));
            image.src = reader.result;
        };
        reader.onerror = () => reject(new Error('Gambar tidak dapat dibaca. Pilih ulang file.'));
        reader.onabort = () => reject(new Error('Pembacaan gambar dibatalkan.'));
        reader.readAsDataURL(file);
    });
}

export default function hotspotPreview() {
    return {
        model: 'clean',
        background: '#20364b',
        background2: '#15233a',
        accent: '#0284c7',
        brand: 'Hotspot WiFi',
        brandCaption: 'Akses internet untuk Anda',
        welcomeTitle: 'Terhubung lebih mudah',
        promoTitle: '',
        promoDescription: 'Lanjutkan untuk masuk ke hotspot.',
        promoButton: 'Lanjut ke login',
        footerText: 'Nikmati koneksi yang nyaman setiap hari.',
        mode: 'voucher',
        logo: '',
        logoFile: null,
        logoError: '',
        promo: '',
        promoFile: null,
        promoError: '',
        ads: [],
        adsError: '',
        adsLoading: false,
        adsRequest: 0,
        packages: [],
        exportError: '',
        exporting: false,
        showPromo: true,
        previewMessage: '',
        setLogo(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                this.logoError = 'Pilih logo PNG, JPG, atau WebP berukuran maksimal 2 MB.';
                event.target.value = '';
                return;
            }
            if (this.logo) URL.revokeObjectURL(this.logo);
            this.logo = URL.createObjectURL(file);
            this.logoFile = file;
            this.logoError = '';
        },
        clearLogo() {
            if (this.logo) URL.revokeObjectURL(this.logo);
            this.logo = '';
            this.logoFile = null;
            this.logoError = '';
            this.$refs.logoInput.value = '';
        },
        logoLoadError() {
            this.clearLogo();
            this.logoError = 'Logo tidak dapat dibuka. Pilih gambar yang valid.';
        },
        setPromo(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                this.promoError = 'Pilih gambar promo PNG atau JPG berukuran maksimal 2 MB.';
                event.target.value = '';
                return;
            }
            if (this.promo) URL.revokeObjectURL(this.promo);
            this.promo = URL.createObjectURL(file);
            this.promoFile = file;
            this.promoError = '';
            this.showPromo = true;
        },
        clearPromo() {
            if (this.promo) URL.revokeObjectURL(this.promo);
            this.promo = '';
            this.promoFile = null;
            this.promoError = '';
            this.$refs.promoInput.value = '';
            this.showPromo = true;
        },
        promoLoadError() {
            this.clearPromo();
            this.promoError = 'Gambar promo tidak dapat dibuka. Pilih file PNG atau JPG yang valid.';
        },
        async setAds(event) {
            const files = Array.from(event.target.files);
            if (!files.length) return;
            const request = ++this.adsRequest;
            this.exportError = '';
            this.adsLoading = false;
            if (files.some(file => !['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024)) {
                this.adsError = 'Pilih gambar iklan PNG, JPG, atau WebP, maksimal 2 MB per gambar.';
                event.target.value = '';
                return;
            }
            this.adsLoading = true;
            this.adsError = '';
            try {
                await Promise.all(files.map(readImage));
                if (request !== this.adsRequest) return;
                this.ads.forEach(ad => URL.revokeObjectURL(ad.preview));
                this.ads = files.map(file => ({ file, preview: URL.createObjectURL(file) }));
                this.$nextTick(() => { this.$refs.adSlides.scrollLeft = 0; });
            } catch (error) {
                if (request !== this.adsRequest) return;
                this.adsError = error.message;
                event.target.value = '';
            } finally {
                if (request === this.adsRequest) this.adsLoading = false;
            }
        },
        clearAds() {
            this.adsRequest++;
            this.adsLoading = false;
            this.ads.forEach(ad => URL.revokeObjectURL(ad.preview));
            this.ads = [];
            this.adsError = '';
            this.exportError = '';
            this.$refs.adsInput.value = '';
        },
        selectMode(mode) {
            this.mode = mode;
            this.previewMessage = '';
        },
        addPackage() {
            if (this.packages.length >= 20) {
                this.exportError = 'Maksimal 20 paket.';
                return;
            }
            this.packages.push({ name: '', price: '' });
            this.exportError = '';
        },
        async downloadTemplate() {
            if (this.exporting) return;
            this.exportError = '';
            if (this.logoError || this.promoError || (this.model === 'ads' && this.adsError)) {
                this.exportError = 'Perbaiki gambar yang tidak valid sebelum mengunduh.';
                return;
            }
            if (this.model === 'ads' && this.adsLoading) {
                this.exportError = 'Tunggu gambar iklan selesai diperiksa.';
                return;
            }
            this.exporting = true;
            try {
                const config = {
                    background: this.background, background2: this.background2, accent: this.accent,
                    brand: this.brand, brandCaption: this.brandCaption, welcomeTitle: this.welcomeTitle,
                    promoTitle: this.promoTitle, promoDescription: this.promoDescription,
                    promoButton: this.promoButton, footerText: this.footerText, mode: this.mode, model: this.model,
                    packages: this.packages.map(item => ({ name: item.name, price: item.price })),
                };
                const logoFile = this.logoFile;
                const promoFile = this.promoFile;
                const adFiles = this.model === 'ads' ? this.ads.map(ad => ad.file) : [];
                const [{ createHotspotArchive }, logo, promo, adData] = await Promise.all([
                    import('./hotspot-archive'), readImage(logoFile), readImage(promoFile),
                    Promise.all(adFiles.map(readImage)),
                ]);
                const ads = adFiles.map((file, index) => ({ file, data: adData[index] }));
                const archive = await createHotspotArchive(config, logoFile, logo, promoFile, promo, ads);
                const url = URL.createObjectURL(archive);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'template-hotspot.zip';
                link.hidden = true;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 10000);
            } catch (error) {
                this.exportError = `Template gagal diunduh: ${error.message}`;
            } finally {
                this.exporting = false;
            }
        },
    };
}
