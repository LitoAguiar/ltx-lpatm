<?php
// POST: cadastro de parceiro (escolinha, escola, clube) -> negociação no funil de parceiros, etapa "Cadastro recebido".
require __DIR__ . '/_rd.php';

$in = ltx_read_input();
ltx_rate_limit('parceiro', 5);

$tipos = ['Escolinha de futebol', 'Escola particular', 'Clube', 'Projeto esportivo', 'Outro'];
$f = [
    'tipo'        => in_array($in['tipo'] ?? '', $tipos, true) ? $in['tipo'] : '',
    'instituicao' => ltx_str($in['instituicao'] ?? '', 120),
    'cnpj'        => ltx_digits($in['cnpj'] ?? ''),
    'cidade'      => ltx_str($in['cidade'] ?? '', 120),
    'responsavel' => ltx_str($in['responsavel'] ?? '', 120),
    'cargo'       => ltx_str($in['cargo'] ?? '', 80),
    'whatsapp'    => ltx_digits($in['whatsapp'] ?? ''),
    'email'       => ltx_str($in['email'] ?? '', 160),
    'alunos'      => ltx_str($in['alunos'] ?? '', 40),
    'idades'      => ltx_str($in['idades'] ?? '', 80),
    'instagram'   => ltx_str($in['instagram'] ?? '', 80),
    'consentimento' => !empty($in['consentimento']),
];

$errors = [];
foreach (['tipo', 'instituicao', 'cidade', 'responsavel', 'alunos'] as $k) {
    if ($f[$k] === '') { $errors[] = $k; }
}
if (strlen($f['cnpj']) !== 14) { $errors[] = 'cnpj'; }
if (strlen($f['whatsapp']) < 10 || strlen($f['whatsapp']) > 13) { $errors[] = 'whatsapp'; }
if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'email'; }
if (!$f['consentimento']) { $errors[] = 'consentimento'; }
if ($errors) {
    ltx_json(422, ['ok' => false, 'error' => 'validation', 'fields' => $errors]);
}

$code = ltx_slug($f['instituicao']);
ltx_backup('parceiros', $f + ['codigo' => $code]);

$cfg = ltx_config();
$stage = $cfg['partner_stage_new_id'] ?? '';
if (($cfg['token'] ?? '') === '' || $stage === '') {
    ltx_json(200, ['ok' => true, 'crm' => false]);
}

$deal = [
    // Formato lido pela lista de parceiros: "Instituição — Cidade/UF". Mantenha ao editar no RD.
    'name' => mb_substr($f['instituicao'] . ' — ' . $f['cidade'], 0, 180),
    'deal_stage_id' => $stage,
    'deal_custom_fields' => rd_custom_fields($cfg['partner_fields'] ?? [], [
        'tipo' => $f['tipo'],
        'cnpj' => $f['cnpj'],
        'alunos' => $f['alunos'],
        'idades' => $f['idades'],
        'instagram' => $f['instagram'],
        'codigo' => $code,
    ]),
];
if (!empty($cfg['user_id'])) {
    $deal['user_id'] = $cfg['user_id'];
}
$contact = [
    'name' => $f['responsavel'],
    'title' => $f['cargo'],
    'emails' => [['email' => $f['email']]],
    'phones' => [['phone' => $f['whatsapp'], 'type' => 'cellphone']],
    'legal_bases' => [['category' => 'communications', 'type' => 'consent', 'status' => 'granted']],
];
$body = ['deal' => $deal, 'contacts' => [$contact]];
if (!empty($cfg['partner_source_id'])) {
    $body['deal_source'] = ['_id' => $cfg['partner_source_id']];
}

$r = rd_request('POST', 'deals', [], $body);
if (!$r['ok']) {
    ltx_backup('erros', ['tipo' => 'parceiro', 'status' => $r['status'], 'resposta' => $r['data'], 'erro' => $r['error']]);
    ltx_json(200, ['ok' => true, 'crm' => false]);
}
$id = rd_deal_id($r['data']);
if ($id !== '') {
    rd_note($id, implode("\n", [
        'Cadastro de parceiro — Football Experience',
        'Tipo: ' . $f['tipo'],
        'Instituição: ' . $f['instituicao'] . ' (CNPJ ' . $f['cnpj'] . ')',
        'Cidade/estado: ' . $f['cidade'],
        'Responsável: ' . $f['responsavel'] . ($f['cargo'] ? ', ' . $f['cargo'] : ''),
        'WhatsApp: ' . $f['whatsapp'] . ' | E-mail: ' . $f['email'],
        'Alunos: ' . $f['alunos'] . ' | Idades: ' . ($f['idades'] ?: '-'),
        'Instagram: ' . ($f['instagram'] ?: '-'),
        'Link do parceiro após aprovação: https://ltxglobal.com.br/footballxp/?p=' . $code,
    ]));
}
ltx_json(200, ['ok' => true, 'crm' => true]);
