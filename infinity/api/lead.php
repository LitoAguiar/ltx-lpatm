<?php
// POST: formulário da LP do Infinity Games Con -> funil "Infinity Games Con" do RD CRM.
// Visitante entra em "Visitantes (lista)"; os demais perfis entram em "Novo contato comercial".
require __DIR__ . '/../../footballxp/api/_rd.php';

$in = ltx_read_input();
ltx_rate_limit('igc');

$perfis = ['Visitante', 'Expositor', 'Patrocinador', 'Creator', 'Desenvolvedor ou startup', 'Caravana ou grupo', 'Imprensa'];
$f = [
    'nome'     => ltx_str($in['nome'] ?? '', 120),
    'whatsapp' => ltx_digits($in['whatsapp'] ?? ''),
    'email'    => ltx_str($in['email'] ?? '', 160),
    'cidade'   => ltx_str($in['cidade'] ?? '', 120),
    'perfil'   => in_array($in['perfil'] ?? '', $perfis, true) ? $in['perfil'] : '',
    'empresa'  => ltx_str($in['empresa'] ?? '', 120),
    'utm'      => ltx_str($in['utm'] ?? '', 300),
    'pagina'   => ltx_str($in['pagina'] ?? '', 300),
    'consentimento' => !empty($in['consentimento']),
];

$errors = [];
foreach (['nome', 'cidade', 'perfil'] as $k) {
    if ($f[$k] === '') { $errors[] = $k; }
}
if (strlen($f['whatsapp']) < 10 || strlen($f['whatsapp']) > 13) { $errors[] = 'whatsapp'; }
if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'email'; }
if (!$f['consentimento']) { $errors[] = 'consentimento'; }
if ($errors) {
    ltx_json(422, ['ok' => false, 'error' => 'validation', 'fields' => $errors]);
}

ltx_backup('igc-leads', $f);

$cfg = ltx_config();
$igc = $cfg['igc'] ?? [];
$visitante = $f['perfil'] === 'Visitante';
$stage = $visitante ? ($igc['stage_visitantes_id'] ?? '') : ($igc['stage_comercial_id'] ?? '');
if (($cfg['token'] ?? '') === '' || $stage === '') {
    ltx_json(200, ['ok' => true, 'crm' => false]);
}

$utm = [];
parse_str($f['utm'], $utm);
$utm = array_map(fn($v) => ltx_str(is_string($v) ? $v : '', 120), $utm);

$dealName = $f['nome'] . ' — ' . ($f['empresa'] !== '' ? $f['empresa'] . ' (' . $f['perfil'] . ')' : $f['perfil']);
$fields = rd_custom_fields($igc['fields'] ?? [], [
    'perfil'       => $f['perfil'],
    'cidade'       => $f['cidade'],
    'utm_source'   => $utm['utm_source'] ?? '',
    'utm_medium'   => $utm['utm_medium'] ?? '',
    'utm_campaign' => $utm['utm_campaign'] ?? '',
    'utm_content'  => $utm['utm_content'] ?? '',
    'pagina'       => $f['pagina'],
]);

$deal = ['name' => $dealName, 'deal_stage_id' => $stage];
if ($fields) { $deal['deal_custom_fields'] = $fields; }
if (!empty($cfg['user_id'])) { $deal['user_id'] = $cfg['user_id']; }
$contact = [
    'name' => $f['nome'],
    'emails' => [['email' => $f['email']]],
    'phones' => [['phone' => $f['whatsapp'], 'type' => 'cellphone']],
    'legal_bases' => [['category' => 'communications', 'type' => 'consent', 'status' => 'granted']],
];
$body = ['deal' => $deal, 'contacts' => [$contact]];
if (!empty($igc['deal_source_id'])) { $body['deal_source'] = ['_id' => $igc['deal_source_id']]; }

$r = rd_request('POST', 'deals', [], $body);
$id = rd_deal_id($r['data'] ?? null);
if (!$r['ok'] || $id === '') {
    ltx_backup('igc-erros', ['status' => $r['status'], 'resp' => $r['data'], 'lead' => $f]);
    // O lead já está no backup; a pessoa não precisa reenviar.
    ltx_json(200, ['ok' => true, 'crm' => false]);
}

rd_note($id, implode("\n", [
    'Formulário LP Infinity Games Con 2027',
    'Nome: ' . $f['nome'],
    'WhatsApp: ' . $f['whatsapp'],
    'E-mail: ' . $f['email'],
    'Cidade/país: ' . $f['cidade'],
    'Perfil: ' . $f['perfil'],
    'Empresa: ' . ($f['empresa'] ?: '-'),
    'Origem: ' . ($f['utm'] ?: 'direto'),
    'Consentimento de contato: sim',
]));

ltx_json(200, ['ok' => true, 'crm' => true]);
