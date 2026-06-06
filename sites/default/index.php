<?php
/**
 * Docker Local Webserver — Dashboard & Status Page
 * URL: http://localhost:8000
 */

session_start();
require_once __DIR__ . '/npm_api.php';

// ---- Helper Functions ----
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return;
    $files = scandir($dirPath);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $path = "$dirPath/$file";
            is_dir($path) ? deleteDir($path) : unlink($path);
        }
    }
    rmdir($dirPath);
}

function checkService($host, $port, $timeout = 2) {
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

function checkRedis() {
    try {
        $redis = new Redis();
        $redis->connect('redis', 6379, 2);
        $info = $redis->info();
        $redis->close();
        return ['status' => true, 'version' => $info['redis_version'] ?? 'Unknown', 'memory' => $info['used_memory_human'] ?? 'N/A'];
    } catch (Exception $e) {
        return ['status' => false, 'version' => 'N/A', 'memory' => 'N/A'];
    }
}

function checkMariaDB() {
    try {
        $pdo = new PDO(
            'mysql:host=mariadb;port=3306',
            getenv('MYSQL_USER') ?: 'homestead',
            getenv('MYSQL_PASSWORD') ?: 'secret',
            [PDO::ATTR_TIMEOUT => 2]
        );
        $version = $pdo->query("SELECT VERSION()")->fetchColumn();
        $databases = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
        return ['status' => true, 'version' => $version, 'databases' => $databases];
    } catch (Exception $e) {
        return ['status' => false, 'version' => 'N/A', 'databases' => []];
    }
}

// ---- Handle POST Actions ----
$action = $_POST['action'] ?? null;
if ($action === 'create') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? '');
    $phpVer = preg_replace('/[^0-9]/', '', $_POST['php_version'] ?? '84');
    $type = $_POST['type'] ?? 'standard';
    
    if ($name) {
        $domain = $name . '.test';
        $siteDir = "/var/www/html/$domain";
        $confDir = "/etc/nginx/conf.d";
        $templatePath = "$confDir/_template.conf.example";
        $confPath = "$confDir/$domain.conf";
        
        if (!is_dir($siteDir)) {
            // 1. Create Directories
            mkdir($siteDir, 0755, true);
            if ($type === 'laravel') {
                mkdir("$siteDir/public", 0755, true);
                $indexPath = "$siteDir/public/index.php";
                $rootPath = "/var/www/html/$domain/public";
            } else {
                $indexPath = "$siteDir/index.php";
                $rootPath = "/var/www/html/$domain";
            }
            
            // Create beautiful default index.php
            $html = <<<'EOD'
<?php
$phpVersion = phpversion();
$server = $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx';
$domain = '{{DOMAIN}}';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?= $domain ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { background: '#09090E', surface: '#12121C', border: '#232336' } } } }
    </script>
    <style type="text/tailwindcss">
        @layer base { body { @apply bg-background text-slate-100 antialiased min-h-screen relative overflow-hidden flex items-center justify-center; } }
        .glass-panel { @apply bg-surface/70 backdrop-blur-md border border-border rounded-3xl p-10 max-w-2xl w-full mx-4 shadow-[0_20px_50px_rgba(0,0,0,0.5)]; }
        .glow-bg { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, rgba(0,0,0,0) 70%); top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: -1; pointer-events: none; }
        .text-gradient { @apply bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400; }
    </style>
