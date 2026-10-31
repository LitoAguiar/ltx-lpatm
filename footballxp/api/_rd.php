<?php
// Funções compartilhadas pela integração com o RD Station CRM.
// O token NUNCA fica neste repositório: ele vive em um arquivo de configuração
// fora do public_html (veja config.sample.php).

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

header('X-Content-Type-Options: nosniff');

function ltx_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $candidates = [];
    if (getenv('LTX_RD_CONFIG')) {
        $candidates[] = getenv('LTX_RD_CONFIG');
    }
    $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if ($docroot !== '') {
        $candidates[] = dirname($docroot) . '/ltx-config/rd-crm.php';
    }
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            $cfg = require $path;
            if (is_array($cfg)) {
                return $cfg;
            }
        }
    }
    $cfg = [];
    return $cfg;
}

function ltx_json(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function ltx_data_dir(): ?string
{
    $cfg = ltx_config();
    $dir = $cfg['data_dir'] ?? null;
    if (!$dir) {
        $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $dir = $docroot !== '' ? dirname($docroot) . '/ltx-data' : sys_get_temp_dir() . '/ltx-data';
    }
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return null;
    }
    return $dir;
}

// Guarda uma cópia de cada envio fora do public_html: se o RD falhar, nenhum lead se perde.
function ltx_backup(string $kind, array $record): void
{
    $dir = ltx_data_dir();
    if ($dir === null) {
        return;
    }
    $record['_kind'] = $kind;
    $record['_at'] = date('c');
    @file_put_contents($dir . '/' . $kind . '.jsonl', json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function ltx_read_input(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ltx_json(405, ['ok' => false, 'error' => 'method']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 20000);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        ltx_json(400, ['ok' => false, 'error' => 'json']);
    }
    // Campo isca: robôs preenchem, pessoas não veem.
    if (!empty($data['website'])) {
        ltx_json(200, ['ok' => true]);
    }
    return $data;
}

function ltx_rate_limit(string $bucket, int $max = 8, int $window = 600): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
    $file = sys_get_temp_dir() . '/ltx-rl-' . $bucket . '-' . md5($ip);
    $now = time();
    $hits = [];
    if (is_readable($file)) {
        $hits = array_filter(explode(',', (string) file_get_contents($file)), fn($t) => $t !== '' && (int) $t > $now - $window);
    }
    if (count($hits) >= $max) {
        ltx_json(429, ['ok' => false, 'error' => 'rate']);
    }
    $hits[] = (string) $now;
    @file_put_contents($file, implode(',', $hits), LOCK_EX);
}

function ltx_str($v, int $max = 200): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string) ($v ?? '')));
    return mb_substr(strip_tags($s), 0, $max);
}

function ltx_digits($v): string
{
    return preg_replace('/\D+/', '', (string) ($v ?? ''));
}

function ltx_slug(string $s): string
{
    $s = mb_strtolower(trim($s));
    $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e','í'=>'i','î'=>'i','ì'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o','ú'=>'u','û'=>'u','ù'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n'];
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// Chamada à API v1 do RD Station CRM (token como parâmetro "token").
function rd_request(string $method, string $path, array $query = [], ?array $body = null): array
{
    $cfg = ltx_config();
    $token = $cfg['token'] ?? '';
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'error' => 'not_configured'];
    }
    $base = $cfg['api_base'] ?? 'https://crm.rdstation.com/api/v1';
    $query['token'] = $token;
    $url = rtrim($base, '/') . '/' . ltrim($path, '/') . '?' . http_build_query($query);
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
    ]);
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $json = is_string($resp) ? json_decode($resp, true) : null;
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'data' => is_array($json) ? $json : null,
        'error' => $err ?: null,
    ];
}

// Monta deal_custom_fields só com os campos cujo ID foi configurado.
function rd_custom_fields(array $map, array $values): array
{
    $out = [];
    foreach ($values as $key => $value) {
        $id = $map[$key] ?? '';
        if ($id !== '' && $value !== '' && $value !== null) {
            $out[] = ['custom_field_id' => $id, 'value' => (string) $value];
        }
    }
    return $out;
}

// Anotação na negociação com todos os dados do formulário (funciona mesmo sem campos personalizados).
function rd_note(string $dealId, string $text): void
{
    $cfg = ltx_config();
    $activity = ['deal_id' => $dealId, 'text' => $text];
    if (!empty($cfg['user_id'])) {
        $activity['user_id'] = $cfg['user_id'];
    }
    rd_request('POST', 'activities', [], ['activity' => $activity]);
}

function rd_deal_id(?array $data): string
{
    if (!$data) {
        return '';
    }
    return (string) ($data['_id'] ?? $data['id'] ?? ($data['deal']['_id'] ?? ''));
}

// Lista de parceiros aprovados = negociações na etapa "Aprovado" do funil de parceiros.
// Nome da negociação no formato "Instituição — Cidade/UF". Cache de 10 minutos.
function ltx_partners(bool $fresh = false): array
{
    $cfg = ltx_config();
    $stage = $cfg['partner_stage_approved_id'] ?? '';
    $cache = sys_get_temp_dir() . '/ltx-partners-' . md5($stage) . '.json';
    if (!$fresh && is_readable($cache) && filemtime($cache) > time() - 600) {
        $list = json_decode((string) file_get_contents($cache), true);
        if (is_array($list)) {
            return $list;
        }
    }
    if ($stage === '') {
        return [];
    }
    $list = [];
    $next = null;
    for ($i = 0; $i < 10; $i++) {
        $q = ['deal_stage_id' => $stage, 'limit' => 200];
        if ($next) {
            $q['next_page'] = $next;
        }
        $r = rd_request('GET', 'deals', $q);
        if (!$r['ok'] || !is_array($r['data']['deals'] ?? null)) {
            if (is_readable($cache)) {
                $old = json_decode((string) file_get_contents($cache), true);
                return is_array($old) ? $old : [];
            }
            return [];
        }
        foreach ($r['data']['deals'] as $d) {
            $full = trim((string) ($d['name'] ?? ''));
            if ($full === '') {
                continue;
            }
            $parts = preg_split('/\s+[—–-]\s+/u', $full, 2);
            $name = trim($parts[0]);
            $city = isset($parts[1]) ? trim($parts[1]) : '';
            $list[] = ['code' => ltx_slug($name), 'name' => $name, 'city' => $city];
        }
        $next = $r['data']['next_page'] ?? null;
        if (empty($r['data']['has_more']) || !$next) {
            break;
        }
    }
    usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
    @file_put_contents($cache, json_encode($list, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $list;
}
