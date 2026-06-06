<?php
$settingsPath = '/var/www/html/config.json';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    file_put_contents($settingsPath, json_encode([
        'npm_email' => $_POST['npm_email'] ?? '',
        'npm_password' => $_POST['npm_password'] ?? ''
    ]));
    header('Location: /');
    exit;
}

function callNpmApi($method, $endpoint, $data = null, $token = null) {
    $ch = curl_init("http://npm:81/api" . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($response, true)];
}

function uploadNpmCertificate($token, $name, $certPath, $keyPath) {
    // Step 1: Create custom certificate record
    $createRes = callNpmApi('POST', '/nginx/certificates', ['nice_name' => $name, 'provider' => 'other'], $token);
    if ($createRes['status'] !== 200 && $createRes['status'] !== 201) {
        return $createRes;
    }
    
    $certId = $createRes['data']['id'];
    
    // Step 2: Upload files
    $ch = curl_init("http://npm:81/api/nginx/certificates/" . $certId . "/upload");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    $headers = ['Authorization: Bearer ' . $token];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $postFields = [
        'certificate_key' => new CURLFile($keyPath, 'application/x-pem-file', 'key.pem'),
        'certificate' => new CURLFile($certPath, 'application/x-x509-ca-cert', 'cert.pem')
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($status === 200 || $status === 201) {
        return ['status' => 200, 'data' => ['id' => $certId]];
    }
    return ['status' => $status, 'data' => json_decode($response, true)];
}
