<section class="panel">
    <div class="section-heading">
        <div>
            <p class="eyebrow">CHR utama</p>
            <h1>Daftar PPP Secret</h1>
            <p class="muted">Salin detail port forwarding atau buat preview script SSTP dan routing Speedtest.</p>
        </div>
        <a class="button" href="/">Buat PPP Secret</a>
    </div>

    <?php if ($secrets === []): ?>
        <p class="hint">Belum ada PPP Secret pada CHR.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="secret-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Service</th>
                        <th>Local address</th>
                        <th>Remote address</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($secrets as $secret): ?>
                        <?php
                        $username = (string) ($secret['name'] ?? '');
                        $remoteAddress = (string) ($secret['remote-address'] ?? '');
                        $rules = array_values(array_filter(
                            $natRules,
                            fn (array $rule): bool => ($rule['chain'] ?? null) === 'dstnat'
                                && ($rule['action'] ?? null) === 'dst-nat'
                                && (string) ($rule['to-addresses'] ?? '') === $remoteAddress
                                && ! filter_var($rule['disabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                        ));
                        $isDisabled = filter_var($secret['disabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
                        ?>
                        <tr>
                            <td><strong><?= h($username) ?></strong></td>
                            <td><?= h((string) ($secret['service'] ?? 'all')) ?></td>
                            <td><?= h((string) ($secret['local-address'] ?? '—')) ?></td>
                            <td><?= h($remoteAddress !== '' ? $remoteAddress : '—') ?></td>
                            <td><?= $isDisabled ? 'Disabled' : 'Aktif' ?></td>
                            <td class="secret-actions">
                                <?php if ($rules !== []): ?>
                                    <?php foreach ($rules as $rule): ?>
                                        <?php
                                        $dstAddress = (string) ($rule['dst-address'] ?? $config->publicIp);
                                        $dstPort = (string) ($rule['dst-port'] ?? '');
                                        $protocol = strtoupper((string) ($rule['protocol'] ?? ''));
                                        $comment = (string) ($rule['comment'] ?? '');
                                        $copyText = implode("\n", [
                                            'Username: '.$username,
                                            'IP Publik Server: '.$dstAddress,
                                            'Port publik: '.$dstAddress.':'.$dstPort,
                                            'Tujuan internal: '.$remoteAddress.':'.(string) ($rule['to-ports'] ?? ''),
                                            'Protokol: '.$protocol,
                                            'Comment: '.$comment,
                                        ]);
                                        ?>
                                        <div class="nat-action">
                                            <span><?= h($protocol.'/'.$dstPort) ?> · <?= h($comment) ?></span>
                                            <button class="button button-quiet" type="button" data-copy-text="<?= h($copyText) ?>">Salin port</button>
                                            <span class="copy-status" role="status" aria-live="polite"></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="hint">Tanpa DST-NAT</span>
                                <?php endif; ?>

                                <?php if (strtolower((string) ($secret['service'] ?? '')) === 'sstp'): ?>
                                    <a class="button button-secondary" href="/script?username=<?= rawurlencode($username) ?>">Generate script</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
