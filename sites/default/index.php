<?php
/**
 * Docker Local Webserver — Dashboard & Status Page
 * URL: http://localhost:8000
 */

session_start();
require_once __DIR__ . '/npm_api.php';

// ---- Helper Functions ----
function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return false;
    $files = scandir($dirPath);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $path = "$dirPath/$file";
            is_dir($path) ? deleteDir($path) : @unlink($path);
        }
    }
    return @rmdir($dirPath);
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

// ---- Handle GET Actions ----
if (($_GET['action'] ?? '') === 'download_bat') {
    $rawDomain = $_GET['domain'] ?? '';
    $rawDomain = preg_replace('/\.test$/i', '', trim($rawDomain));
    $domain = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawDomain) . '.test';
    
    $confPath = "/etc/nginx/conf.d/$domain.conf";
    $domains = [$domain];
    if (file_exists($confPath)) {
        $content = file_get_contents($confPath);
        if (preg_match('/server_name\s+(.+?);/', $content, $matches)) {
            $parsed = array_filter(array_map('trim', explode(' ', $matches[1])));
            if (!empty($parsed)) $domains = array_values($parsed);
        }
    }
    
    $bat = "@echo off\r\n";
    $bat .= ":: BatchGotAdmin\r\n";
    $bat .= ":-------------------------------------\r\n";
    $bat .= "REM  --> Check for permissions\r\n";
    $bat .= ">nul 2>&1 \"%SYSTEMROOT%\\system32\\cacls.exe\" \"%SYSTEMROOT%\\system32\\config\\system\"\r\n";
    $bat .= "REM --> If error flag set, we do not have admin.\r\n";
    $bat .= "if '%errorlevel%' NEQ '0' (\r\n";
    $bat .= "    echo Requesting administrative privileges...\r\n";
    $bat .= "    goto UACPrompt\r\n";
    $bat .= ") else ( goto gotAdmin )\r\n";
    $bat .= ":UACPrompt\r\n";
    $bat .= "    echo Set UAC = CreateObject^(\"Shell.Application\"^) > \"%temp%\\getadmin.vbs\"\r\n";
    $bat .= "    echo UAC.ShellExecute \"%~s0\", \"\", \"\", \"runas\", 1 >> \"%temp%\\getadmin.vbs\"\r\n";
    $bat .= "    \"%temp%\\getadmin.vbs\"\r\n";
    $bat .= "    exit /B\r\n";
    $bat .= ":gotAdmin\r\n";
    $bat .= "    if exist \"%temp%\\getadmin.vbs\" ( del \"%temp%\\getadmin.vbs\" )\r\n";
    $bat .= "    pushd \"%CD%\"\r\n";
    $bat .= "    CD /D \"%~dp0\"\r\n";
    $bat .= ":--------------------------------------\r\n\r\n";
    
    $bat .= "echo ===========================================\r\n";
    $bat .= "echo  Docker Local Webserver - Fix Hosts File\r\n";
    $bat .= "echo ===========================================\r\n";
    $bat .= "echo.\r\n";
    
    foreach ($domains as $d) {
        $bat .= "echo Adding $d to Windows hosts file...\r\n";
        $bat .= "findstr /I /C:\"$d\" %WINDIR%\\System32\\drivers\\etc\\hosts >nul\r\n";
        $bat .= "if %errorlevel% neq 0 (\r\n";
        $bat .= "    >> %WINDIR%\\System32\\drivers\\etc\\hosts echo 127.0.0.1	$d\r\n";
        $bat .= "    echo [OK] $d added successfully.\r\n";
        $bat .= ") else (\r\n";
        $bat .= "    echo [INFO] $d already exists in hosts file.\r\n";
        $bat .= ")\r\n";
        $bat .= "echo.\r\n";
    }
    
    $bat .= "echo Reloading Nginx container...\r\n";
    $bat .= "docker exec nginx nginx -s reload\r\n";
    $bat .= "echo.\r\n";
    $bat .= "echo Done! You can now access your domains in the browser.\r\n";
    $bat .= "pause\r\n";

    header('Content-Type: application/bat');
    header('Content-Disposition: attachment; filename="fix-hosts-'.$domain.'.bat"');
    echo $bat;
    exit;
}

