<?php

return [
    'audit' => [
        'callback_secret' => [
            'rotated' => 'URL privado de confirmação de votos renovado',
        ],
        'reviewed' => 'Verificação de voto reconciliada',
    ],
    'callbacks' => [
        'title' => 'Receber confirmações de voto',
        'url' => 'URL privado a configurar no diretório',
        'url_help' => 'Guarda este URL como destino de confirmação nas definições do teu servidor no diretório. Os votos só são atribuídos após a receção e verificação da prova.',
        'private' => 'Este URL contém um segredo. Mantém-no nas definições privadas do diretório e nunca o uses como ligação pública de votação.',
        'reveal' => 'Mostrar URL privado',
        'key' => 'Segredo de confirmação fornecido pelo diretório',
        'server_id' => 'ID do servidor ou projeto no diretório',
        'rotate' => 'Renovar URL privado',
        'rotate_help' => 'O URL anterior será recusado e as tentativas não confirmadas expirarão. Depois guarda o novo URL nas definições privadas do diretório.',
        'rotate_confirm' => 'Confirmo que devo atualizar o URL no diretório após esta ação.',
    ],
    'imports' => [
        'busy' => 'Está em curso uma verificação de voto ou entrega de recompensa. Aguarda a conclusão antes de substituir os dados de votos.',
    ],
    'sites' => [
        'sections' => [
            'site' => 'Site de votação',
            'rules' => 'Regras de votação',
            'rewards' => 'Recompensas',
        ],
        'saved' => 'O site de votação foi guardado.',
        'used' => 'Este site tem um histórico de votos. Desativa-o para conservar o histórico.',
        'callback_help' => 'Os votos são validados por uma confirmação assinada do parceiro. Um clique nunca conta como voto confirmado.',
        'callback' => 'URL de confirmação',
        'new' => 'Adicionar um site de votação',
        'name' => 'Nome',
        'cooldown_minutes' => 'Intervalo entre votos (minutos)',
        'reward' => 'Pontos por voto',
        'secret' => 'Segredo partilhado (mínimo de 32 caracteres, deixa vazio para manter)',
        'commands' => 'Comandos de recompensa, um por linha. Variáveis: {player}, {uuid}',
        'enabled' => 'Site ativo',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'deliveries' => 'Entregas de recompensas',
        'command' => 'Comando',
        'status' => 'Estado',
        'deletion_delivery_pending' => 'Uma recompensa de voto está a ser entregue. Aguarda a confirmação antes de eliminar a conta.',
    ],
];
