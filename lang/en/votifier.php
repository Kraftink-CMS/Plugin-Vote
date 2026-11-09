<?php

return [
    'title' => 'Votifier v2',
    'delivery_mode' => 'Per-vote reward delivery',
    'commands' => 'Website commands and rewards',
    'mode_help' => 'With Votifier, per-vote rewards are managed on the server. Website points, milestones and goals remain active. Avoid sending the same vote again directly from the listing.',
    'connection' => 'Vote server connection',
    'server' => 'Votifier server',
    'server_help' => 'Choose the server or proxy running Votifier. Its address is used with the Votifier port below.',
    'port' => 'Votifier port',
    'port_help' => 'Use the port shown in your Votifier configuration.',
    'website_name' => 'Voting website name (website_name)',
    'website_name_help' => 'Associate the token with this exact name in the server configuration. The name is case-sensitive.',
    'token' => 'Votifier v2 secret token',
    'token_help' => 'Copy the token from the game plugin, or configure your own token there with at least 16 characters.',
    'token_kept' => 'A token is saved. Leave empty to keep it. It is never displayed again.',
    'sent' => 'Sent without confirmation',
    'sent_at' => 'Sent at :date',
    'server_required' => 'Choose the server receiving votes and remove the player-selectable servers.',
    'identity_required' => 'The game identity or target server does not meet the Votifier v2 requirements.',
    'token_required' => 'Enter the Votifier v2 token.',
    'retry_configuration' => 'Enable Votifier v2 for this listing with its original target server before retrying the notification. The retry uses the currently configured token.',
];
