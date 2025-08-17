#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use WorkflowSales\Pipeline\LeadGenerator;
use WorkflowSales\Utils\Logger;
use WorkflowSales\Utils\Config;

// Configurar timezone
date_default_timezone_set('America/Lima');

// Cargar configuración
$config = Config::load();

// Inicializar logger
$logger = new Logger($config['logging']);

// Banner
echo "\n";
echo "╔════════════════════════════════════════╗\n";
echo "║     WORKFLOW SALES - LEAD GENERATOR    ║\n";
echo "║         Pipeline B2B para Perú         ║\n";
echo "╚════════════════════════════════════════╝\n\n";

// Parsear argumentos
$options = getopt("c:l:o:h", ["category:", "limit:", "output:", "help"]);

if (isset($options['h']) || isset($options['help'])) {
    showHelp();
    exit(0);
}

$category = $options['c'] ?? $options['category'] ?? 'restaurantes-bares-y-cantinas-categoria';
$limit = $options['l'] ?? $options['limit'] ?? 100;
$output = $options['o'] ?? $options['output'] ?? 'data/output/leads_' . date('Y-m-d_H-i-s') . '.csv';

try {
    $logger->info("Iniciando generación de leads");
    $logger->info("Categoría: $category, Límite: $limit");
    
    // Inicializar generador
    $generator = new LeadGenerator($config, $logger);
    
    // Generar leads
    echo "🔍 Buscando empresas en categoría: $category\n";
    $leads = $generator->generate($category, $limit);
    
    // Mostrar resumen
    echo "\n📊 RESUMEN DE RESULTADOS:\n";
    echo "────────────────────────\n";
    echo "Total empresas encontradas: " . count($leads) . "\n";
    
    $with_email = array_filter($leads, function($lead) {
        return !empty($lead['emails']);
    });
    echo "Con emails verificados: " . count($with_email) . "\n";
    
    $decision_makers = array_filter($leads, function($lead) {
        return !empty($lead['decision_maker']);
    });
    echo "Decision makers identificados: " . count($decision_makers) . "\n";
    
    $hot_leads = array_filter($leads, function($lead) {
        return $lead['score'] >= 80;
    });
    echo "Hot leads (score >= 80): " . count($hot_leads) . "\n";
    
    // Guardar resultados
    echo "\n💾 Guardando resultados en: $output\n";
    $generator->export($leads, $output);
    
    // Top 5 leads
    echo "\n🔥 TOP 5 HOT LEADS:\n";
    echo "────────────────────\n";
    
    $top_leads = array_slice($leads, 0, 5);
    foreach ($top_leads as $i => $lead) {
        echo ($i + 1) . ". {$lead['nombre']}\n";
        echo "   RUC: {$lead['ruc']}\n";
        echo "   Score: {$lead['score']}\n";
        echo "   Email: " . ($lead['decision_maker'] ?? $lead['emails'][0] ?? 'N/A') . "\n";
        echo "   Web: " . ($lead['dominio'] ?? 'N/A') . "\n\n";
    }
    
    $logger->info("Proceso completado exitosamente");
    echo "✅ Proceso completado exitosamente!\n\n";
    
} catch (Exception $e) {
    $logger->error("Error: " . $e->getMessage());
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function showHelp() {
    echo "Uso: php main.php [opciones]\n\n";
    echo "Opciones:\n";
    echo "  -c, --category    Categoría a buscar (default: restaurantes-peru)\n";
    echo "  -l, --limit       Límite de empresas (default: 100)\n";
    echo "  -o, --output      Archivo de salida (default: data/output/leads_[timestamp].csv)\n";
    echo "  -h, --help        Mostrar esta ayuda\n\n";
    echo "Categorías disponibles:\n";
    echo "  - restaurantes-peru\n";
    echo "  - hoteles-peru\n";
    echo "  - clinicas-peru\n";
    echo "  - colegios-peru\n";
    echo "  - inmobiliarias-peru\n";
    echo "  - farmacias-peru\n";
}