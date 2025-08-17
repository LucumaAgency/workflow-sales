<?php

return [
    // Configuración de scrapers
    'scrapers' => [
        'universidadperu' => [
            'enabled' => true,
            'base_url' => 'https://www.universidadperu.com/empresas/',
            'categories' => [
                'restaurantes-peru',
                'hoteles-peru',
                'clinicas-peru',
                'colegios-peru',
                'inmobiliarias-peru',
                'farmacias-peru'
            ]
        ],
        'datosperu' => [
            'enabled' => false,
            'base_url' => 'https://www.datosperu.org/'
        ],
        'sunat' => [
            'enabled' => true,
            'padron_url' => 'http://www.sunat.gob.pe/descargaPRR/padron_reducido_ruc.zip'
        ]
    ],
    
    // Verificación de emails
    'email_verification' => [
        'smtp_check' => true,
        'dns_check' => true,
        'syntax_check' => true,
        'disposable_check' => true,
        'timeout' => 5
    ],
    
    // Patrones de email para Perú
    'email_patterns' => [
        // Genéricos
        'info@{domain}',
        'ventas@{domain}',
        'contacto@{domain}',
        'administracion@{domain}',
        
        // Decision makers
        'gerencia@{domain}',
        'gerente@{domain}',
        'director@{domain}',
        'ceo@{domain}',
        'marketing@{domain}',
        'comercial@{domain}',
        
        // Con .pe
        'info@{domain}.pe',
        'ventas@{domain}.pe',
        'contacto@{domain}.pe'
    ],
    
    // Rate limiting
    'rate_limits' => [
        'requests_per_second' => 1,
        'delay_between_requests' => 2,
        'delay_between_batches' => 10,
        'max_concurrent_requests' => 5
    ],
    
    // Lead scoring
    'scoring' => [
        'has_website' => 20,
        'no_gmb' => 30,
        'verified_email' => 25,
        'decision_maker_found' => 25,
        'recent_company' => 20,
        'hiring_marketing' => 30
    ],
    
    // Filtros
    'filters' => [
        'min_score' => 50,
        'exclude_gmail' => false,
        'only_decision_makers' => false,
        'industries' => [] // vacío = todas
    ],
    
    // Output
    'output' => [
        'format' => 'csv', // csv, json, both
        'directory' => __DIR__ . '/../data/output/',
        'include_score' => true,
        'include_metadata' => true
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'file' => __DIR__ . '/../logs/app.log',
        'max_files' => 7
    ],
    
    // Database (opcional)
    'database' => [
        'enabled' => false,
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'workflow_sales',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ]
];