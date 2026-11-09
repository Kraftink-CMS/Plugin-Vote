<?php

return [
    'token_placeholder' => 'EL_MISMO_TOKEN_QUE_EN_EL_SITIO_WEB',
    'minecraft' => [
        'setup_help' => 'En el archivo config.yml de NuVotifier, añade este nombre de servicio en tokens con el mismo token que en el sitio web. El nombre distingue entre mayúsculas y minúsculas. Integra este fragmento en tu configuración actual.',
        'connection_help' => 'El sitio web se conecta al puerto de Votifier de tu servidor o proxy de Minecraft. Puede ser distinto del puerto del juego. Usa una dirección accesible desde tu alojamiento web.',
    ],
    'hytale' => [
        'setup_help' => 'En el archivo config.json de HyVotifier, añade este nombre de servicio en Tokens con el mismo token que en el sitio web. El nombre distingue entre mayúsculas y minúsculas. Integra este fragmento en tu configuración actual.',
        'connection_help' => 'Usa el valor de BindPort de HyVotifier y una dirección del servidor accesible desde tu alojamiento web. El puerto de Votifier puede ser distinto del puerto de juego de Hytale.',
    ],
];
