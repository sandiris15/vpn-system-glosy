import { drawTopology } from './topology-canvas';

export default function topologyEditor(initialItems, revision, updateUrl, canEdit) {
    return {
        items: initialItems,
        revision,
        canEdit,
        selected: null,
        lineMode: false,
        drag: null,
        saving: false,
        message: '',
        savedDesign: JSON.stringify(initialItems),

        initCanvas() {
            this.draw();
            window.addEventListener('beforeunload', (event) => {
                if (this.canEdit && JSON.stringify(this.items) !== this.savedDesign) {
                    event.preventDefault();
                }
            });
        },
        draw(showSelection = true) {
            drawTopology(this.$refs.canvas, this.items, showSelection ? this.selected : null);
        },
        add(type) {
            if (this.items.length >= 200) {
                this.message = 'Maksimal 200 objek per topologi.';
                return;
            }
            this.items.push({ type, x: 500, y: 300, text: type === 'text' ? 'Teks baru' : 'Perangkat' });
            this.selected = this.items.length - 1;
            this.lineMode = false;
            this.draw();
        },
        setText(value) {
            if (this.selected !== null && this.items[this.selected]?.type !== 'line') {
                this.items[this.selected].text = value;
                this.draw();
            }
        },
        removeSelected() {
            if (this.selected === null) return;
            this.items.splice(this.selected, 1);
            this.selected = null;
            this.draw();
        },
        point(event) {
            const rect = this.$refs.canvas.getBoundingClientRect();
            return {
                x: Math.max(0, Math.min(1000, Math.round((event.clientX - rect.left) * 1000 / rect.width))),
                y: Math.max(0, Math.min(600, Math.round((event.clientY - rect.top) * 600 / rect.height))),
            };
        },
        hit(item, point) {
            if (item.type === 'line') {
                const dx = item.x2 - item.x, dy = item.y2 - item.y;
                const length = dx * dx + dy * dy;
                const t = length ? Math.max(0, Math.min(1, ((point.x - item.x) * dx + (point.y - item.y) * dy) / length)) : 0;
                return Math.hypot(point.x - item.x - t * dx, point.y - item.y - t * dy) < 12;
            }
            return Math.abs(point.x - item.x) <= (item.type === 'text' ? 110 : 75)
                && Math.abs(point.y - item.y) <= (item.type === 'text' ? 18 : 35);
        },
        pointerDown(event) {
            if (!this.canEdit || event.button !== 0) return;
            const point = this.point(event);
            if (this.lineMode) {
                if (this.items.length >= 200) {
                    this.message = 'Maksimal 200 objek per topologi.';
                    return;
                }
                this.items.push({ type: 'line', ...point, x2: point.x, y2: point.y });
                this.selected = this.items.length - 1;
                this.drag = { kind: 'line' };
                this.lineMode = false;
            } else {
                this.selected = null;
                for (let i = this.items.length - 1; i >= 0; i--) {
                    if (this.hit(this.items[i], point)) {
                        this.selected = i;
                        this.drag = { kind: 'move', point };
                        break;
                    }
                }
            }
            if (this.drag) this.$refs.canvas.setPointerCapture(event.pointerId);
            this.draw();
        },
        pointerMove(event) {
            if (!this.drag || this.selected === null) return;
            const point = this.point(event);
            const item = this.items[this.selected];
            if (this.drag.kind === 'line') {
                item.x2 = point.x;
                item.y2 = point.y;
            } else {
                let dx = point.x - this.drag.point.x, dy = point.y - this.drag.point.y;
                const xs = item.type === 'line' ? [item.x, item.x2] : [item.x];
                const ys = item.type === 'line' ? [item.y, item.y2] : [item.y];
                dx = Math.max(-Math.min(...xs), Math.min(1000 - Math.max(...xs), dx));
                dy = Math.max(-Math.min(...ys), Math.min(600 - Math.max(...ys), dy));
                item.x += dx;
                item.y += dy;
                if (item.type === 'line') {
                    item.x2 += dx;
                    item.y2 += dy;
                }
                this.drag.point = point;
            }
            this.draw();
        },
        pointerUp() {
            this.drag = null;
        },
        loadExample(example) {
            if (!example || (this.items.length && !confirm('Ganti gambar saat ini dengan contoh?'))) return;
            const box = (x, y, text) => ({ type: 'rectangle', x, y, text });
            const line = (x, y, x2, y2) => ({ type: 'line', x, y, x2, y2 });
            const examples = {
                basic: [line(205, 300, 295, 300), line(455, 300, 545, 300), line(705, 300, 795, 300), box(125, 300, 'Internet'), box(375, 300, 'Router'), box(625, 300, 'Switch'), box(875, 300, 'Pelanggan')],
                dual: [line(225, 200, 425, 300), line(225, 400, 425, 300), line(575, 300, 775, 300), box(150, 200, 'ISP 1'), box(150, 400, 'ISP 2'), box(500, 300, 'Router'), box(850, 300, 'LAN')],
                vpn: [line(225, 300, 425, 300), line(575, 300, 775, 300), box(150, 300, 'Kantor pusat'), box(500, 300, 'VPN'), box(850, 300, 'Kantor cabang')],
            };
            this.items = examples[example];
            this.selected = null;
            this.draw();
        },
        async save() {
            if (this.saving) return;
            this.saving = true;
            this.message = 'Menyimpan...';
            const design = JSON.stringify(this.items);
            try {
                const response = await fetch(updateUrl, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ design: JSON.parse(design), revision: this.revision }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'Penyimpanan gagal.');
                this.revision = result.revision;
                this.savedDesign = design;
                this.message = JSON.stringify(this.items) === design ? 'Perubahan berhasil disimpan.' : 'Tersimpan, tetapi masih ada perubahan baru.';
            } catch (error) {
                this.message = `Tidak dapat menyimpan: ${error.message}`;
            } finally {
                this.saving = false;
            }
        },
        download() {
            this.draw(false);
            const link = document.createElement('a');
            link.download = 'topologi.jpg';
            link.href = this.$refs.canvas.toDataURL('image/jpeg', 0.92);
            link.click();
            this.draw();
        },
    };
}