</head>
<body>
    <div class="glow-bg"></div>
    <div class="glass-panel text-center relative z-10">
        <div class="inline-flex items-center justify-center p-4 mb-6 rounded-3xl bg-indigo-500/10 border border-indigo-500/20">
            <iconify-icon icon="mdi:rocket-launch" class="text-4xl text-indigo-400"></iconify-icon>
        </div>
        <h1 class="text-4xl font-extrabold mb-4">It Works! <br><span class="text-gradient"><?= $domain ?></span></h1>
        <p class="text-slate-400 text-lg mb-8 font-light">Your new project is successfully running on Docker Webserver.</p>
        
        <div class="grid grid-cols-2 gap-4 mb-8 text-left">
            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">PHP Engine</p>
                <p class="text-lg font-semibold text-slate-200 flex items-center gap-2">
                    <iconify-icon icon="logos:php"></iconify-icon> <?= $phpVersion ?>
                </p>
            </div>
            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-800">
                <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Web Server</p>
                <p class="text-lg font-semibold text-slate-200 flex items-center gap-2">
                    <iconify-icon icon="logos:nginx"></iconify-icon> Nginx
                </p>
            </div>
        </div>
        
        <div class="flex justify-center gap-4">
            <a href="http://localhost:8000" class="px-6 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-sm font-medium transition-colors flex items-center gap-2">
                <iconify-icon icon="mdi:arrow-left"></iconify-icon> Dashboard
            </a>
            <a href="/info.php" target="_blank" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-sm font-medium transition-colors shadow-lg shadow-indigo-500/20 flex items-center gap-2">
                <iconify-icon icon="mdi:information-outline"></iconify-icon> phpinfo()
            </a>
        </div>
    </div>
</body>
</html>
EOD;
            
            $html = str_replace('{{DOMAIN}}', $domain, $html);
            file_put_contents($indexPath, $html);
            file_put_contents(dirname($indexPath) . '/info.php', "<?php phpinfo();");
            
            // 2. Create Nginx Config
            if (file_exists($templatePath)) {
                $template = file_get_contents($templatePath);
                $config = str_replace(
                    ['{{DOMAIN}}', '{{PHP_VERSION}}', '{{ROOT_PATH}}'],
                    [$domain, "php$phpVer", $rootPath],
                    $template
                );
                
                if (@file_put_contents($confPath, $config) !== false) {
                    // Try NPM API integration
                    $npmMsg = "";
                    if (file_exists('/var/www/html/config.json')) {
                        $cfg = json_decode(file_get_contents('/var/www/html/config.json'), true);
                        if (!empty($cfg['npm_email']) && !empty($cfg['npm_password'])) {
                            $res = callNpmApi('POST', '/tokens', ['identity' => $cfg['npm_email'], 'secret' => $cfg['npm_password']]);
                            if ($res['status'] === 200 && isset($res['data']['token'])) {
                                $token = $res['data']['token'];
                                
                                // Generate specific certificate for this domain using mkcert
                                $certId = 0;
                                $certPath = "/tmp/$domain.pem";
                                $keyPath = "/tmp/$domain-key.pem";
                                exec("CAROOT=/var/www/ssl/ca /var/www/ssl/mkcert -cert-file $certPath -key-file $keyPath $domain", $output, $ret);
                                
                                if ($ret === 0 && file_exists($certPath)) {
                                    $uploadRes = uploadNpmCertificate($token, "$domain SSL", $certPath, $keyPath);
                                    if ($uploadRes['status'] === 200 || $uploadRes['status'] === 201) {
                                        $certId = $uploadRes['data']['id'];
                                    }
                                    @unlink($certPath);
                                    @unlink($keyPath);
                                }
                                
                                // Create Proxy Host
                                $payload = [
                                    'domain_names' => [$domain],
                                    'forward_scheme' => 'http',
                                    'forward_host' => 'nginx',
                                    'forward_port' => 8000,
                                    'certificate_id' => $certId,
                                    'ssl_forced' => ($certId > 0),
                                    'meta' => ['letsencrypt_agree' => false, 'dns_challenge' => false],
                                    'advanced_config' => '',
                                    'locations' => [],
                                    'block_exploits' => false,
                                    'caching_enabled' => false,
                                    'allow_websocket_upgrade' => true,
                                    'http2_support' => false,
                                    'hsts_enabled' => false,
                                    'hsts_subdomains' => false
                                ];
                                $proxyRes = callNpmApi('POST', '/nginx/proxy-hosts', $payload, $token);
                                if ($proxyRes['status'] === 200 || $proxyRes['status'] === 201) {
                                    $npmMsg = " & proxy host created in NPM!";
                                } else {
                                    $npmMsg = " (NPM API error: " . ($proxyRes['data']['error']['message'] ?? 'Unknown') . ")";
                                }
                            } else {
                                $npmMsg = " (NPM Auth Failed! Check Settings.)";
                            }
                        }
                    }

                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'title' => 'Project Created Successfully! 🎉',
                        'message' => "Folder <strong>$domain</strong> and Nginx config generated" . $npmMsg . ".",
                        'command' => "Add-Content -Path \$env:SystemRoot\\System32\\drivers\\etc\\hosts -Value \"`n127.0.0.1`t$domain\" ; docker restart nginx"
                    ];
                } else {
                    $_SESSION['flash'] = ['type' => 'error', 'message' => "Failed to write Nginx config. Are volumes mounted?"];
                }
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => "Nginx template not found in $templatePath!"];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Project folder already exists!"];
        }
    }
    header('Location: /');
    exit;
}

