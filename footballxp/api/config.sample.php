<?php
// MODELO de configuração. NÃO coloque o token aqui.
// Copie este conteúdo para um arquivo FORA do public_html, no Gerenciador de Arquivos da Hostinger:
//   domains/ltxglobal.com.br/ltx-config/rd-crm.php
// (a pasta ltx-config fica ao lado da pasta public_html, não dentro dela)
return [
    // RD Station CRM > Perfil > Token da instância (API v1)
    'token' => 'COLE_O_TOKEN_AQUI',

    // Funil "Atlético de Madrid": ID da etapa de entrada (ex.: "Novo lead")
    'lead_stage_id' => '',

    // Funil "Parceiros": etapa "Cadastro recebido" e etapa "Aprovado"
    'partner_stage_new_id' => '',
    'partner_stage_approved_id' => '',

    // Chave para abrir api/ids.php e descobrir os IDs abaixo (invente uma senha longa)
    'admin_key' => '',

    // Opcional: dono padrão das negociações e fontes
    'user_id' => '',
    'lead_source_id' => '',
    'partner_source_id' => '',

    // Opcional: IDs de campos personalizados de negociação no RD (deixe vazio o que não existir)
    'lead_fields' => [
        'parceiro' => '',
        'parceiro_status' => '',
        'idade' => '',
        'cidade' => '',
        'atleta' => '',
        'utm' => '',
    ],
    'partner_fields' => [
        'tipo' => '',
        'cnpj' => '',
        'alunos' => '',
        'idades' => '',
        'instagram' => '',
        'codigo' => '',
    ],
];
