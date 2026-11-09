<?php

return [
    'token_placeholder' => 'THE_SAME_TOKEN_AS_ON_THE_WEBSITE',
    'minecraft' => [
        'setup_help' => 'In NuVotifier config.yml, add this service name under tokens with exactly the same token as on the website. The name is case-sensitive. Merge this excerpt into your existing configuration.',
        'connection_help' => 'The website connects to the Votifier port of your Minecraft server or proxy. This can differ from the game port. Use its address reachable from your web hosting.',
    ],
    'hytale' => [
        'setup_help' => 'In HyVotifier config.json, add this service name under Tokens with exactly the same token as on the website. The name is case-sensitive. Merge this excerpt into your existing configuration.',
        'connection_help' => 'Use HyVotifier BindPort and a server address reachable from your web hosting. The Votifier port can differ from the Hytale game port.',
    ],
];