if ($action === 'delete') {
    $domain = preg_replace('/[^a-zA-Z0-9_.-]/', '', $_POST['domain'] ?? '');
    // Ensure we don't delete default or things outside .test
    if ($domain && strpos($domain, '.test') !== false && $domain !== 'default.test') {
        $siteDir = "/var/www/html/$domain";
        $confPath = "/etc/nginx/conf.d/$domain.conf";
        
        deleteDir($siteDir);
        if (file_exists($confPath)) {
            @unlink($confPath);
        }

        // NPM API Delete
        if (file_exists('/var/www/html/config.json')) {
            $cfg = json_decode(file_get_contents('/var/www/html/config.json'), true);
            if (!empty($cfg['npm_email'])) {
                $res = callNpmApi('POST', '/tokens', ['identity' => $cfg['npm_email'], 'secret' => $cfg['npm_password']]);
                if ($res['status'] === 200) {
                    $token = $res['data']['token'];
                    $hosts = callNpmApi('GET', '/nginx/proxy-hosts', null, $token);
                    if ($hosts['status'] === 200) {
                        foreach ($hosts['data'] as $host) {
                            if (in_array($domain, $host['domain_names'])) {
                                $certId = $host['certificate_id'];
                                callNpmApi('DELETE', '/nginx/proxy-hosts/' . $host['id'], null, $token);
                                if ($certId > 0) {
                                    callNpmApi('DELETE', '/nginx/certificates/' . $certId, null, $token);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $_SESSION['flash'] = [
            'type' => 'success',
            'title' => 'Project Deleted 🗑️',
            'message' => "Project <strong>$domain</strong> and its configuration have been removed.",
            'command' => "docker restart nginx"
        ];
    }
    header('Location: /');
    exit;
}

// ---- Gather Data ----
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$phpVersion = phpversion();
$redis = checkRedis();
$mariadb = checkMariaDB();
$mailpit = checkService('mailpit', 8025);
$xdebugLoaded = extension_loaded('xdebug');

// Get list of sites
$sitesDir = '/var/www/html';
$sites = [];
foreach (scandir($sitesDir) as $dir) {
    if ($dir === '.' || $dir === '..' || $dir === 'default' || !is_dir("$sitesDir/$dir")) continue;
    $sites[] = $dir;
}
sort($sites);
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Docker Webserver Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Iconify -->
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        background: '#09090E',
                        surface: '#12121C',
                        surfaceHover: '#1A1A28',
                        border: '#232336'
                    }
                }
            }
        }
        
        function toggleModal(id) {
            const modal = document.getElementById(id);
            if (modal.classList.contains('hidden')) {
                modal.classList.remove('hidden');
                setTimeout(() => modal.classList.remove('opacity-0'), 10);
            } else {
                modal.classList.add('opacity-0');
                setTimeout(() => modal.classList.add('hidden'), 300);
            }
        }
        
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<iconify-icon icon="mdi:check"></iconify-icon> Copied!';
                btn.classList.add('text-emerald-400');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('text-emerald-400');
                }, 2000);
            });
        }
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body { 
                @apply bg-background text-slate-100 antialiased min-h-screen relative overflow-x-hidden selection:bg-indigo-500/30;
            }
        }
        @layer components {
            .glass-panel {
                @apply bg-surface/70 backdrop-blur-md border border-border rounded-2xl transition-all duration-300;
            }
            .glass-panel-hover:hover {
                @apply bg-surfaceHover/90 border-indigo-500/40 -translate-y-1 shadow-[0_12px_30px_-10px_rgba(0,0,0,0.6),0_0_20px_rgba(99,102,241,0.15)];
            }
            .text-gradient {
                @apply bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400;
            }
            .form-input {
                @apply w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors;
            }
        }
        
        .glow-bg {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, rgba(0,0,0,0) 60%);
            top: -400px;
            left: 50%;
            transform: translateX(-50%);
            z-index: -1;
            pointer-events: none;
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
        
        /* Modal Transition */
        .modal-transition { transition: opacity 0.3s ease; }
    </style>
