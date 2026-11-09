<?php

return [
    'token_placeholder' => 'O_MESMO_TOKEN_QUE_NO_SITE',
    'minecraft' => [
        'setup_help' => 'No ficheiro config.yml do NuVotifier, adiciona este nome de serviço em tokens com o mesmo token que no site. O nome distingue maiúsculas de minúsculas. Integra este excerto na configuração existente.',
        'connection_help' => 'O site liga-se à porta Votifier do servidor ou proxy de Minecraft. Pode ser diferente da porta de jogo. Usa um endereço acessível a partir do alojamento web.',
    ],
    'hytale' => [
        'setup_help' => 'No ficheiro config.json do HyVotifier, adiciona este nome de serviço em Tokens com o mesmo token que no site. O nome distingue maiúsculas de minúsculas. Integra este excerto na configuração existente.',
        'connection_help' => 'Usa o valor de BindPort do HyVotifier e um endereço de servidor acessível a partir do alojamento web. A porta Votifier pode ser diferente da porta de jogo do Hytale.',
    ],
];
