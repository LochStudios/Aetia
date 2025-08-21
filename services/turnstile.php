<?php
// services/turnstile.php - Cloudflare Turnstile service helpers

require_once __DIR__ . '/../config/database.php';

/**
 * Verify Cloudflare Turnstile response token server-side and persist verification.
 * Returns decoded siteverify array (may have success=false and error-codes).
 */
function verifyTurnstileResponseShared($token, $secret, $remoteIp = null, $idempotencyKey = null, $expectedAction = null, $expectedHostname = null) {
    // Basic input validation
    if (empty($secret) || empty($token)) {
        return ['success' => false, 'error' => 'missing-input'];
    }
    if (!is_string($token) || $token === '') {
        return ['success' => false, 'error' => 'invalid-input-response'];
    }
    if (strlen($token) > 2048) {
        return ['success' => false, 'error' => 'token_too_long'];
    }
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $payload = [
        'secret' => $secret,
        'response' => $token
    ];
    if (!empty($remoteIp)) {
        $payload['remoteip'] = $remoteIp;
    }
    if (!empty($idempotencyKey)) {
        $payload['idempotency_key'] = $idempotencyKey;
    }
    $postData = http_build_query($payload);
    // Cluster-wide single-use enforcement: check DB for used token hash if available
    try {
        if (class_exists('Database')) {
            $db = new Database();
            $mysqli = $db->getConnection();
            $tokenHash = hash('sha256', $token);
            $checkStmt = $mysqli->prepare("SELECT id, success, created_at FROM turnstile_verifications WHERE token_hash = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('s', $tokenHash);
                $checkStmt->execute();
                $res = $checkStmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    error_log('Turnstile token already verified (replay): ' . $tokenHash);
                    return ['success' => false, 'error' => 'replayed_token'];
                }
                $checkStmt->close();
            }
        }
    } catch (Exception $e) {
        error_log('Turnstile DB check failed: ' . $e->getMessage());
    }
    // Perform siteverify (prefer curl)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            error_log('Turnstile verification cURL error: ' . $err);
            $decoded = ['success' => false, 'error' => 'curl_error'];
        } else {
            $decoded = json_decode($response, true) ?: ['success' => false, 'error' => 'invalid_response'];
        }
    } else {
        $opts = ['http' => [
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 5
        ]];
        $context  = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('Turnstile verification failed: unable to contact siteverify endpoint');
            $decoded = ['success' => false, 'error' => 'network_error'];
        } else {
            $decoded = json_decode($response, true) ?: ['success' => false, 'error' => 'invalid_response'];
        }
    }
    // Additional validation: expected action and hostname
    if (!empty($expectedAction) && !empty($decoded['action']) && $decoded['action'] !== $expectedAction) {
        error_log('Turnstile action mismatch: expected=' . $expectedAction . ' received=' . ($decoded['action'] ?? ''));
        $decoded['success'] = false;
        $decoded['error-codes'] = array_merge($decoded['error-codes'] ?? [], ['action-mismatch']);
    }
    if (!empty($expectedHostname) && !empty($decoded['hostname']) && stripos($decoded['hostname'], $expectedHostname) === false && stripos($expectedHostname, $decoded['hostname']) === false) {
        error_log('Turnstile hostname mismatch: expected=' . $expectedHostname . ' received=' . ($decoded['hostname'] ?? ''));
        $decoded['success'] = false;
        $decoded['error-codes'] = array_merge($decoded['error-codes'] ?? [], ['hostname-mismatch']);
    }
    // Persist verification result if DB available
    try {
        if (class_exists('Database')) {
            $db = new Database();
            $mysqli = $db->getConnection();
            $tokenHash = hash('sha256', $token);
            $responseJson = $mysqli->real_escape_string(json_encode($decoded));
            $successInt = !empty($decoded['success']) ? 1 : 0;
            $idempKey = $idempotencyKey;
            $action = !empty($decoded['action']) ? $decoded['action'] : null;
            $cdata = !empty($decoded['cdata']) ? $decoded['cdata'] : null;
            $ephemeral = null;
            if (!empty($decoded['metadata']) && is_array($decoded['metadata']) && !empty($decoded['metadata']['ephemeral_id'])) {
                $ephemeral = $decoded['metadata']['ephemeral_id'];
            }
            $hostname = !empty($decoded['hostname']) ? $decoded['hostname'] : null;
            $challengeTs = !empty($decoded['challenge_ts']) ? date('Y-m-d H:i:s', strtotime($decoded['challenge_ts'])) : null;
            $errorCodesJson = null;
            if (!empty($decoded['error-codes']) || !empty($decoded['error_codes'])) {
                $ec = !empty($decoded['error-codes']) ? $decoded['error-codes'] : ($decoded['error_codes'] ?? null);
                $errorCodesJson = $mysqli->real_escape_string(json_encode($ec));
            }
            $insertSql = "INSERT INTO turnstile_verifications (token_hash, idempotency_key, remoteip, success, response_json, action, cdata, ephemeral_id, hostname, challenge_ts, error_codes) VALUES ('{$tokenHash}', ?, ?, {$successInt}, '{$responseJson}', ?, ?, ?, ?, ?, ?)";
            $stmt = $mysqli->prepare($insertSql);
            if ($stmt) {
                $stmt->bind_param('ssssssssss', $idempKey, $remoteIp, $action, $cdata, $ephemeral, $hostname, $challengeTs, $errorCodesJson);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log('Turnstile DB insert failed: ' . $e->getMessage());
    }
    return $decoded;
}

// Backwards-compatible wrapper named as before
function verifyTurnstileResponse($token, $secret, $remoteIp = null, $idempotencyKey = null, $expectedAction = null, $expectedHostname = null) {
    return verifyTurnstileResponseShared($token, $secret, $remoteIp, $idempotencyKey, $expectedAction, $expectedHostname);
}

// Small helper to load turnstile config from secure web-config
function loadTurnstileConfig() {
    $cfgPath = '/home/aetiacom/web-config/turnstile.php';
    $out = [
        'site_key' => null,
        'secret_key' => null,
        'explicit' => false,
        'execution' => 'render',
        // pages where Turnstile should be enabled: contact, login, signup, forgot
        'enable_on' => ['contact']
    ];
    if (file_exists($cfgPath)) {
        $cfg = include $cfgPath;
        if (is_array($cfg)) {
            $out['site_key'] = !empty($cfg['site_key']) ? $cfg['site_key'] : null;
            $out['secret_key'] = !empty($cfg['secret_key']) ? $cfg['secret_key'] : null;
            $out['explicit'] = !empty($cfg['explicit']);
            $out['execution'] = !empty($cfg['execution']) ? $cfg['execution'] : 'render';
            $out['enable_on'] = !empty($cfg['enable_on']) && is_array($cfg['enable_on']) ? $cfg['enable_on'] : $out['enable_on'];
        }
    }
    return $out;
}

?>
