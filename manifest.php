<?php
// Set header sebagai JSON
header('Content-Type: application/json');

// Load base_url dari config
require_once __DIR__ . '/config/config.php';

// Generate manifest JSON secara dinamis
$manifest = [
    "name" => "Ipenk Legend",
    "short_name" => "Ipenk Legend",
    "description" => "Sistem informasi manajemen produksi mukena dan koko.",
    "start_url" => $base_url . "/",
    "scope" => $base_url . "/",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => "#000000",
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => $base_url . "/icons/Logo-Ipenk.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any maskable"
        ],
        [
            "src" => $base_url . "/icons/Logo-Ipenk.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any maskable"
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
