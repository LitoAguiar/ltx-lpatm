<?php
// GET: lista pública de parceiros aprovados (nome, cidade e código do link).
require __DIR__ . '/_rd.php';

header('Cache-Control: public, max-age=300');
ltx_json(200, ['ok' => true, 'partners' => ltx_partners()]);
