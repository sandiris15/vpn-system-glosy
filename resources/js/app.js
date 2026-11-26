import Alpine from 'alpinejs';
import topologyEditor from './topology-editor';
import hotspotPreview from './hotspot-preview';
import { drawTopology } from './topology-canvas';
import internshipCertificate from './internship-certificate';
import internshipCertificatePreview from './internship-certificate-preview';

window.Alpine = Alpine;
Alpine.data('topologyEditor', topologyEditor);
Alpine.data('hotspotPreview', hotspotPreview);
Alpine.data('internshipCertificate', internshipCertificate);
Alpine.data('internshipCertificatePreview', internshipCertificatePreview);
Alpine.data('topologyPreview', items => ({
    init() {
        drawTopology(this.$refs.canvas, items);
    },
}));
Alpine.start();
