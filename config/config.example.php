<?php
/**
 * VerityVote - Configuration Template
 * 
 * Copy this file to 'config.php' and adjust settings to match your local environment.
 * For production, ensure debug is false and secure cookies are enabled.
 */

$databaseName = 'verityvote_db';
$testOverrideFile = dirname(__DIR__) . '/storage/.test_database_override';
if (file_exists($testOverrideFile)) {
    $overrideVal = trim((string)@file_get_contents($testOverrideFile));
    if ($overrideVal === 'verityvote_test_suite') {
        $databaseName = 'verityvote_test_suite';
    }
}

return [
    'app' => [
        'name' => 'VerityVote',
        'tagline' => 'Institutional College Voting System',
        'env' => 'development', // 'development' or 'production'
        'debug' => true,
        // Base URL relative to domain root, e.g. '/Baymax_Voting_System/public' or '' if VirtualHost root
        'base_url' => '', 
        'timezone' => 'Asia/Kolkata',
    ],

    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => $databaseName,
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'session' => [
        'cookie_name' => 'VERITYVOTE_SESSID',
        'idle_timeout' => 1800,        // 30 minutes
        'absolute_timeout' => 14400,   // 4 hours max per session
        'cookie_lifetime' => 0,        // Session cookie
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => false,      // Set to true when serving over HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',    // 'Lax' or 'Strict'
    ],

    'security' => [
        'max_login_attempts' => 5,
        'lockout_duration_seconds' => 900, // 15 minutes
        'temp_password_expiry_hours' => 24,
        'hmac_secret' => '4f8b9d3e2a1c7f5e8b0a9d8c7e6f5a4b3c2d1e0f9a8b7c6d5e4f3a2b1c0d9e8f', // Replace in production
        'allowed_image_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp'
        ],
        'allowed_image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_upload_bytes' => 2097152, // 2MB
    ],

    'storage' => [
        'logs_path' => dirname(__DIR__) . '/storage/logs',
        'uploads_path' => dirname(__DIR__) . '/storage/uploads',
        'candidates_dir' => dirname(__DIR__) . '/storage/uploads/candidates',
    ]
];
