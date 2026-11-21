<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(($pageTitle ?? 'Simple VPN').' · '.$config->appName) ?></title>
    <link rel="stylesheet" href="/app.css">
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <a class="brand" href="/"><?= h($config->appName) ?></a>
            <?php if (authenticated()): ?>
                <nav class="top-actions">
                    <a class="button button-quiet" href="/secrets">Daftar PPP Secret</a>
                    <form method="post" action="/logout">
                        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
                        <button class="button button-quiet" type="submit">Logout</button>
                    </form>
                </nav>
            <?php endif; ?>
        </header>
        <?php if (is_array($flashMessage ?? null)): ?>
            <div class="notice notice-<?= h((string) ($flashMessage['type'] ?? 'info')) ?>" role="status">
                <?= h((string) ($flashMessage['message'] ?? '')) ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
        <footer>RouterOS API over HTTPS · Single CHR · No cluster replication</footer>
    </main>
    <script src="/app.js" defer></script>
</body>
</html>