</head>
<body>
    <div class="glow-bg"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <?php if ($flash): ?>
            <div class="mb-8 p-6 rounded-2xl border <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 border-emerald-500/20' : 'bg-red-500/10 border-red-500/20' ?> relative">
                <h3 class="text-lg font-bold <?= $flash['type'] === 'success' ? 'text-emerald-400' : 'text-red-400' ?> flex items-center gap-2 mb-2">
                    <iconify-icon icon="<?= $flash['type'] === 'success' ? 'mdi:check-circle' : 'mdi:alert-circle' ?>"></iconify-icon>
                    <?= $flash['title'] ?? ($flash['type'] === 'success' ? 'Success' : 'Error') ?>
                </h3>
                <p class="text-slate-300"><?= $flash['message'] ?></p>
                
                <?php if (!empty($flash['command'])): ?>
                <div class="mt-4 p-4 bg-slate-950 rounded-xl border border-slate-800 flex items-center justify-between group">
                    <code class="text-sm text-pink-400 font-mono select-all overflow-x-auto custom-scrollbar pr-4"><?= htmlspecialchars($flash['command']) ?></code>
                    <button onclick="copyToClipboard(`<?= htmlspecialchars($flash['command']) ?>`, this)" class="flex-shrink-0 text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1">
                        <iconify-icon icon="mdi:content-copy"></iconify-icon> Copy
                    </button>
                </div>
                <p class="text-xs text-slate-500 mt-2"><iconify-icon icon="mdi:information-outline"></iconify-icon> <strong>Action Required:</strong> Please copy and run the command above in your Windows PowerShell (as Administrator) to finalize the changes.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Header Section -->
        <header class="text-center mb-16 relative">
            <div class="inline-flex items-center justify-center p-4 mb-6 rounded-3xl bg-indigo-500/10 border border-indigo-500/20 shadow-[0_0_30px_rgba(99,102,241,0.2)]">
                <iconify-icon icon="logos:docker" width="40"></iconify-icon>
            </div>
            <h1 class="text-5xl font-extrabold tracking-tight mb-4">
                <span class="text-gradient">Local Dev</span> Environment
            </h1>
            <p class="text-lg text-slate-400 max-w-2xl mx-auto font-light">
                Professional Docker-based local webserver. Running 17 optimized containers with seamless GUI management.
            </p>
            
            <div class="flex flex-wrap justify-center gap-3 mt-8">
                <span class="px-4 py-1.5 rounded-full text-sm font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center gap-2">
                    <iconify-icon icon="logos:php"></iconify-icon> PHP <?= $phpVersion ?>
                </span>
                <span class="px-4 py-1.5 rounded-full text-sm font-medium bg-sky-500/10 text-sky-400 border border-sky-500/20 flex items-center gap-2">
                    <iconify-icon icon="logos:mariadb-icon"></iconify-icon> MariaDB <?= $mariadb['version'] ?: 'N/A' ?>
                </span>
                <span class="px-4 py-1.5 rounded-full text-sm font-medium bg-red-500/10 text-red-400 border border-red-500/20 flex items-center gap-2">
                    <iconify-icon icon="logos:redis"></iconify-icon> Redis <?= $redis['version'] ?: 'N/A' ?>
                </span>
            </div>
        </header>

        <!-- Status Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- PHP -->
            <div class="glass-panel p-6 flex items-start space-x-4">
                <div class="p-3.5 rounded-xl bg-blue-500/10 text-blue-400">
                    <iconify-icon icon="mdi:language-php" width="28"></iconify-icon>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-400">PHP Engine</h3>
                    <div class="mt-1 flex items-center space-x-2">
                        <span class="text-xl font-semibold text-white"><?= $phpVersion ?></span>
                        <span class="flex h-2.5 w-2.5 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- MariaDB -->
            <div class="glass-panel p-6 flex items-start space-x-4">
                <div class="p-3.5 rounded-xl bg-sky-500/10 text-sky-400">
                    <iconify-icon icon="mdi:database" width="28"></iconify-icon>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-400">Database</h3>
                    <div class="mt-1 flex items-center space-x-2">
                        <span class="text-xl font-semibold text-white">MariaDB</span>
                        <?php if ($mariadb['status']): ?>
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                        <?php else: ?>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Redis -->
            <div class="glass-panel p-6 flex items-start space-x-4">
                <div class="p-3.5 rounded-xl bg-red-500/10 text-red-400">
                    <iconify-icon icon="mdi:flash" width="28"></iconify-icon>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-400">Cache Layer</h3>
                    <div class="mt-1 flex items-center space-x-2">
                        <span class="text-xl font-semibold text-white">Redis</span>
                        <?php if ($redis['status']): ?>
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                        <?php else: ?>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mailpit -->
            <div class="glass-panel p-6 flex items-start space-x-4">
                <div class="p-3.5 rounded-xl bg-amber-500/10 text-amber-400">
                    <iconify-icon icon="mdi:email-fast-outline" width="28"></iconify-icon>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-slate-400">Mail Catcher</h3>
                    <div class="mt-1 flex items-center space-x-2">
                        <span class="text-xl font-semibold text-white">Mailpit</span>
                        <?php if ($mailpit): ?>
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                        <?php else: ?>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Left Column: Panels & Sites -->
            <div class="lg:col-span-2 space-y-8">
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                        <iconify-icon icon="mdi:view-dashboard-outline" class="text-indigo-400 text-2xl"></iconify-icon>
                        Management Panels
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php
                        $panels = [
                            ['name' => 'Nginx Proxy Manager', 'desc' => 'Manage Domains & SSL', 'url' => 'http://localhost:81', 'icon' => 'mdi:shield-lock-outline', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10'],
                            ['name' => 'Dockge', 'desc' => 'Docker Stack Manager', 'url' => 'http://localhost:5001', 'icon' => 'mdi:docker', 'color' => 'text-blue-400', 'bg' => 'bg-blue-500/10'],
                            ['name' => 'phpMyAdmin', 'desc' => 'Database Administration', 'url' => 'http://localhost:8080', 'icon' => 'mdi:database-search-outline', 'color' => 'text-sky-400', 'bg' => 'bg-sky-500/10'],
                            ['name' => 'RedisInsight', 'desc' => 'Redis Visual Manager', 'url' => 'http://localhost:8001', 'icon' => 'mdi:database-eye-outline', 'color' => 'text-red-400', 'bg' => 'bg-red-500/10'],
                            ['name' => 'Dozzle', 'desc' => 'Real-time Container Logs', 'url' => 'http://localhost:9999', 'icon' => 'mdi:text-box-search-outline', 'color' => 'text-indigo-400', 'bg' => 'bg-indigo-500/10'],
                            ['name' => 'Mailpit', 'desc' => 'Email Testing Interface', 'url' => 'http://localhost:8025', 'icon' => 'mdi:email-outline', 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10']
                        ];
                        foreach ($panels as $panel): ?>
                        <a href="<?= $panel['url'] ?>" target="_blank" class="glass-panel glass-panel-hover p-5 group flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="p-3.5 rounded-xl <?= $panel['bg'] ?> <?= $panel['color'] ?>">
                                    <iconify-icon icon="<?= $panel['icon'] ?>" width="24"></iconify-icon>
                                </div>
                                <div>
                                    <h3 class="text-base font-semibold text-white group-hover:text-indigo-400 transition-colors"><?= $panel['name'] ?></h3>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= $panel['desc'] ?></p>
                                </div>
                            </div>
                            <iconify-icon icon="mdi:chevron-right" class="text-slate-500 text-xl group-hover:text-indigo-400 transform group-hover:translate-x-1 transition-all"></iconify-icon>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Projects List -->
                <div>
                    <div class="flex items-center justify-between mb-4 mt-8">
                        <h2 class="text-xl font-semibold text-white flex items-center gap-2">
                            <iconify-icon icon="mdi:folder-star-outline" class="text-pink-400 text-2xl"></iconify-icon>
                            Your Sites (<?= count($sites) ?>)
                        </h2>
                        <div class="flex items-center gap-2">
                            <button onclick="toggleModal('settingsModal')" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-lg transition-colors border border-slate-700 flex items-center gap-2 tooltip" title="NPM API Settings">
                                <iconify-icon icon="mdi:cog-outline"></iconify-icon>
                            </button>
                            <button onclick="toggleModal('createModal')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2 shadow-lg shadow-indigo-500/20">
                                <iconify-icon icon="mdi:plus"></iconify-icon> New Project
                            </button>
                        </div>
                    </div>
                    
                    <div class="glass-panel p-2">
                        <?php if (empty($sites)): ?>
                            <div class="p-8 text-center">
                                <iconify-icon icon="mdi:folder-alert-outline" class="text-slate-600 text-5xl mb-4"></iconify-icon>
                                <p class="text-slate-400 mb-4">No projects found yet.</p>
                                <button onclick="toggleModal('createModal')" class="bg-indigo-500/20 text-indigo-400 hover:bg-indigo-500/30 px-5 py-2 rounded-lg text-sm font-medium transition-colors border border-indigo-500/30">
                                    Create your first project
                                </button>
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-slate-800/50">
                                <?php foreach ($sites as $site): ?>
                                <li class="group">
                                    <div class="flex items-center justify-between p-2 hover:bg-slate-800/40 rounded-xl transition-colors">
                                        <a href="http://<?= htmlspecialchars($site) ?>" target="_blank" class="flex-1 flex items-center space-x-3 p-2">
                                            <iconify-icon icon="mdi:web" class="text-slate-500 text-xl group-hover:text-pink-400 transition-colors"></iconify-icon>
                                            <span class="font-medium text-slate-300 group-hover:text-white transition-colors"><?= htmlspecialchars($site) ?></span>
                                            <span class="text-xs text-indigo-400 opacity-0 group-hover:opacity-100 transform translate-x-2 group-hover:translate-x-0 transition-all flex items-center gap-1">
                                                Open <iconify-icon icon="mdi:open-in-new"></iconify-icon>
                                            </span>
                                        </a>
                                        <form method="POST" action="/" class="ml-4 pr-2" onsubmit="return confirm('Are you sure you want to completely delete <?= htmlspecialchars($site) ?> and all its files?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="domain" value="<?= htmlspecialchars($site) ?>">
                                            <button type="submit" class="text-slate-500 hover:text-red-400 p-2 rounded-lg hover:bg-red-500/10 transition-colors tooltip" title="Delete Site">
                                                <iconify-icon icon="mdi:trash-can-outline" class="text-lg"></iconify-icon>
                                            </button>
                                        </form>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Settings & DBs -->
            <div class="space-y-8">
                <!-- PHP Env -->
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                        <iconify-icon icon="mdi:cog-outline" class="text-emerald-400 text-2xl"></iconify-icon>
                        Environment
                    </h2>
                    <div class="glass-panel p-5">
                        <ul class="space-y-4">
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">PHP Version</span>
                                <span class="font-semibold text-slate-200"><?= $phpVersion ?></span>
                            </li>
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Xdebug</span>
                                <?php if ($xdebugLoaded): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Enabled</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-800 text-slate-400 border border-slate-700">Disabled</span>
                                <?php endif; ?>
                            </li>
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Memory Limit</span>
                                <span class="font-mono text-slate-300 bg-slate-900 px-2 py-1 rounded-md border border-slate-800/50"><?= ini_get('memory_limit') ?></span>
                            </li>
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Upload Max</span>
                                <span class="font-mono text-slate-300 bg-slate-900 px-2 py-1 rounded-md border border-slate-800/50"><?= ini_get('upload_max_filesize') ?></span>
                            </li>
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">Max Execution</span>
                                <span class="font-mono text-slate-300 bg-slate-900 px-2 py-1 rounded-md border border-slate-800/50"><?= ini_get('max_execution_time') ?>s</span>
                            </li>
                            <li class="flex justify-between items-center text-sm">
                                <span class="text-slate-400">OPcache</span>
                                <?php if (function_exists('opcache_get_status') && opcache_get_status()): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Enabled</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-800 text-slate-400 border border-slate-700">Disabled</span>
                                <?php endif; ?>
                            </li>
                            <li class="pt-4 border-t border-slate-800/50">
                                <a href="/info.php" target="_blank" class="w-full flex justify-center items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-xl text-indigo-400 bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 transition-colors">
                                    View Full phpinfo() <iconify-icon icon="mdi:open-in-new"></iconify-icon>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Databases List -->
                <?php if ($mariadb['status'] && !empty($mariadb['databases'])): ?>
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4 flex items-center gap-2">
                        <iconify-icon icon="mdi:database-outline" class="text-sky-400 text-2xl"></iconify-icon>
                        Databases
                    </h2>
                    <div class="glass-panel p-2 max-h-[300px] overflow-y-auto custom-scrollbar">
                        <ul class="divide-y divide-slate-800/50">
                            <?php foreach ($mariadb['databases'] as $db): ?>
                            <li class="group">
                                <a href="http://localhost:8080/?db=<?= urlencode($db) ?>" target="_blank" class="flex items-center justify-between p-3 hover:bg-slate-800/40 rounded-xl transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <iconify-icon icon="mdi:database" class="text-slate-500 group-hover:text-sky-400"></iconify-icon>
                                        <span class="text-sm font-medium text-slate-300 group-hover:text-white transition-colors"><?= htmlspecialchars($db) ?></span>
                                    </div>
                                    <iconify-icon icon="mdi:chevron-right" class="text-slate-600 text-lg group-hover:text-sky-400 opacity-0 group-hover:opacity-100 transition-all"></iconify-icon>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <!-- Footer -->
        <footer class="mt-16 pt-8 border-t border-slate-800/50 flex flex-col md:flex-row items-center justify-between text-slate-500 text-sm">
            <p>&copy; <?= date('Y') ?> Docker Local Webserver. All rights reserved.</p>
            <p class="mt-2 md:mt-0 flex items-center">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-2 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                System Time: <?= date('Y-m-d H:i:s') ?> (<?= date_default_timezone_get() ?>)
            </p>
        </footer>
        
    </div>
    
    <!-- Create Site Modal -->
    <div id="createModal" class="fixed inset-0 z-50 hidden opacity-0 modal-transition">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="toggleModal('createModal')"></div>
        
        <!-- Modal Content -->
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative bg-surface border border-slate-700 rounded-2xl text-left overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] transform transition-all sm:my-8 sm:max-w-lg w-full">
                <div class="px-6 py-5 border-b border-slate-700/50 flex justify-between items-center bg-slate-900/50">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <iconify-icon icon="mdi:plus-box-multiple-outline" class="text-indigo-400"></iconify-icon>
                        Create New Project
                    </h3>
                    <button type="button" onclick="toggleModal('createModal')" class="text-slate-400 hover:text-white transition-colors">
                        <iconify-icon icon="mdi:close" class="text-xl"></iconify-icon>
                    </button>
                </div>
                <form method="POST" action="/">
                    <input type="hidden" name="action" value="create">
                    <div class="px-6 py-6 space-y-5">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Project Name</label>
                            <div class="relative flex items-center">
                                <input type="text" name="name" required placeholder="myawesomeapp" pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, dashes and underscores" class="form-input rounded-r-none border-r-0 focus:ring-0 focus:border-indigo-500 z-10 w-full" autofocus>
                                <span class="bg-slate-800 border border-slate-700 border-l-0 text-slate-400 px-4 py-2.5 rounded-r-lg text-sm font-medium">.test</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1.5">This will be your local domain (e.g. myawesomeapp.test)</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">PHP Version</label>
                            <div class="relative">
                                <select name="php_version" class="form-input appearance-none">
                                    <option value="84">PHP 8.4 (Latest)</option>
                                    <option value="83">PHP 8.3</option>
                                    <option value="82">PHP 8.2</option>
                                    <option value="81">PHP 8.1</option>
                                    <option value="74">PHP 7.4 (Legacy)</option>
                                </select>
                                <iconify-icon icon="mdi:chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></iconify-icon>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Project Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="type" value="standard" class="peer sr-only" checked>
                                    <div class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-3 hover:bg-slate-800 peer-checked:border-indigo-500 peer-checked:ring-1 peer-checked:ring-indigo-500 transition-all">
                                        <div class="flex items-center gap-2">
                                            <iconify-icon icon="mdi:file-code-outline" class="text-slate-400 peer-checked:text-indigo-400"></iconify-icon>
                                            <span class="text-sm font-medium text-slate-300">Standard / Native</span>
                                        </div>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="type" value="laravel" class="peer sr-only">
                                    <div class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-3 hover:bg-slate-800 peer-checked:border-indigo-500 peer-checked:ring-1 peer-checked:ring-indigo-500 transition-all">
                                        <div class="flex items-center gap-2">
                                            <iconify-icon icon="logos:laravel" class="grayscale peer-checked:grayscale-0"></iconify-icon>
                                            <span class="text-sm font-medium text-slate-300">Laravel</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                    </div>
                    <div class="px-6 py-4 bg-slate-900/80 border-t border-slate-700/50 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" onclick="toggleModal('createModal')" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-indigo-500/20 flex items-center gap-2">
                            <iconify-icon icon="mdi:rocket-launch-outline"></iconify-icon> Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div id="settingsModal" class="fixed inset-0 z-50 hidden opacity-0 modal-transition">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="toggleModal('settingsModal')"></div>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative bg-surface border border-slate-700 rounded-2xl text-left overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] transform transition-all sm:my-8 sm:max-w-lg w-full">
                <div class="px-6 py-5 border-b border-slate-700/50 flex justify-between items-center bg-slate-900/50">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <iconify-icon icon="mdi:cog-outline" class="text-emerald-400"></iconify-icon>
                        NPM API Settings
                    </h3>
                    <button type="button" onclick="toggleModal('settingsModal')" class="text-slate-400 hover:text-white transition-colors">
                        <iconify-icon icon="mdi:close" class="text-xl"></iconify-icon>
                    </button>
                </div>
                <?php
                $cfg = file_exists('/var/www/html/config.json') ? json_decode(file_get_contents('/var/www/html/config.json'), true) : [];
                ?>
                <form method="POST" action="/npm_api.php">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="px-6 py-6 space-y-5">
                        <p class="text-sm text-slate-400">Save your Nginx Proxy Manager credentials here to automatically generate Proxy Hosts and SSL mappings when you create a new project.</p>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">NPM Email</label>
                            <input type="email" name="npm_email" value="<?= htmlspecialchars($cfg['npm_email'] ?? '') ?>" placeholder="admin@example.com" class="form-input" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">NPM Password</label>
                            <input type="password" name="npm_password" value="<?= htmlspecialchars($cfg['npm_password'] ?? '') ?>" placeholder="••••••••" class="form-input" required>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-slate-900/80 border-t border-slate-700/50 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" onclick="toggleModal('settingsModal')" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-emerald-500/20 flex items-center gap-2">
                            <iconify-icon icon="mdi:content-save"></iconify-icon> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
