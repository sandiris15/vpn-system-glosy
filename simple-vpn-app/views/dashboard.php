<?php if ($configurationErrors !== []): ?>
    <section class="notice notice-error">
        <strong>Konfigurasi belum lengkap.</strong>
        <ul>
            <?php foreach ($configurationErrors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
        Isi file `.env` khusus aplikasi ini sebelum menghubungkan ke CHR.
    </section>
<?php endif; ?>

<?php if (is_array($result ?? null)): ?>
    <section class="panel success-panel">
        <p class="eyebrow">PPP Secret berhasil dibuat</p>
        <h2><?= h((string) $result['username']) ?></h2>
        <dl class="result-grid">
            <div><dt>Remote address</dt><dd><?= h((string) $result['remote_address']) ?></dd></div>
            <?php if (is_array($result['nat_rule'] ?? null)): ?>
                <div><dt>Port publik</dt><dd><?= h($result['nat_rule']['dst-address'].':'.$result['nat_rule']['dst-port']) ?></dd></div>
                <div><dt>Tujuan</dt><dd><?= h($result['nat_rule']['to-addresses'].':'.$result['nat_rule']['to-ports']) ?></dd></div>
                <div><dt>Protokol</dt><dd><?= h(strtoupper($result['nat_rule']['protocol'])) ?></dd></div>
                <div><dt>Comment</dt><dd><?= h($result['nat_rule']['comment']) ?></dd></div>
            <?php else: ?>
                <div><dt>DST-NAT</dt><dd>Tidak dibuat</dd></div>
            <?php endif; ?>
        </dl>
        <?php if (is_string($result['script'] ?? null) && $result['script'] !== ''): ?>
            <a class="button button-secondary" href="/export">Download script DST-NAT (.rsc)</a>
            <p class="hint">Script hanya berisi rule DST-NAT. PPP Secret sudah dibuat langsung pada CHR.</p>
        <?php endif; ?>
        <a class="button button-secondary" href="/secrets">Lihat daftar PPP Secret</a>
    </section>

    <?php if (is_array($result['nat_rule'] ?? null)): ?>
        <?php
        $natRule = $result['nat_rule'];
        $copyText = implode("\n", [
            'Username: '.$result['username'],
            'IP Publik Server: '.$natRule['dst-address'],
            'Port publik: '.$natRule['dst-address'].':'.$natRule['dst-port'],
            'Tujuan internal: '.$result['remote_address'].':'.$natRule['to-ports'],
            'Protokol: '.strtoupper($natRule['protocol']),
            'Comment: '.$natRule['comment'],
        ]);
        ?>
    <?php endif; ?>

    <dialog id="success-actions" class="success-dialog" aria-labelledby="success-actions-title">
        <div class="dialog-card">
            <p class="eyebrow">Berhasil dibuat</p>
            <h2 id="success-actions-title">Pilih tindakan</h2>
            <p class="muted">PPP Secret <strong><?= h((string) $result['username']) ?></strong> sudah dibuat di CHR.</p>
            <div class="dialog-actions">
                <?php if (is_array($result['nat_rule'] ?? null)): ?>
                    <button class="button" type="button" data-copy-text="<?= h($copyText) ?>">Salin port</button>
                    <span class="copy-status" role="status" aria-live="polite"></span>
                <?php else: ?>
                    <p class="hint">Akun ini tidak memiliki DST-NAT untuk disalin.</p>
                <?php endif; ?>
                <a class="button button-secondary" href="/script?username=<?= rawurlencode((string) $result['username']) ?>">Generate script Speedtest</a>
                <form method="dialog">
                    <button class="button button-quiet" type="submit">Tutup</button>
                </form>
            </div>
        </div>
    </dialog>
<?php endif; ?>

<section class="panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">CHR utama saja</p>
            <h1>Buat akun SSTP</h1>
            <p class="muted">Username duplikat ditolak. DST-NAT bersifat opsional dan port publik diperiksa sebelum dibuat.</p>
        </div>
        <span class="chip"><?= h($config->publicIp !== '' ? $config->publicIp : 'IP belum diatur') ?></span>
    </div>
    <form method="post" action="/create" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <label>
            <span>Username VPN</span>
            <input name="username" maxlength="64" pattern="[A-Za-z0-9._\-]+" required value="<?= h((string) ($old['username'] ?? '')) ?>" autocomplete="off">
        </label>
        <label>
            <span>Password VPN</span>
            <input name="password" type="password" minlength="8" maxlength="255" required autocomplete="new-password">
        </label>
        <label>
            <span>Protokol DST-NAT</span>
            <select id="protocol" name="protocol">
                <option value="">Tanpa DST-NAT</option>
                <option value="tcp" <?= ($old['protocol'] ?? '') === 'tcp' ? 'selected' : '' ?>>TCP</option>
                <option value="udp" <?= ($old['protocol'] ?? '') === 'udp' ? 'selected' : '' ?>>UDP</option>
            </select>
        </label>
        <div id="nat-fields" class="form-grid" hidden>
            <label>
                <span>Layanan internal</span>
                <select id="internal_port" name="internal_port" disabled>
                    <option value="">Pilih layanan</option>
                    <option value="8291" <?= ($old['internal_port'] ?? '') === '8291' ? 'selected' : '' ?>>Winbox · 8291</option>
                    <option value="8728" <?= ($old['internal_port'] ?? '') === '8728' ? 'selected' : '' ?>>MikroTik API · 8728</option>
                    <option value="80" <?= ($old['internal_port'] ?? '') === '80' ? 'selected' : '' ?>>WebFig · 80</option>
                    <option value="custom" <?= ($old['internal_port'] ?? '') === 'custom' ? 'selected' : '' ?>>Port manual</option>
                </select>
            </label>
            <label id="custom-port-field" hidden>
                <span>Port internal manual</span>
                <input id="custom_internal_port" name="custom_internal_port" type="number" min="1" max="65535" value="<?= h((string) ($old['custom_internal_port'] ?? '')) ?>" placeholder="Contoh: 8080" disabled>
            </label>
            <label id="manual-comment-field" hidden>
                <span>Comment DST-NAT manual</span>
                <input id="comment" name="comment" maxlength="160" value="<?= h((string) ($old['comment'] ?? '')) ?>" placeholder="Masukkan comment" disabled>
            </label>
            <p id="auto-comment-preview" class="hint" hidden></p>
        </div>
        <div class="form-actions">
            <p class="hint">Port publik dialokasikan otomatis. Comment untuk layanan standar dibuat dari username dan layanan yang dipilih.</p>
            <button class="button" type="submit" <?= $configurationErrors !== [] ? 'disabled' : '' ?>>Buat PPP Secret</button>
        </div>
    </form>
</section>
