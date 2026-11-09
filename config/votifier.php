<?php

return [
    'games' => [
        'minecraft' => [
            'username_max' => 16,
            'connection_help' => 'vote::votifier_games.minecraft.connection_help',
            'setup_help' => 'vote::votifier_games.minecraft.setup_help',
            'template' => "port: {port}\ndisable-v1-protocol: true\ntokens:\n  {website_name}: {token}",
        ],
        'hytale' => [
            'username_max' => 16,
            'connection_help' => 'vote::votifier_games.hytale.connection_help',
            'setup_help' => 'vote::votifier_games.hytale.setup_help',
            'template' => "{\n  \"BindPort\": {port},\n  \"DisableV1Protocol\": true,\n  \"Tokens\": {\n    {website_name}: {token}\n  }\n}",
        ],
    ],
];
