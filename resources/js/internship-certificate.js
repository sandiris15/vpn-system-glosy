export function loadImage(url) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('Logo atau tanda tangan tidak dapat dimuat. Muat ulang halaman.'));
        image.src = url;
    });
}

function drawLocation(ctx, location) {
    let lines = [];
    for (let size = 25; size >= 10; size--) {
        ctx.font = `${size}px Georgia, serif`;
        lines = [''];
        for (const word of location.trim().split(/\s+/)) {
            const last = lines.length - 1;
            const combined = lines[last] ? `${lines[last]} ${word}` : word;
            if (ctx.measureText(combined).width <= 650) {
                lines[last] = combined;
            } else if (ctx.measureText(word).width <= 650) {
                lines.push(word);
            } else {
                if (lines[last]) lines.push('');
                for (const character of Array.from(word)) {
                    const index = lines.length - 1;
                    if (ctx.measureText(lines[index] + character).width > 650 && lines[index]) lines.push('');
                    lines[lines.length - 1] += character;
                }
            }
        }
        if (lines.length <= 2) break;
    }

    if (lines.length > 2) {
        lines = [lines[0], lines.slice(1).join(' ')];
    }
    lines.forEach((line, index) => ctx.fillText(line, 1185, lines.length === 1 ? 807 : 792 + index * 27, 650));
}

