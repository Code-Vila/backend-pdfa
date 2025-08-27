<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF/A Converter Configuration
    |--------------------------------------------------------------------------
    */

    // Limite diário de conversões para IPs não expandidos
    'daily_limit' => env('PDFA_DAILY_LIMIT', 10),

    // Caminho de armazenamento dos arquivos
    'storage_path' => env('PDFA_STORAGE_PATH', 'pdfa'),

    // Tamanho máximo do arquivo em KB
    'max_file_size' => env('PDFA_MAX_FILE_SIZE', 10240), // 10MB

    // Email do administrador para notificações
    'admin_email' => env('ADMIN_EMAIL', 'admin@atrim.com'),

    // Rate limiting
    'rate_limit_daily_conversions' => env('RATE_LIMIT_DAILY_CONVERSIONS', 10),
    'rate_limit_expansion_requests' => env('RATE_LIMIT_EXPANSION_REQUESTS', 3),

    // Tempo de retenção dos arquivos (dias)
    'file_retention_days' => env('PDFA_FILE_RETENTION_DAYS', 7),

    // Configurações do Ghostscript
    'ghostscript' => [
        'binary_path' => env('GS_BINARY_PATH', 'gs'),
        'pdf_version' => env('PDFA_VERSION', '1'), // PDF/A-1, PDF/A-2, etc.
        'color_conversion' => env('PDFA_COLOR_CONVERSION', 'RGB'),
    ],

    // Configurações de validação
    'validation' => [
        'allowed_extensions' => ['pdf'],
        'allowed_mime_types' => ['application/pdf', 'application/x-pdf'],
        'max_pages' => env('PDFA_MAX_PAGES', 1000),
    ],
];