if (($_GET['action'] ?? '') === 'download_remove_bat') {
    $rawDomain = $_GET['domain'] ?? '';
    $rawDomain = preg_replace('/\.test$/i', '', trim($rawDomain));
    $domain = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawDomain) . '.test';
    
    $confPath = "/etc/nginx/conf.d/$domain.conf";
    $domains = [$domain];
    if (file_exists($confPath)) {
        $content = file_get_contents($confPath);
        if (preg_match('/server_name\s+(.+?);/', $content, $matches)) {
            $parsed = array_filter(array_map('trim', explode(' ', $matches[1])));
            if (!empty($parsed)) $domains = array_values($parsed);
        }
    }
    
    $bat = "@echo off\r\n";
    $bat .= ":: BatchGotAdmin\r\n";
    $bat .= ":-------------------------------------\r\n";
    $bat .= "REM  --> Check for permissions\r\n";
    $bat .= ">nul 2>&1 \"%SYSTEMROOT%\\system32\\cacls.exe\" \"%SYSTEMROOT%\\system32\\config\\system\"\r\n";
    $bat .= "REM --> If error flag set, we do not have admin.\r\n";
    $bat .= "if '%errorlevel%' NEQ '0' (\r\n";
    $bat .= "    echo Requesting administrative privileges...\r\n";
    $bat .= "    goto UACPrompt\r\n";
    $bat .= ") else ( goto gotAdmin )\r\n";
    $bat .= ":UACPrompt\r\n";
    $bat .= "    echo Set UAC = CreateObject^(\"Shell.Application\"^) > \"%temp%\\getadmin.vbs\"\r\n";
    $bat .= "    echo UAC.ShellExecute \"%~s0\", \"\", \"\", \"runas\", 1 >> \"%temp%\\getadmin.vbs\"\r\n";
    $bat .= "    \"%temp%\\getadmin.vbs\"\r\n";
    $bat .= "    exit /B\r\n";
    $bat .= ":gotAdmin\r\n";
    $bat .= "    if exist \"%temp%\\getadmin.vbs\" ( del \"%temp%\\getadmin.vbs\" )\r\n";
    $bat .= "    pushd \"%CD%\"\r\n";
    $bat .= "    CD /D \"%~dp0\"\r\n";
    $bat .= ":--------------------------------------\r\n\r\n";
    
    $bat .= "echo ===========================================\r\n";
    $bat .= "echo  Docker Local Webserver - Clean Hosts File\r\n";
    $bat .= "echo ===========================================\r\n";
    $bat .= "echo.\r\n";
    
    // Copy hosts to temp once
    $bat .= "copy /Y %WINDIR%\\System32\\drivers\\etc\\hosts \"%temp%\\hosts_clean.tmp\" >nul\r\n";
    
    foreach ($domains as $d) {
        $bat .= "echo Removing $d from Windows hosts file...\r\n";
        $bat .= "findstr /I /V /C:\"$d\" \"%temp%\\hosts_clean.tmp\" > \"%temp%\\hosts_clean2.tmp\"\r\n";
        $bat .= "copy /Y \"%temp%\\hosts_clean2.tmp\" \"%temp%\\hosts_clean.tmp\" >nul\r\n";
    }
    
    $bat .= "copy /Y \"%temp%\\hosts_clean.tmp\" %WINDIR%\\System32\\drivers\\etc\\hosts >nul\r\n";
    $bat .= "del \"%temp%\\hosts_clean.tmp\" \"%temp%\\hosts_clean2.tmp\"\r\n";
    $bat .= "echo [OK] Domains removed successfully.\r\n";
    $bat .= "echo.\r\n";
    $bat .= "pause\r\n";

    header('Content-Type: application/bat');
    header('Content-Disposition: attachment; filename="clean-hosts-'.$domain.'.bat"');
    echo $bat;
    exit;
}

// ---- Handle POST Actions ----
$action = $_POST['action'] ?? null;