export function drawCertificate(canvas, data, logo, signature) {
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Browser tidak mendukung pembuatan gambar sertifikat.');

    const width = canvas.width;
    const height = canvas.height;
    ctx.fillStyle = '#fbfcfa';
    ctx.fillRect(0, 0, width, height);

    // Fine waves echo the reference without obscuring the certificate text.
    ctx.strokeStyle = '#e5f1ee';
    ctx.lineWidth = 2;
    for (let i = 0; i < 12; i++) {
        ctx.beginPath();
        ctx.moveTo(0, 280 + i * 12);
        ctx.bezierCurveTo(300, 240 + i * 12, 340, 620 + i * 12, 720, 600 + i * 12);
        ctx.bezierCurveTo(1050, 585 + i * 12, 1290, 420 + i * 12, 1800, 570 + i * 12);
        ctx.stroke();
    }

    ctx.strokeStyle = '#c99b32';
    ctx.lineWidth = 3;
    ctx.strokeRect(30, 30, width - 60, height - 60);

    ctx.fillStyle = '#d5a930';
    ctx.beginPath();
    ctx.moveTo(1120, 0);
    ctx.bezierCurveTo(1470, 0, 1520, 270, 1800, 320);
    ctx.lineTo(1800, 0);
    ctx.fill();
    ctx.fillStyle = '#00534b';
    ctx.beginPath();
    ctx.moveTo(1160, 0);
    ctx.bezierCurveTo(1480, 0, 1540, 215, 1800, 270);
    ctx.lineTo(1800, 0);
    ctx.fill();
    ctx.fillStyle = '#002f2d';
    ctx.beginPath();
    ctx.moveTo(1330, 0);
    ctx.bezierCurveTo(1510, 15, 1570, 175, 1800, 205);
    ctx.lineTo(1800, 0);
    ctx.fill();
    ctx.fillStyle = '#d5a930';
    ctx.beginPath();
    ctx.moveTo(0, 925);
    ctx.bezierCurveTo(430, 975, 570, 1170, 1020, 1200);
    ctx.lineTo(0, 1200);
    ctx.fill();
    ctx.fillStyle = '#00534b';
    ctx.beginPath();
    ctx.moveTo(0, 970);
    ctx.bezierCurveTo(400, 985, 530, 1160, 920, 1200);
    ctx.lineTo(0, 1200);
    ctx.fill();
    ctx.fillStyle = '#002f2d';
    ctx.beginPath();
    ctx.moveTo(0, 1030);
    ctx.bezierCurveTo(380, 1000, 440, 1170, 760, 1200);
    ctx.lineTo(0, 1200);
    ctx.fill();

    ctx.drawImage(logo, 75, 38, 410, 205);
    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'right';
    ctx.font = 'italic 25px Georgia, serif';
    ctx.fillText('Innovating Today,', 1740, 75);
    ctx.fillText('Empowering Tomorrow', 1740, 105);
    ctx.font = '22px Georgia, serif';
    ctx.fillText('www.naomiaurora.com', 1740, 145);

    ctx.textAlign = 'center';
    ctx.fillStyle = '#063b3b';
    ctx.font = 'bold 112px Georgia, serif';
    ctx.fillText('SERTIFIKAT', 900, 260);
    ctx.font = 'bold 55px Georgia, serif';
    ctx.fillText(data.program === 'pkl' ? 'P K L' : 'M A G A N G', 900, 325);
    ctx.strokeStyle = '#338c7d';
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.moveTo(380, 307);
    ctx.lineTo(670, 307);
    ctx.moveTo(1130, 307);
    ctx.lineTo(1420, 307);
    ctx.stroke();

    ctx.fillStyle = '#263a3a';
    ctx.font = '25px Georgia, serif';
    ctx.fillText('No. ' + data.number, 900, 369);
    ctx.font = '34px Georgia, serif';
    ctx.fillText('Diberikan kepada:', 900, 425);
    ctx.fillStyle = '#063b3b';
    let nameSize = 76;
    do {
        ctx.font = `italic ${nameSize}px Georgia, serif`;
        if (ctx.measureText(data.name).width <= 1050) break;
        nameSize -= 2;
    } while (nameSize > 24);
    ctx.fillText(data.name, 900, 510, 1050);
    ctx.strokeStyle = '#164f49';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(390, 535);
    ctx.lineTo(1410, 535);
    ctx.stroke();

    ctx.fillStyle = '#202e2e';
    ctx.font = '23px Georgia, serif';
    if (data.institution) ctx.fillText('Asal instansi/sekolah: ' + data.institution, 670, 568, 550);
    if (data.birthDate) ctx.fillText('Tanggal lahir: ' + data.birthDate, 1220, 568, 450);
    ctx.font = '32px Georgia, serif';
    const program = data.program === 'pkl' ? 'PKL' : 'magang';
    ctx.fillText(`Atas partisipasinya sebagai peserta ${program} di`, 900, 600);
    ctx.fillText('PT Naomi Aurora Teknologi, yang telah menunjukkan dedikasi,', 900, 645);
    ctx.fillText(`semangat belajar, dan kontribusi positif selama periode ${program}.`, 900, 690, 1250);

    ctx.strokeStyle = '#338c7d';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.roundRect(320, 730, 1220, 115, 50);
    ctx.stroke();
    ctx.fillStyle = '#123c39';
    ctx.font = 'bold 27px Georgia, serif';
    ctx.fillText(data.program === 'pkl' ? 'Periode PKL' : 'Periode Magang', 585, 768);
    ctx.fillText('Lokasi', 1185, 768);
    ctx.font = '25px Georgia, serif';
    ctx.fillText(data.period, 585, 805, 450);
    drawLocation(ctx, data.location);
    ctx.beginPath();
    ctx.moveTo(850, 748);
    ctx.lineTo(850, 827);
    ctx.stroke();

    ctx.fillStyle = '#202e2e';
    ctx.font = '29px Georgia, serif';
    ctx.fillText('Semoga pengalaman ini menjadi langkah awal untuk', 900, 885);
    ctx.fillText('meraih masa depan yang lebih baik.', 900, 925);

    ctx.textAlign = 'center';
    ctx.font = '26px Georgia, serif';
    ctx.fillText('Mempawah, ' + data.issued, 1490, 930);
    ctx.fillText('Direktur,', 1490, 965);
    ctx.drawImage(signature, 1415, 960, 150, 150 * signature.height / signature.width);
    ctx.strokeStyle = '#164f49';
    ctx.beginPath();
    ctx.moveTo(1300, 1130);
    ctx.lineTo(1680, 1130);
    ctx.stroke();
    ctx.font = 'bold 28px Georgia, serif';
    ctx.fillText('Sandi Riswandi', 1490, 1160);

    ctx.fillStyle = '#d5a930';
    ctx.beginPath();
    ctx.arc(245, 935, 105, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#fff1bb';
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.arc(245, 935, 91, 0, Math.PI * 2);
    ctx.stroke();
    ctx.drawImage(logo, 155, 890, 180, 90);
}

export default data => ({
    ready: false,
    error: '',
    async init() {
        try {
            const [logo, signature] = await Promise.all([loadImage(data.logo), loadImage(data.signature)]);
            drawCertificate(this.$refs.canvas, data, logo, signature);
            this.ready = true;
        } catch (error) {
            this.error = error.message;
        }
    },
    download() {
        this.$refs.canvas.toBlob(blob => {
            if (!blob) {
                this.error = 'Gambar tidak dapat dibuat. Coba browser lain.';
                return;
            }
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `sertifikat-${data.program}-${data.number.split('/')[0]}.png`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }, 'image/png');
    },
});
