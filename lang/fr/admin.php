<?php

return [
    'audit' => [
        'reviewed' => 'Vérification de vote rapprochée',
        'callback_secret' => [
            'rotated' => 'URL privée de confirmation des votes renouvelée',
        ],
    ],
    'callbacks' => [
        'title' => 'Réception des confirmations de vote',
        'url' => 'URL privée à enregistrer chez l’annuaire',
        'url_help' => 'Enregistrez cette URL comme destination de confirmation dans les paramètres de votre serveur sur l’annuaire. Les votes sont attribués uniquement après réception et vérification de sa preuve.',
        'private' => 'Cette URL contient un secret. Conservez-la dans les paramètres privés de l’annuaire et ne l’utilisez jamais comme lien public de vote.',
        'reveal' => 'Afficher l’URL privée',
        'key' => 'Secret de confirmation fourni par l’annuaire',
        'server_id' => 'Identifiant du serveur ou projet sur l’annuaire',
        'rotate' => 'Renouveler l’URL privée',
        'rotate_help' => 'L’ancienne URL sera refusée et les tentatives non confirmées expireront. Enregistrez ensuite la nouvelle URL dans les paramètres privés de l’annuaire.',
        'rotate_confirm' => 'Je confirme devoir remplacer l’URL chez l’annuaire après cette action.',
    ],
    'imports' => [
        'busy' => 'Une vérification ou une livraison de vote est en cours. Attendez sa fin avant de remplacer les données de vote.',
    ],
    'sites' => [
        'sections' => [
            'site' => 'Site de vote',
            'rules' => 'Règles de vote',
            'rewards' => 'Récompenses',
        ],
        'saved' => 'Le site de vote a été enregistré.',
        'used' => 'Ce site possède un historique de votes. Désactivez-le pour le conserver.',
        'callback_help' => 'Les votes sont validés par le retour signé du partenaire. Ne comptez jamais un simple clic comme un vote.',
        'callback' => 'Adresse de validation',
        'new' => 'Ajouter un site de vote',
        'name' => 'Nom',
        'cooldown_minutes' => 'Délai entre deux votes (minutes)',
        'reward' => 'Points par vote',
        'secret' => 'Secret partagé (32 caractères minimum, vide pour conserver)',
        'commands' => 'Commandes de récompense, une par ligne. Variables : {player}, {uuid}',
        'enabled' => 'Site activé',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'deliveries' => 'Livraisons de récompenses',
        'command' => 'Commande',
        'status' => 'État',
        'deletion_delivery_pending' => 'Une récompense de vote est en cours de livraison. Attendez sa confirmation avant de supprimer le compte.',
    ],
];
