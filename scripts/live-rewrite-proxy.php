<?php
/*
 * What nginx does on the panel's own port, locally and over plain HTTP.
 *
 * The panel serves the External API at https://<host>:8443/api/external/v1/...
 * and rewrites that to /v1/... on 127.0.0.1:3002. The signature covers the
 * rewritten path, so this does the same rewrite and nothing else: the module's
 * real client, its real signing and the real backend are all in the loop. Only
 * TLS is not, because the development panel's certificate is self-signed with
 * no subject alternative name and the module - correctly - verifies.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);

if (!str_starts_with($path, '/api/external/')) {
    http_response_code(404);
    echo '{"error":"not an external api path"}';
    return;
}

$target = 'http://127.0.0.1:3002' . substr($path, strlen('/api/external')) . ($query ? '?' . $query : '');
$body = file_get_contents('php://input');

$headers = ['Accept: application/json'];
foreach (['HTTP_X_API_KEY' => 'X-API-Key', 'HTTP_X_TIMESTAMP' => 'X-Timestamp', 'HTTP_X_SIGNATURE' => 'X-Signature', 'CONTENT_TYPE' => 'Content-Type'] as $from => $to) {
    if (!empty($_SERVER[$from])) {
        $headers[] = $to . ': ' . $_SERVER[$from];
    }
}

$ch = curl_init($target);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 60,
]);
if ($body !== '' && $body !== false) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$answer = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

http_response_code($status ?: 502);
header('Content-Type: application/json');
echo $answer !== false ? $answer : json_encode(['status' => 'error', 'error' => $error]);
