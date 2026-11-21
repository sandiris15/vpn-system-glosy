<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use SimpleVpn\Config;
use SimpleVpn\RouterOsClient;
use SimpleVpn\ScriptExporter;
use SimpleVpn\VpnProvisioner;

require dirname(__DIR__).'/vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = Config::fromEnvironment();
$sessionDirectory = dirname(__DIR__).'/storage/sessions';
if (! is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
chmod($sessionDirectory, 0700);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.save_path', $sessionDirectory);
session_name('simple_vpn_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $config->secureCookie,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Cache-Control: no-store, private');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (! isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf'];
}

function redirectTo(string $path): never
{
    header('Location: '.$path, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * @param  array<string, mixed>  $data
 */
function render(string $template, array $data = []): never
{
    global $config;

    $flashMessage = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $csrf = csrfToken();
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__).'/views/'.$template.'.php';
    $content = (string) ob_get_clean();
    require dirname(__DIR__).'/views/layout.php';
    exit;
}

function authenticated(): bool
{
    return ($_SESSION['authenticated'] ?? false) === true;
}

function requireCsrf(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($token === '' || ! hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        render('error', ['pageTitle' => 'Sesi kedaluwarsa', 'message' => 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.']);
    }
}

function findPppSecret(RouterOsClient $router, string $username): ?array
{
    foreach ($router->collection('ppp/secret') as $secret) {
        if (strcasecmp((string) ($secret['name'] ?? ''), $username) === 0) {
            return $secret;
        }
    }

    return null;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if ($path === '/login' && $method === 'POST') {
    requireCsrf();

    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $lastAttempt = (int) ($_SESSION['login_last_attempt'] ?? 0);
    if ($lastAttempt < time() - 300) {
        $attempts = 0;
    }

    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $valid = $attempts < 5
        && $config->adminUsername !== ''
        && $config->adminPasswordHash !== ''
        && hash_equals($config->adminUsername, $username)
        && password_verify($password, $config->adminPasswordHash);

    if (! $valid) {
        $_SESSION['login_attempts'] = $attempts + 1;
        $_SESSION['login_last_attempt'] = time();
        flash('error', $attempts >= 5
            ? 'Terlalu banyak percobaan login. Tunggu 5 menit sebelum mencoba lagi.'
            : 'Username atau password tidak valid.');
        redirectTo('/login');
    }

    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);
    flash('success', 'Login berhasil.');
    redirectTo('/');
}

if ($path === '/login' && $method === 'GET') {
    if (authenticated()) {
        redirectTo('/');
    }

    render('login', ['pageTitle' => 'Login']);
}

if (! authenticated()) {
    redirectTo('/login');
}

if ($path === '/logout' && $method === 'POST') {
    requireCsrf();
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    flash('success', 'Anda sudah logout.');
    redirectTo('/login');
}

if ($path === '/create' && $method === 'POST') {
    requireCsrf();

    $configurationErrors = $config->errors();
    if ($configurationErrors !== []) {
        flash('error', $configurationErrors[0]);
        redirectTo('/');
    }

    $_SESSION['old'] = [
        'username' => trim((string) ($_POST['username'] ?? '')),
        'protocol' => (string) ($_POST['protocol'] ?? ''),
        'internal_port' => (string) ($_POST['internal_port'] ?? ''),
        'custom_internal_port' => (string) ($_POST['custom_internal_port'] ?? ''),
        'comment' => trim((string) ($_POST['comment'] ?? '')),
    ];
    unset($_SESSION['result'], $_SESSION['nat_script']);

    try {
        $provisioner = new VpnProvisioner(
            new RouterOsClient($config),
            $config,
            dirname(__DIR__).'/storage/provision.lock',
        );
        $result = $provisioner->create($_POST);
        $_SESSION['result'] = $result;
        $_SESSION['nat_script'] = $result['script'];
        unset($_SESSION['old']);
        flash('success', 'PPP Secret berhasil dibuat pada CHR. Akun tidak direplikasi ke node lain.');
    } catch (InvalidArgumentException|RuntimeException $exception) {
        error_log('Simple VPN provisioning failed: '.$exception::class.' '.$exception->getMessage());
        flash('error', $exception->getMessage());
    }

    redirectTo('/');
}

if ($path === '/secrets' && $method === 'GET') {
    try {
        $router = new RouterOsClient($config);
        $secrets = $router->collection('ppp/secret');
        $natRules = $router->collection('ip/firewall/nat');
    } catch (RuntimeException $exception) {
        error_log('Simple VPN PPP list failed: '.$exception::class.' '.$exception->getMessage());
        http_response_code(502);
        render('error', [
            'pageTitle' => 'CHR tidak tersedia',
            'message' => 'Gagal membaca PPP Secret dari CHR. Periksa koneksi API dan coba lagi.',
        ]);
    }

    usort($secrets, fn (array $left, array $right): int => strcasecmp(
        (string) ($left['name'] ?? ''),
        (string) ($right['name'] ?? ''),
    ));

    render('secrets', [
        'pageTitle' => 'Daftar PPP Secret',
        'secrets' => $secrets,
        'natRules' => $natRules,
    ]);
}

if ($path === '/script' && $method === 'GET') {
    $username = trim((string) ($_GET['username'] ?? ''));
    if (! preg_match('/^[A-Za-z0-9._-]{1,64}$/', $username)) {
        http_response_code(404);
        render('error', ['pageTitle' => 'Secret tidak ditemukan', 'message' => 'PPP Secret yang diminta tidak ditemukan.']);
    }

    try {
        $account = findPppSecret(new RouterOsClient($config), $username);
    } catch (RuntimeException $exception) {
        error_log('Simple VPN script lookup failed: '.$exception::class.' '.$exception->getMessage());
        http_response_code(502);
        render('error', [
            'pageTitle' => 'CHR tidak tersedia',
            'message' => 'Gagal membaca PPP Secret dari CHR. Periksa koneksi API dan coba lagi.',
        ]);
    }

    if ($account === null || strtolower((string) ($account['service'] ?? '')) !== 'sstp') {
        http_response_code(404);
        render('error', ['pageTitle' => 'Secret tidak ditemukan', 'message' => 'PPP Secret SSTP yang diminta tidak ditemukan.']);
    }

    if (filter_var($config->publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        http_response_code(422);
        render('error', [
            'pageTitle' => 'Konfigurasi belum lengkap',
            'message' => 'ROUTEROS_PUBLIC_IP harus berisi IPv4 yang valid untuk script SSTP.',
        ]);
    }

    $storedPassword = $account['password'] ?? null;
    $passwordAvailable = is_string($storedPassword)
        && $storedPassword !== ''
        && ! in_array($storedPassword, ['*', '******', '********', '(hidden)'], true);
    $script = (new ScriptExporter())->sstpSpeedtestClient(
        $username,
        $passwordAvailable ? $storedPassword : ScriptExporter::PASSWORD_PLACEHOLDER,
        $config->publicIp,
        $config->localAddress,
    );
    $downloadName = 'sstp-speedtest-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $username).'.rsc';

    render('script-preview', [
        'pageTitle' => 'Preview script',
        'script' => $script,
        'downloadName' => $downloadName,
        'passwordAvailable' => $passwordAvailable,
    ]);
}

if ($path === '/export' && $method === 'GET') {
    $script = $_SESSION['nat_script'] ?? null;
    if (! is_string($script) || $script === '') {
        flash('error', 'Belum ada script DST-NAT untuk diekspor.');
        redirectTo('/');
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="simple-vpn-dstnat.rsc"');
    echo $script;
    exit;
}

if ($path === '/' && $method === 'GET') {
    $result = $_SESSION['result'] ?? null;
    unset($_SESSION['result']);

    render('dashboard', [
        'pageTitle' => 'Provisioning VPN',
        'configurationErrors' => $config->errors(),
        'result' => $result,
        'old' => $_SESSION['old'] ?? [],
    ]);
}

http_response_code(404);
render('error', ['pageTitle' => 'Tidak ditemukan', 'message' => 'Halaman yang diminta tidak tersedia.']);
