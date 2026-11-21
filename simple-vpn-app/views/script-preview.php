<section class="panel">
    <p class="eyebrow">SSTP client · Speedtest routing</p>
    <h1>Preview script</h1>
    <?php if ($passwordAvailable): ?>
        <p class="notice notice-warning">Password diambil dari PPP Secret yang sudah ada; script memuat password teks biasa. Script tidak membuat atau mengubah password. Verifikasi sertifikat server dinonaktifkan sesuai pilihan Anda.</p>
    <?php else: ?>
        <p class="notice notice-warning">API CHR tidak mengembalikan password Secret. Ganti placeholder <code>GANTI_DENGAN_PASSWORD_PPP_SECRET</code> dengan password Secret yang sudah ada sebelum impor. Script tidak membuat atau mengubah password. Verifikasi sertifikat server dinonaktifkan sesuai pilihan Anda.</p>
    <?php endif; ?>
    <label for="client-script">Preview script RouterOS (placeholder bisa diedit)</label>
    <textarea id="client-script" class="script-preview" spellcheck="false"><?= h($script) ?></textarea>
    <div class="form-actions">
        <button class="button" type="button" data-copy-target="client-script">Salin script</button>
        <button class="button button-secondary" type="button" data-download-target="client-script" data-download-name="<?= h($downloadName) ?>">Download .rsc</button>
        <span class="copy-status" role="status" aria-live="polite"></span>
    </div>
    <a class="button button-secondary" href="/secrets">Kembali ke daftar PPP Secret</a>
</section>
