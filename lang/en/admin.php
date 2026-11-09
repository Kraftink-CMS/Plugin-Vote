<?php

return [
    'audit' => [
        'reviewed' => 'Vote verification reconciled',
        'callback_secret' => [
            'rotated' => 'Private vote confirmation URL rotated',
        ],
    ],
    'callbacks' => [
        'title' => 'Receive vote confirmations',
        'url' => 'Private URL to configure with the listing',
        'url_help' => 'Save this URL as the confirmation destination in your server settings on the listing website. Votes are awarded only after its proof has been received and verified.',
        'private' => 'This URL contains a secret. Keep it in the listing’s private settings and never use it as a public voting link.',
        'reveal' => 'Show private URL',
        'key' => 'Confirmation secret supplied by the listing',
        'server_id' => 'Server or project ID on the listing',
        'rotate' => 'Rotate private URL',
        'rotate_help' => 'The previous URL will be rejected and unconfirmed attempts will expire. Then save the new URL in the listing’s private settings.',
        'rotate_confirm' => 'I confirm that I must update the listing’s URL after this action.',
    ],
    'imports' => [
        'busy' => 'A vote verification or reward delivery is in progress. Wait for it to finish before replacing vote data.',
    ],
    'sites' => [
        'sections' => [
            'site' => 'Voting site',
            'rules' => 'Voting rules',
            'rewards' => 'Rewards',
        ],
        'saved' => 'The voting site has been saved.',
        'used' => 'This site has a voting history. Disable it to preserve the history.',
        'callback_help' => 'Votes are validated by a signed partner callback. A click never counts as a verified vote.',
        'callback' => 'Callback URL',
        'new' => 'Add a voting site',
        'name' => 'Name',
        'cooldown_minutes' => 'Cooldown between votes (minutes)',
        'reward' => 'Points per vote',
        'secret' => 'Shared secret (minimum 32 characters, leave empty to keep)',
        'commands' => 'Reward commands, one per line. Variables: {player}, {uuid}',
        'enabled' => 'Site enabled',
        'save' => 'Save',
        'delete' => 'Delete',
        'deliveries' => 'Reward deliveries',
        'command' => 'Command',
        'status' => 'Status',
        'deletion_delivery_pending' => 'A vote reward is being delivered. Wait for its confirmation before deleting the account.',
    ],
];
