<?php

return [
    'audit' => [
        'callback_secret' => [
            'rotated' => 'Private Abstimmungsbestätigungs-URL erneuert',
        ],
        'reviewed' => 'Abstimmungsprüfung abgeglichen',
    ],
    'callbacks' => [
        'title' => 'Abstimmungsbestätigungen empfangen',
        'url' => 'Im Verzeichnis einzutragende private URL',
        'url_help' => 'Speichere diese URL in den Servereinstellungen des Verzeichnisses als Bestätigungsziel. Stimmen werden erst nach Empfang und Prüfung des Nachweises vergeben.',
        'private' => 'Diese URL enthält ein Geheimnis. Bewahre sie in den privaten Verzeichniseinstellungen auf und verwende sie niemals als öffentlichen Abstimmungslink.',
        'reveal' => 'Private URL anzeigen',
        'key' => 'Vom Verzeichnis bereitgestelltes Bestätigungsgeheimnis',
        'server_id' => 'Server- oder Projekt-ID im Verzeichnis',
        'rotate' => 'Private URL erneuern',
        'rotate_help' => 'Die bisherige URL wird abgelehnt und unbestätigte Versuche laufen ab. Trage danach die neue URL in den privaten Verzeichniseinstellungen ein.',
        'rotate_confirm' => 'Ich bestätige, dass ich danach die URL beim Verzeichnis aktualisieren muss.',
    ],
    'imports' => [
        'busy' => 'Eine Abstimmungsprüfung oder Belohnungszustellung läuft. Warte vor dem Ersetzen der Abstimmungsdaten auf deren Abschluss.',
    ],
    'sites' => [
        'sections' => [
            'site' => 'Abstimmungsseite',
            'rules' => 'Abstimmungsregeln',
            'rewards' => 'Belohnungen',
        ],
        'saved' => 'Die Abstimmungsseite wurde gespeichert.',
        'used' => 'Diese Seite hat einen Abstimmungsverlauf. Deaktiviere sie, um ihn zu erhalten.',
        'callback_help' => 'Stimmen werden durch eine signierte Bestätigung des Partners validiert. Ein Klick zählt niemals als bestätigte Stimme.',
        'callback' => 'Bestätigungs-URL',
        'new' => 'Abstimmungsseite hinzufügen',
        'name' => 'Name',
        'cooldown_minutes' => 'Wartezeit zwischen Stimmen (Minuten)',
        'reward' => 'Punkte pro Stimme',
        'secret' => 'Gemeinsames Geheimnis (mindestens 32 Zeichen, zum Beibehalten leer lassen)',
        'commands' => 'Belohnungsbefehle, einer pro Zeile. Variablen: {player}, {uuid}',
        'enabled' => 'Seite aktiviert',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'deliveries' => 'Belohnungszustellungen',
        'command' => 'Befehl',
        'status' => 'Status',
        'deletion_delivery_pending' => 'Eine Abstimmungsbelohnung wird zugestellt. Warte vor dem Löschen des Kontos auf die Bestätigung.',
    ],
];
