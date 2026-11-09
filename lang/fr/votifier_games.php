<?php

return [
    'token_placeholder' => 'LE_MEME_TOKEN_QUE_SUR_LE_SITE',
    'minecraft' => [
        'setup_help' => 'Dans config.yml de NuVotifier, ajoutez ce nom de service dans tokens avec exactement le même token que sur le site. Le nom respecte la casse. Fusionnez cet extrait avec votre configuration existante.',
        'connection_help' => 'Le site se connecte au port Votifier de votre serveur ou proxy Minecraft. Ce port peut être différent du port de jeu. Utilisez son adresse joignable depuis votre hébergement web.',
    ],
    'hytale' => [
        'setup_help' => 'Dans config.json de HyVotifier, ajoutez ce nom de service dans Tokens avec exactement le même token que sur le site. Le nom respecte la casse. Fusionnez cet extrait avec votre configuration existante.',
        'connection_help' => 'Dans HyVotifier, utilisez BindPort et une adresse du serveur joignable depuis votre hébergement web. Le port Votifier peut être différent du port de jeu Hytale.',
    ],
];