// Require valid NPM credentials for any modifying action
$npmToken = null;
if (in_array($action, ['create', 'delete'])) {
    $npmValid = false;
    if (file_exists('/var/www/html/config.json')) {
        $cfg = json_decode(file_get_contents('/var/www/html/config.json'), true);
        if (!empty($cfg['npm_email']) && !empty($cfg['npm_password'])) {
            require_once __DIR__ . '/npm_api.php';
            $res = callNpmApi('POST', '/tokens', ['identity' => $cfg['npm_email'], 'secret' => $cfg['npm_password']]);
            if ($res['status'] === 200 && isset($res['data']['token'])) {
                $npmValid = true;
                $npmToken = $res['data']['token'];
            }
        }
    }
    
    if (!$npmValid) {
        $_SESSION['flash'] = [
            'type' => 'error', 
            'title' => 'NPM Authentication Required', 
            'message' => 'Anda harus login ke akun NPM terlebih dahulu via menu <strong>Settings (⚙️)</strong> di pojok kanan atas sebelum membuat atau menghapus project.'
        ];
        header("Location: /");
        exit;
    }
}

if ($action === 'create') {
    $rawName = $_POST['name'] ?? '';
    // Hapus akhiran .test jika user tidak sengaja mengetiknya
    $rawName = preg_replace('/\.test$/i', '', trim($rawName));
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawName);
    $phpVer = preg_replace('/[^0-9]/', '', $_POST['php_version'] ?? '84');
    $type = $_POST['type'] ?? 'standard';
    
    $rawAliases = $_POST['aliases'] ?? '';
    $aliases = [];
    if (!empty(trim($rawAliases))) {
        $parts = explode(',', $rawAliases);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p)) $aliases[] = $p;
        }
    }
    
    if ($name) {
        $domain = $name . '.test';
        $siteDir = "/var/www/html/$domain";
        $confDir = "/etc/nginx/conf.d";
        $templatePath = "$confDir/_template.conf.example";
        $confPath = "$confDir/$domain.conf";
        
        $allDomains = array_values(array_unique(array_merge([$domain], $aliases)));
        $domainList = implode(' ', $allDomains);
        
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
                    ['{{DOMAIN}}', '{{DOMAIN_LIST}}', '{{PHP_VERSION}}', '{{ROOT_PATH}}'],
                    [$domain, $domainList, "php$phpVer", $rootPath],
                    $template
                );
                
                if (@file_put_contents($confPath, $config) !== false) {
                    // NPM API integration
                    $npmMsg = "";
                    $token = $npmToken;
                    
                    // Generate specific certificate for this domain using mkcert
                    $certId = 0;
                    $certPath = "/tmp/$domain.pem";
                    $keyPath = "/tmp/$domain-key.pem";
                    $mkcertArgs = implode(' ', array_map('escapeshellarg', $allDomains));
                    exec("CAROOT=/var/www/ssl/ca /var/www/ssl/mkcert -cert-file $certPath -key-file $keyPath " . $mkcertArgs, $output, $ret);
                    
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
                        'domain_names' => $allDomains,
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

                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'title' => 'Project Created Successfully! 🎉',
                        'message' => "Folder <strong>$domain</strong> and Nginx config generated" . $npmMsg . ".",
                        'domain' => $domain
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

if ($action === 'edit') {
    $domain = preg_replace('/[^a-zA-Z0-9_.-]/', '', $_POST['domain'] ?? '');
    $rawAliases = $_POST['aliases'] ?? '';
    
    if ($domain && strpos($domain, '.test') !== false && $domain !== 'default.test') {
        $aliases = [];
        if (!empty(trim($rawAliases))) {
            $parts = explode(',', $rawAliases);
            foreach ($parts as $p) {
                $p = trim($p);
                if (!empty($p)) $aliases[] = $p;
            }
        }
        
        $confPath = "/etc/nginx/conf.d/$domain.conf";
        $allDomains = array_values(array_unique(array_merge([$domain], $aliases)));
        $domainList = implode(' ', $allDomains);
        
        if (file_exists($confPath)) {
            // Update Nginx
            $content = file_get_contents($confPath);
            $content = preg_replace('/server_name\s+(.+?);/', "server_name $domainList;", $content);
            file_put_contents($confPath, $content);
            
            // Generate new cert
            $certId = 0;
            $certPath = "/tmp/$domain.pem";
            $keyPath = "/tmp/$domain-key.pem";
            $mkcertArgs = implode(' ', array_map('escapeshellarg', $allDomains));
            exec("CAROOT=/var/www/ssl/ca /var/www/ssl/mkcert -cert-file $certPath -key-file $keyPath " . $mkcertArgs, $output, $ret);
            
            if ($ret === 0 && file_exists($certPath)) {
                $uploadRes = uploadNpmCertificate($npmToken, "$domain SSL (Edited)", $certPath, $keyPath);
                if ($uploadRes['status'] === 200 || $uploadRes['status'] === 201) {
                    $certId = $uploadRes['data']['id'];
                }
                @unlink($certPath);
                @unlink($keyPath);
            }
            
            // Update Proxy Host in NPM
            $proxyRes = callNpmApi('GET', '/nginx/proxy-hosts', [], $npmToken);
            $hostFound = false;
            $oldCertId = 0;
            if ($proxyRes['status'] === 200) {
                foreach ($proxyRes['data'] as $host) {
                    if (in_array($domain, $host['domain_names'])) {
                        $hostFound = true;
                        $oldCertId = $host['certificate_id'] ?? 0;
                        
                        $payload = [
                            'domain_names' => $allDomains,
                            'forward_scheme' => $host['forward_scheme'],
                            'forward_host' => $host['forward_host'],
                            'forward_port' => $host['forward_port'],
                            'certificate_id' => $certId,
                            'ssl_forced' => ($certId > 0),
                            'meta' => $host['meta'],
                            'advanced_config' => $host['advanced_config'],
                            'locations' => $host['locations'],
                            'block_exploits' => $host['block_exploits'],
                            'caching_enabled' => $host['caching_enabled'],
                            'allow_websocket_upgrade' => $host['allow_websocket_upgrade'],
                            'http2_support' => $host['http2_support'],
                            'hsts_enabled' => $host['hsts_enabled'],
                            'hsts_subdomains' => $host['hsts_subdomains']
                        ];
                        callNpmApi('PUT', "/nginx/proxy-hosts/{$host['id']}", $payload, $npmToken);
                        
                        // Delete old cert if we have a new one
                        if ($certId > 0 && $oldCertId > 0 && $oldCertId !== $certId) {
                            callNpmApi('DELETE', "/nginx/certificates/$oldCertId", [], $npmToken);
                        }
                        break;
                    }
                }
            }
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'title' => 'Project Updated!',
                'message' => "Domains and SSL for <strong>$domain</strong> have been updated.",
                'domain' => $domain
            ];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => "Nginx config not found for $domain"];
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
        
        $deletedComplete = deleteDir($siteDir);
        if (file_exists($confPath)) {
            @unlink($confPath);
        }

        // 1. Delete Proxy Host from NPM via API
        $proxyRes = callNpmApi('GET', '/nginx/proxy-hosts', [], $npmToken);
        if ($proxyRes['status'] === 200) {
            foreach ($proxyRes['data'] as $host) {
                if (in_array($domain, $host['domain_names'])) {
                    $certId = $host['certificate_id'] ?? 0;
                    callNpmApi('DELETE', "/nginx/proxy-hosts/{$host['id']}", [], $npmToken);
                    if ($certId > 0) {
                        callNpmApi('DELETE', "/nginx/certificates/$certId", [], $npmToken);
                    }
                    break;
                }
            }
        }
        
        $msg = "Project <strong>$domain</strong> configuration and routing have been removed.";
        if (is_dir($siteDir)) {
            $msg .= "<br><br><span class='text-yellow-400'>⚠️ Note: Could not completely delete the folder due to permissions/file locks. You may need to delete the `sites/$domain` folder manually from Windows.</span>";
        } else {
            $msg .= " The folder has also been successfully deleted.";
        }
        
        $_SESSION['flash'] = [
            'type' => is_dir($siteDir) ? 'warning' : 'success',
            'title' => 'Project Deleted 🗑️',
            'message' => $msg,
            'command' => "docker restart nginx",
            'domain' => $domain,
            'autoclean' => true
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
        
        <div id="flash-container">
        <?php if ($flash): ?>
            <div class="mb-8 p-6 rounded-2xl border <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 border-emerald-500/20' : ($flash['type'] === 'warning' ? 'bg-amber-500/10 border-amber-500/20' : 'bg-red-500/10 border-red-500/20') ?> relative">
                <h3 class="text-lg font-bold <?= $flash['type'] === 'success' ? 'text-emerald-400' : ($flash['type'] === 'warning' ? 'text-amber-400' : 'text-red-400') ?> flex items-center gap-2 mb-2">
                    <iconify-icon icon="<?= $flash['type'] === 'success' ? 'mdi:check-circle' : ($flash['type'] === 'warning' ? 'mdi:alert' : 'mdi:alert-circle') ?>"></iconify-icon>
                    <?= $flash['title'] ?? ucfirst($flash['type']) ?>
                </h3>
                <p class="text-slate-300"><?= $flash['message'] ?></p>
                
                <?php if (!empty($flash['domain'])): ?>
                <div class="mt-4 p-4 bg-slate-950 rounded-xl border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 group">
                    <div>
                        <p class="text-sm font-medium text-slate-200"><iconify-icon icon="mdi:information-outline" class="text-indigo-400"></iconify-icon> Action Required</p>
                        <p class="text-xs text-slate-400 mt-1">
                            <?= !empty($flash['autoclean']) ? "Download and run the cleanup script to remove the domain from Windows." : "Download and run the auto-fix script to make the domain accessible on Windows." ?>
                        </p>
                    </div>
                    <?php if (!empty($flash['autoclean'])): ?>
                    <a href="/?action=download_remove_bat&domain=<?= urlencode($flash['domain']) ?>" class="flex-shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-sm font-medium rounded-lg transition-colors shadow-lg shadow-amber-500/20 whitespace-nowrap">
                        <iconify-icon icon="mdi:delete-sweep"></iconify-icon> Auto-Clean Hosts File
                    </a>
                    <?php else: ?>
                    <a href="/?action=download_bat&domain=<?= urlencode($flash['domain']) ?>" class="flex-shrink-0 inline-flex items-center justify-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors shadow-lg shadow-indigo-500/20 whitespace-nowrap">
                        <iconify-icon icon="mdi:download"></iconify-icon> Auto-Fix Hosts File
                    </a>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-slate-500 mt-2"><iconify-icon icon="mdi:shield-check"></iconify-icon> The script will prompt for Administrator privileges to edit <code>C:\Windows\System32\drivers\etc\hosts</code>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
        
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
                    
                    <div id="sites-container" class="glass-panel p-2">
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
                                        <?php
                                            $siteAliases = '';
                                            $confPath = "/etc/nginx/conf.d/$site.conf";
                                            if (file_exists($confPath)) {
                                                $content = file_get_contents($confPath);
                                                if (preg_match('/server_name\s+(.+?);/', $content, $matches)) {
                                                    $parsed = array_filter(array_map('trim', explode(' ', $matches[1])));
                                                    $parsed = array_filter($parsed, function($d) use ($site) { 
                                                        return $d !== $site; 
                                                    });
                                                    if (!empty($parsed)) $siteAliases = implode(', ', $parsed);
                                                }
                                            }
                                        ?>
                                        <div class="flex items-center space-x-1 ml-4">
                                            <button type="button" onclick="openEditModal('<?= htmlspecialchars($site) ?>', '<?= htmlspecialchars($siteAliases, ENT_QUOTES) ?>')" class="text-slate-500 hover:text-indigo-400 p-2 rounded-lg hover:bg-indigo-500/10 transition-colors tooltip" title="Edit Domains">
                                                <iconify-icon icon="mdi:pencil-outline" class="text-lg"></iconify-icon>
                                            </button>
                                            <button type="button" onclick="confirmDelete('<?= htmlspecialchars($site) ?>')" class="text-slate-500 hover:text-red-400 p-2 rounded-lg hover:bg-red-500/10 transition-colors tooltip" title="Delete Site">
                                                <iconify-icon icon="mdi:trash-can-outline" class="text-lg"></iconify-icon>
                                            </button>
                                        </div>
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
                <form method="POST" action="/" class="spa-form">
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
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Aliases / Custom Domains (Optional)</label>
                            <input type="text" name="aliases" placeholder="api.myapp.test, myotherdomain.com" class="form-input w-full">
                            <p class="text-xs text-slate-500 mt-1.5">Comma separated. E.g: admin.myapp.test, *.myapp.test</p>
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
    <!-- Edit Site Modal -->
    <div id="editModal" class="fixed inset-0 z-50 hidden opacity-0 modal-transition">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="toggleModal('editModal')"></div>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative bg-surface border border-slate-700 rounded-2xl text-left overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] transform transition-all sm:my-8 sm:max-w-lg w-full">
                <div class="px-6 py-5 border-b border-slate-700/50 flex justify-between items-center bg-slate-900/50">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <iconify-icon icon="mdi:pencil-outline" class="text-indigo-400"></iconify-icon>
                        Edit Domains for <span id="editDomainTitle" class="text-pink-400"></span>
                    </h3>
                    <button type="button" onclick="toggleModal('editModal')" class="text-slate-400 hover:text-white transition-colors">
                        <iconify-icon icon="mdi:close" class="text-xl"></iconify-icon>
                    </button>
                </div>
                <form method="POST" action="/" class="spa-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="domain" id="editDomainInput" value="">
                    <div class="px-6 py-6 space-y-5">
                        <p class="text-sm text-slate-400">Update the additional domains or subdomains for this project.</p>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Aliases / Custom Domains</label>
                            <input type="text" id="editAliasesInput" name="aliases" placeholder="api.myapp.test, myotherdomain.com" class="form-input w-full">
                            <p class="text-xs text-slate-500 mt-1.5">Comma separated. E.g: admin.myapp.test, *.myapp.test</p>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-slate-900/80 border-t border-slate-700/50 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" onclick="toggleModal('editModal')" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-indigo-500/20 flex items-center gap-2">
                            <iconify-icon icon="mdi:content-save"></iconify-icon> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Site Modal -->
    <div id="deleteModal" class="fixed inset-0 z-[60] hidden opacity-0 modal-transition">
        <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onclick="toggleModal('deleteModal')"></div>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative bg-surface border border-slate-700 rounded-2xl text-left overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] transform transition-all sm:my-8 sm:max-w-md w-full">
                <div class="px-6 py-5 border-b border-slate-700/50 flex justify-between items-center bg-red-500/10">
                    <h3 class="text-lg font-semibold text-red-400 flex items-center gap-2">
                        <iconify-icon icon="mdi:alert-circle-outline"></iconify-icon>
                        Confirm Deletion
                    </h3>
                    <button type="button" onclick="toggleModal('deleteModal')" class="text-slate-400 hover:text-white transition-colors">
                        <iconify-icon icon="mdi:close" class="text-xl"></iconify-icon>
                    </button>
                </div>
                <form id="deleteForm" method="POST" action="/" class="spa-form">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="domain" id="deleteDomainInput" value="">
                    <div class="px-6 py-6 text-slate-300">
                        <p>Are you sure you want to completely delete <strong id="deleteDomainText" class="text-white"></strong> and all its files?</p>
                        <p class="text-sm text-red-400 mt-3 p-3 bg-red-500/10 rounded-lg border border-red-500/20"><iconify-icon icon="mdi:warning"></iconify-icon> This action cannot be undone.</p>
                    </div>
                    <div class="px-6 py-4 bg-slate-900/80 border-t border-slate-700/50 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" onclick="toggleModal('deleteModal')" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors shadow-lg shadow-red-500/20 flex items-center gap-2">
                            <iconify-icon icon="mdi:trash-can-outline"></iconify-icon> Delete Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(domain) {
            document.getElementById('deleteDomainInput').value = domain;
            document.getElementById('deleteDomainText').textContent = domain;
            toggleModal('deleteModal');
        }

        function openEditModal(domain, aliases = '') {
            document.getElementById('editDomainInput').value = domain;
            document.getElementById('editDomainTitle').textContent = domain;
            document.getElementById('editAliasesInput').value = aliases;
            toggleModal('editModal');
        }

        document.querySelectorAll('.spa-form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const btn = form.querySelector('button[type="submit"]');
                btn.innerHTML = '<iconify-icon icon="mdi:loading" class="animate-spin"></iconify-icon> Processing...';
                // Biarkan form tersubmit secara normal (browser reload)
                // Disable button setelah sedikit delay agar form tetap terkirim
                setTimeout(() => { btn.disabled = true; }, 10);
            });
        });
    </script>
</body>
</html>
