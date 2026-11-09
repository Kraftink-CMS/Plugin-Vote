<?php

return [
    'title' => 'Votifier v2',
    'delivery_mode' => 'Distribution des récompenses par vote',
    'commands' => 'Commandes et récompenses du site',
    'mode_help' => 'Avec Votifier, les récompenses par vote sont gérées sur le serveur. Les points, paliers et objectifs du site restent actifs. Évitez un second envoi du même vote depuis l’annuaire.',
    'connection' => 'Connexion au serveur de votes',
    'server' => 'Serveur Votifier',
    'server_help' => 'Choisissez le serveur ou proxy où Votifier est installé. Son adresse sera utilisée avec le port Votifier ci-dessous.',
    'port' => 'Port Votifier',
    'port_help' => 'Utilisez le port indiqué dans la configuration de Votifier.',
    'website_name' => 'Nom du site de vote (website_name)',
    'website_name_help' => 'Utilisez ce même nom pour associer le token dans la configuration du serveur, en respectant la casse.',
    'token' => 'Token secret Votifier v2',
    'token_help' => 'Copiez le token du plugin de jeu, ou configurez-y votre propre token de 16 caractères minimum.',
    'token_kept' => 'Un token est enregistré. Laissez vide pour le conserver. Il n’est jamais réaffiché.',
    'sent' => 'Envoyé sans confirmation',
    'sent_at' => 'Envoyé le :date',
    'server_required' => 'Choisissez le serveur qui reçoit les votes et retirez les serveurs proposés au joueur.',
    'identity_required' => 'L’identité de jeu ou le serveur cible ne correspond pas aux exigences de Votifier v2.',
    'token_required' => 'Renseignez le token Votifier v2.',
    'retry_configuration' => 'Réactivez Votifier v2 pour cet annuaire avec le serveur cible d’origine avant de relancer la notification. La relance utilisera le token actuellement configuré.',
];
