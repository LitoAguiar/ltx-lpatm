<?php
// POST: inscrição de interesse da LP do Football Experience -> negociação no funil Atlético de Madrid.
require __DIR__ . '/_rd.php';

$in = ltx_read_input();
ltx_rate_limit('lead');

$f = [
    'responsavel' => ltx_str($in['responsavel'] ?? '', 120),
    'whatsapp'    => ltx_digits($in['whatsapp'] ?? ''),
    'idade'       => ltx_digits($in['idade'] ?? ''),
    'cidade'      => ltx_str($in['cidade'] ?? '', 120),
    'email'       => ltx_str($in['email'] ?? '', 160),
    'atleta'      => ltx_str($in['atleta'] ?? '', 120),
    'parceiro'    => ltx_slug(ltx_str($in['parceiro'] ?? '', 120)),
    'parceiro_origem' => in_array($in['parceiro_origem'] ?? '', ['link', 'declarado'], true) ? $in['parceiro_origem'] : '',
    'parceiro_texto' => ltx_str($in['parceiro_texto'] ?? '', 160),
    'utm'         => ltx_str($in['utm'] ?? '', 300),
    'pagina'      => ltx_str($in['pagina'] ?? '', 300),
    'consentimento' => !empty($in['consentimento']),
];

$errors = [];
if ($f['responsavel'] === '') { $errors[] = 'responsavel'; }
if (strlen($f['whatsapp']) < 10 || strlen($f['whatsapp']) > 13) { $errors[] = 'whatsapp'; }
if ($f['idade'] === '' || (int) $f['idade'] < 5 || (int) $f['idade'] > 19) { $errors[] = 'idade'; }
if ($f['cidade'] === '') { $errors[] = 'cidade'; }
if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'email'; }
if (!$f['consentimento']) { $errors[] = 'consentimento'; }
if ($errors) {
    ltx_json(422, ['ok' => false, 'error' => 'validation', 'fields' => $errors]);
}

// Confere o parceiro na lista de aprovados.
$partner = null;
if ($f['parceiro'] !== '') {
    // Primeiro no cache; se não achar, consulta o RD na hora (parceiro aprovado há menos de 10 minutos).
    foreach ([false, true] as $fresh) {
        foreach (ltx_partners($fresh) as $p) {
            if ($p['code'] === $f['parceiro']) { $partner = $p; break 2; }
        }
    }
}
$partnerLabel = $partner ? $partner['name'] : '';
if ($partner) {
    $partnerStatus = $f['parceiro_origem'] === 'link' ? 'Link do parceiro' : 'Declarado pelo inscrito (validar)';
} elseif ($f['parceiro_origem'] === 'link' && $f['parceiro'] !== '') {
    // Link de parceiro ainda não aprovado (ou código errado): não perde a indicação, marca para validar.
    $partnerLabel = $f['parceiro'];
    $partnerStatus = 'Link sem parceiro aprovado (validar)';
} else {
    $partnerStatus = $f['parceiro_texto'] !== '' ? 'Não cadastrado: ' . $f['parceiro_texto'] : 'Sem parceiro';
}

ltx_backup('leads', $f + ['parceiro_nome' => $partnerLabel, 'parceiro_status' => $partnerStatus]);

$cfg = ltx_config();
$stage = $cfg['lead_stage_id'] ?? '';
if (($cfg['token'] ?? '') === '' || $stage === '') {
    // Sem configuração: o lead fica salvo no backup e a página mostra sucesso.
    ltx_json(200, ['ok' => true, 'crm' => false]);
}

$utm = [];
parse_str($f['utm'], $utm);
$utm = array_map(fn($v) => ltx_str(is_string($v) ? $v : '', 120), $utm);

$dealName = $f['responsavel'] . ' — atleta ' . $f['idade'] . ' anos' . ($partnerLabel ? ' — ' . $partnerLabel : '');
$contact = [
    'name' => $f['responsavel'],
    'phones' => [['phone' => $f['whatsapp'], 'type' => 'cellphone']],
    'legal_bases' => [['category' => 'communications', 'type' => 'consent', 'status' => 'granted']],
];
if ($f['email'] !== '') {
    $contact['emails'] = [['email' => $f['email']]];
}
$deal = [
    'name' => mb_substr($dealName, 0, 180),
    'deal_stage_id' => $stage,
    'deal_custom_fields' => rd_custom_fields($cfg['lead_fields'] ?? [], [
        'parceiro' => $partnerLabel,
        'parceiro_status' => $partnerStatus,
        'idade' => $f['idade'],
        'cidade' => $f['cidade'],
        'atleta' => $f['atleta'],
        'utm_source' => $utm['utm_source'] ?? '',
        'utm_medium' => $utm['utm_medium'] ?? '',
        'utm_campaign' => $utm['utm_campaign'] ?? '',
        'utm_content' => $utm['utm_content'] ?? '',
        'pagina' => $f['pagina'],
    ]),
];
if (!empty($cfg['user_id'])) {
    $deal['user_id'] = $cfg['user_id'];
}
$body = ['deal' => $deal, 'contacts' => [$contact]];
if (!empty($cfg['lead_source_id'])) {
    $body['deal_source'] = ['_id' => $cfg['lead_source_id']];
}

$r = rd_request('POST', 'deals', [], $body);
if (!$r['ok']) {
    ltx_backup('erros', ['tipo' => 'lead', 'status' => $r['status'], 'resposta' => $r['data'], 'erro' => $r['error']]);
    // O lead já está no backup; a família não precisa saber da falha do CRM.
    ltx_json(200, ['ok' => true, 'crm' => false]);
}

$id = rd_deal_id($r['data']);
if ($id !== '') {
    rd_note($id, implode("\n", [
        'Inscrição de interesse — Football Experience Madrid 2027',
        'Responsável: ' . $f['responsavel'],
        'WhatsApp: ' . $f['whatsapp'],
        'E-mail: ' . ($f['email'] ?: '-'),
        'Atleta: ' . ($f['atleta'] ?: '-') . ', ' . $f['idade'] . ' anos',
        'Cidade/estado: ' . $f['cidade'],
        'Parceiro: ' . ($partnerLabel ?: '-') . ' (' . $partnerStatus . ')',
        'Origem: ' . ($f['utm'] ?: '-'),
    ]));
}
ltx_json(200, ['ok' => true, 'crm' => true]);
