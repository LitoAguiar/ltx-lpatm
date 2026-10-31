<?php
// Ajuda de configuração: mostra os IDs de funis, etapas e campos personalizados do RD CRM.
// Uso: https://ltxglobal.com.br/footballxp/api/ids.php?key=SUA_CHAVE (a chave fica no arquivo de configuração, campo admin_key).
require __DIR__ . '/_rd.php';

$cfg = ltx_config();
$key = (string) ($cfg['admin_key'] ?? '');
if ($key === '' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit;
}

$out = ['funis' => [], 'campos_de_negociacao' => []];
$p = rd_request('GET', 'deal_pipelines');
foreach ((is_array($p['data']) ? $p['data'] : []) as $pipe) {
    $stages = [];
    foreach (($pipe['deal_stages'] ?? []) as $st) {
        $stages[] = ['etapa' => $st['name'] ?? '', 'id' => $st['_id'] ?? ($st['id'] ?? '')];
    }
    $out['funis'][] = ['funil' => $pipe['name'] ?? '', 'id' => $pipe['_id'] ?? ($pipe['id'] ?? ''), 'etapas' => $stages];
}
$c = rd_request('GET', 'custom_fields', ['option' => 'deal']);
foreach (($c['data']['custom_fields'] ?? []) as $f) {
    $out['campos_de_negociacao'][] = ['campo' => $f['label'] ?? '', 'id' => $f['_id'] ?? ($f['id'] ?? '')];
}
$out['status_rd'] = ['funis' => $p['status'], 'campos' => $c['status']];
ltx_json(200, $out);
