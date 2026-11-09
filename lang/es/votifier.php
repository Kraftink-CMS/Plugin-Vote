<?php

return [
    'title' => 'Votifier v2',
    'delivery_mode' => 'Entrega de recompensas por voto',
    'commands' => 'Comandos y recompensas del sitio web',
    'mode_help' => 'Con Votifier, el servidor gestiona las recompensas por voto. Los puntos, hitos y objetivos del sitio web siguen activos. Evita que el directorio envíe también el mismo voto directamente.',
    'connection' => 'Conexión con el servidor de votos',
    'server' => 'Servidor Votifier',
    'server_help' => 'Elige el servidor o proxy que ejecuta Votifier. Su dirección se usa con el puerto de Votifier indicado a continuación.',
    'port' => 'Puerto de Votifier',
    'port_help' => 'Usa el puerto indicado en tu configuración de Votifier.',
    'website_name' => 'Nombre del sitio de votación (website_name)',
    'website_name_help' => 'Asocia el token con este nombre exacto en la configuración del servidor. Distingue entre mayúsculas y minúsculas.',
    'token' => 'Token secreto de Votifier v2',
    'token_help' => 'Copia el token del plugin del juego o configura allí tu propio token de al menos 16 caracteres.',
    'token_kept' => 'Ya hay un token guardado. Deja el campo vacío para conservarlo. El token no se vuelve a mostrar.',
    'sent' => 'Enviado sin confirmación',
    'sent_at' => 'Enviado el :date',
    'server_required' => 'Elige el servidor que recibe los votos y quita los servidores que pueden seleccionar los jugadores.',
    'identity_required' => 'La identidad del juego o el servidor de destino no cumple los requisitos de Votifier v2.',
    'token_required' => 'Introduce el token de Votifier v2.',
    'retry_configuration' => 'Activa Votifier v2 para este directorio con el servidor de destino original antes de reenviar la notificación. El reenvío usa el token configurado actualmente.',
];
