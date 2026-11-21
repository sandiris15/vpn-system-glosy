<section class="panel login-panel">
    <p class="eyebrow">Admin portal</p>
    <h1>Login</h1>
    <p class="muted">Masuk untuk membuat PPP Secret dan aturan DST-NAT pada CHR yang dikonfigurasi.</p>
    <?php if ($config->adminUsername === '' || $config->adminPasswordHash === ''): ?>
        <div class="notice notice-error">Isi APP_ADMIN_USERNAME dan APP_ADMIN_PASSWORD_HASH pada file `.env` aplikasi ini untuk mengaktifkan login.</div>
    <?php endif; ?>
    <form method="post" action="/login" class="form-stack">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <label>
            <span>Username admin</span>
            <input name="username" autocomplete="username" required autofocus>
        </label>
        <label>
            <span>Password</span>
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button class="button" type="submit">Login</button>
    </form>
</section>
