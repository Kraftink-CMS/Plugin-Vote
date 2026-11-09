<?php

return [
    'audit' => [
        'callback_secret' => [
            'rotated' => 'URL privada de confirmación de votos renovada',
        ],
        'reviewed' => 'Verificación de voto conciliada',
    ],
    'callbacks' => [
        'title' => 'Recibir confirmaciones de voto',
        'url' => 'URL privada que se configura en el directorio',
        'url_help' => 'Guarda esta URL como destino de confirmación en los ajustes de tu servidor en el directorio. Los votos solo se conceden después de recibir y verificar la prueba.',
        'private' => 'Esta URL contiene un secreto. Consérvala en los ajustes privados del directorio y nunca la uses como enlace público de votación.',
        'reveal' => 'Mostrar URL privada',
        'key' => 'Secreto de confirmación proporcionado por el directorio',
        'server_id' => 'ID del servidor o proyecto en el directorio',
        'rotate' => 'Renovar URL privada',
        'rotate_help' => 'La URL anterior se rechazará y las verificaciones sin confirmar caducarán. Después guarda la nueva URL en los ajustes privados del directorio.',
        'rotate_confirm' => 'Confirmo que debo actualizar la URL del directorio después de esta acción.',
    ],
    'imports' => [
        'busy' => 'Hay una verificación de voto o entrega de recompensa en curso. Espera a que termine antes de reemplazar los datos de votos.',
    ],
    'sites' => [
        'sections' => [
            'site' => 'Sitio de votación',
            'rules' => 'Reglas de votación',
            'rewards' => 'Recompensas',
        ],
        'saved' => 'El sitio de votación se ha guardado.',
        'used' => 'Este sitio tiene un historial de votos. Desactívalo para conservarlo.',
        'callback_help' => 'Los votos se validan con una confirmación firmada del colaborador. Un clic nunca cuenta como voto verificado.',
        'callback' => 'URL de confirmación',
        'new' => 'Añadir un sitio de votación',
        'name' => 'Nombre',
        'cooldown_minutes' => 'Espera entre votos (minutos)',
        'reward' => 'Puntos por voto',
        'secret' => 'Secreto compartido (mínimo 32 caracteres, déjalo vacío para conservarlo)',
        'commands' => 'Comandos de recompensa, uno por línea. Variables: {player}, {uuid}',
        'enabled' => 'Sitio activado',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'deliveries' => 'Entregas de recompensas',
        'command' => 'Comando',
        'status' => 'Estado',
        'deletion_delivery_pending' => 'Se está entregando una recompensa de voto. Espera su confirmación antes de eliminar la cuenta.',
    ],
];
