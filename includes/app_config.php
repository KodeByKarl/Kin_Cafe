<?php

function getProjectConfig(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'app' => [
            'name' => 'Kin Cafe',
        ],
        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => 'kin_cafe',
            'username' => 'root',
            'password' => '',
        ],
        'mail' => [
            'enabled' => false,
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'auth_enabled' => true,
            'encryption' => 'tls',
            'from_email' => '',
            'from_name' => 'Kin Cafe',
            'reply_to_email' => '',
            'reply_to_name' => 'Kin Cafe',
            'timeout_seconds' => 20,
        ],
    ];

    return $config;
}

function getProjectDatabaseDefaults(): array {
    $config = getProjectConfig();
    $database = $config['database'] ?? [];

    return [
        'host' => trim((string) ($database['host'] ?? '127.0.0.1')),
        'port' => max(1, (int) ($database['port'] ?? 3306)),
        'name' => trim((string) ($database['name'] ?? 'kin_cafe')),
        'username' => trim((string) ($database['username'] ?? 'root')),
        'password' => (string) ($database['password'] ?? ''),
    ];
}

function getProjectMailDefaults(): array {
    $config = getProjectConfig();
    $mail = $config['mail'] ?? [];

    return [
        'enabled' => !empty($mail['enabled']),
        'host' => trim((string) ($mail['host'] ?? '')),
        'port' => max(1, (int) ($mail['port'] ?? 587)),
        'username' => trim((string) ($mail['username'] ?? '')),
        'password' => (string) ($mail['password'] ?? ''),
        'auth_enabled' => array_key_exists('auth_enabled', $mail) ? (bool) $mail['auth_enabled'] : true,
        'encryption' => strtolower(trim((string) ($mail['encryption'] ?? 'tls'))),
        'from_email' => trim((string) ($mail['from_email'] ?? '')),
        'from_name' => trim((string) ($mail['from_name'] ?? 'Kin Cafe')),
        'reply_to_email' => trim((string) ($mail['reply_to_email'] ?? '')),
        'reply_to_name' => trim((string) ($mail['reply_to_name'] ?? 'Kin Cafe')),
        'timeout_seconds' => max(5, (int) ($mail['timeout_seconds'] ?? 20)),
    ];
}