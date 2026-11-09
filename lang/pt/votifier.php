<?php

return [
    'title' => 'Votifier v2',
    'delivery_mode' => 'Entrega de recompensas por voto',
    'commands' => 'Comandos e recompensas do site',
    'mode_help' => 'Com Votifier, o servidor gere as recompensas por voto. Os pontos, marcos e objetivos do site continuam ativos. Evita que o diretório envie também o mesmo voto diretamente.',
    'connection' => 'Ligação ao servidor de votos',
    'server' => 'Servidor Votifier',
    'server_help' => 'Escolhe o servidor ou proxy que executa o Votifier. O endereço é usado com a porta Votifier indicada abaixo.',
    'port' => 'Porta Votifier',
    'port_help' => 'Usa a porta indicada na configuração do Votifier.',
    'website_name' => 'Nome do site de votação (website_name)',
    'website_name_help' => 'Associa o token a este nome exato na configuração do servidor. O nome distingue maiúsculas de minúsculas.',
    'token' => 'Token secreto do Votifier v2',
    'token_help' => 'Copia o token do plugin do jogo ou configura lá um token com pelo menos 16 caracteres.',
    'token_kept' => 'Já existe um token guardado. Deixa o campo vazio para o manter. O token não volta a ser mostrado.',
    'sent' => 'Enviado sem confirmação',
    'sent_at' => 'Enviado a :date',
    'server_required' => 'Escolhe o servidor que recebe os votos e retira os servidores que os jogadores podem selecionar.',
    'identity_required' => 'A identidade de jogo ou o servidor de destino não cumpre os requisitos do Votifier v2.',
    'token_required' => 'Introduz o token do Votifier v2.',
    'retry_configuration' => 'Ativa o Votifier v2 neste diretório com o servidor de destino original antes de reenviares a notificação. O reenvio usa o token atualmente configurado.',
];
