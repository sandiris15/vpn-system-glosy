export function drawTopology(canvas, items, selected = null) {
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, 1000, 600);
    items.forEach((item, index) => {
        ctx.strokeStyle = index === selected ? '#db2777' : '#334155';
        ctx.lineWidth = index === selected ? 3 : 2;
        ctx.fillStyle = '#e0f2fe';
        ctx.beginPath();
        if (item.type === 'line') {
            ctx.moveTo(item.x, item.y);
            ctx.lineTo(item.x2, item.y2);
            ctx.stroke();
            return;
        }
        if (item.type === 'rectangle') {
            ctx.rect(item.x - 75, item.y - 35, 150, 70);
            ctx.fill();
            ctx.stroke();
        } else if (item.type === 'ellipse') {
            ctx.ellipse(item.x, item.y, 75, 35, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
        }
        ctx.fillStyle = '#0f172a';
        ctx.font = '16px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(item.text, item.x, item.y, item.type === 'text' ? 220 : 135);
    });
}
