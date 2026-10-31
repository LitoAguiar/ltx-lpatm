<?php
// IDs do RD Station CRM (instância "Agência Be"). Não são segredos: o token fica fora do public_html.
return [
    // Funil "Football Experience - Inscritos" (6ae5f2145027920027e954df)
    'lead_stage_id' => '6ae5f2145027920027e954e1', // Novo lead

    // Funil "Football Experience - Parceiros" (6ae5f36456256500205f98fa)
    'partner_stage_new_id' => '6ae5f36456256500205f98fc',      // Cadastro recebido
    'partner_stage_approved_id' => '6ae5f36456256500205f9900', // Aprovado

    'lead_fields' => [
        'parceiro'        => '6ae5f9f9c0333e0024c44846', // FX - Parceiro
        'parceiro_status' => '6ae5fa2334ca2e0020f9e0d8', // FX - Tipo de indicação
        'idade'           => '6ae5fa293ffd77002007c992', // FX - Idade do atleta
        'atleta'          => '6ae5fa47c0333e0020c446c5', // FX - Nome do atleta
        'cidade'          => '6ae5fa587235960020a4b7e3', // FX - Cidade/UF
        'utm_source'      => '6acf764ba4a1dc0024d9cd70', // UTM Source
        'utm_medium'      => '6acf764cd17ed000248e84ef', // UTM Medium
        'utm_campaign'    => '6acf764dfd8d280024a6fd3e', // UTM Campaign
        'utm_content'     => '6acf764ee2912a0023092548', // UTM Content
        'pagina'          => '6acf7647cc21be0020f726c4', // Página
    ],
    'partner_fields' => [
        'tipo'      => '6ae5fa5f85eb9100239a00fd', // FX - Tipo de instituição
        'cnpj'      => '6ae5fa7085eb91002099fbb0', // FX - CNPJ
        'alunos'    => '6ae5fa77971dc400210377a9', // FX - Número de alunos
        'idades'    => '6ae5fa8985eb91002099fbc2', // FX - Faixas de idade
        'instagram' => '6ae5fa8fcdb77b00252efc2a', // FX - Instagram
        'codigo'    => '6ae5fa9656256500285f9d16', // FX - Código do parceiro
    ],

    // Funil "Infinity Games Con" (6ae638f430023500245557c9)
    'igc' => [
        'stage_visitantes_id' => '6ae638f430023500245557cb', // Visitantes (lista)
        'stage_comercial_id'  => '6ae638f430023500245557cc', // Novo contato comercial
        // Apresentação ...cd · Proposta enviada ...ce · Contrato e pagamento ...cf
        'fields' => [
            'perfil'       => '6ae639c6521a9c002453727c', // IGC - Perfil
            'cidade'       => '6ae639c6eb59c800201254b8', // IGC - Cidade/país
            'utm_source'   => '6acf764ba4a1dc0024d9cd70',
            'utm_medium'   => '6acf764cd17ed000248e84ef',
            'utm_campaign' => '6acf764dfd8d280024a6fd3e',
            'utm_content'  => '6acf764ee2912a0023092548',
            'pagina'       => '6acf7647cc21be0020f726c4',
        ],
    ],
];
