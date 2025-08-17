<?php

namespace WorkflowSales\Pipeline;

use WorkflowSales\Scrapers\UniversidadPeruScraper;
use WorkflowSales\Validators\EmailValidator;
use WorkflowSales\Enrichers\LeadEnricher;
use League\Csv\Writer;

class LeadGenerator
{
    private $config;
    private $logger;
    private $scraper;
    private $validator;
    private $enricher;
    
    public function __construct($config, $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
        
        // Inicializar componentes
        $this->scraper = new UniversidadPeruScraper($logger);
        $this->validator = new EmailValidator($config['email_verification'] ?? [], $logger);
        $this->enricher = new LeadEnricher($config, $logger);
    }
    
    /**
     * Generar leads para una categoría
     */
    public function generate($category, $limit = 100)
    {
        $leads = [];
        
        // PASO 1: Scraping de empresas
        if ($this->logger) {
            $this->logger->info("Iniciando scraping de $category");
        }
        
        $empresas = $this->scraper->scrapeByCategory($category, $limit);
        
        if ($this->logger) {
            $this->logger->info("Empresas encontradas: " . count($empresas));
        }
        
        // PASO 2: Procesar cada empresa
        foreach ($empresas as $empresa) {
            $lead = $this->processEmpresa($empresa);
            
            if ($lead && $lead['score'] >= ($this->config['filters']['min_score'] ?? 0)) {
                $leads[] = $lead;
            }
        }
        
        // PASO 3: Ordenar por score
        usort($leads, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $leads;
    }
    
    /**
     * Procesar una empresa individual
     */
    public function processEmpresa($empresa)
    {
        $lead = $empresa;
        
        // Inicializar score
        $lead['score'] = 0;
        $lead['emails'] = [];
        $lead['emails_valid'] = [];
        $lead['decision_maker'] = null;
        
        // Si tiene dominio, generar y verificar emails
        if (!empty($empresa['dominio'])) {
            // Dominio válido +20 puntos
            $lead['score'] += 20;
            
            // Generar emails probables
            $emails = $this->validator->generateEmails($empresa['dominio']);
            $lead['emails'] = $emails;
            
            // Verificar emails (limitado para no demorar)
            $emailsToCheck = array_slice($emails, 0, 5); // Solo verificar los primeros 5
            foreach ($emailsToCheck as $email) {
                if ($this->validator->validate($email)) {
                    $lead['emails_valid'][] = $email;
                }
            }
            
            // Si tiene emails válidos +25 puntos
            if (!empty($lead['emails_valid'])) {
                $lead['score'] += 25;
                
                // Identificar decision makers
                $decisionMakers = $this->validator->identifyDecisionMakers($lead['emails_valid']);
                $lead['decision_maker'] = $decisionMakers['best'] ?? null;
                
                // Si encontramos decision maker +25 puntos
                if ($lead['decision_maker']) {
                    $lead['score'] += 25;
                }
            }
        }
        
        // Enriquecer con datos adicionales
        $lead = $this->enricher->enrich($lead);
        
        // Logging
        if ($this->logger) {
            $this->logger->debug("Procesado: {$lead['nombre']} - Score: {$lead['score']}");
        }
        
        return $lead;
    }
    
    /**
     * Buscar empresas por RUC específicos
     */
    public function searchByRucs($rucs)
    {
        $leads = [];
        
        foreach ($rucs as $ruc) {
            $empresa = $this->scraper->searchByRuc($ruc);
            
            if ($empresa) {
                $lead = $this->processEmpresa($empresa);
                if ($lead) {
                    $leads[] = $lead;
                }
            }
            
            // Delay entre búsquedas
            sleep(2);
        }
        
        return $leads;
    }
    
    /**
     * Exportar leads a CSV
     */
    public function export($leads, $filename)
    {
        // Crear directorio si no existe
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Determinar formato
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if ($extension === 'json') {
            $this->exportJson($leads, $filename);
        } else {
            $this->exportCsv($leads, $filename);
        }
    }
    
    /**
     * Exportar a CSV
     */
    private function exportCsv($leads, $filename)
    {
        $csv = Writer::createFromPath($filename, 'w+');
        
        // Headers
        $headers = [
            'RUC',
            'Nombre',
            'Dominio',
            'Email Principal',
            'Emails Válidos',
            'Decision Maker',
            'Teléfono',
            'Dirección',
            'Actividad',
            'Score',
            'Tiene GMB',
            'Necesita Marketing'
        ];
        
        $csv->insertOne($headers);
        
        // Datos
        foreach ($leads as $lead) {
            $row = [
                $lead['ruc'] ?? '',
                $lead['nombre'] ?? '',
                $lead['dominio'] ?? '',
                $lead['emails_valid'][0] ?? '',
                implode('; ', $lead['emails_valid'] ?? []),
                $lead['decision_maker'] ?? '',
                $lead['telefono'] ?? '',
                $lead['direccion'] ?? '',
                $lead['actividad'] ?? '',
                $lead['score'] ?? 0,
                $lead['has_gmb'] ?? 'No verificado',
                $lead['needs_marketing'] ?? 'Sí'
            ];
            
            $csv->insertOne($row);
        }
        
        if ($this->logger) {
            $this->logger->info("Exportado a CSV: $filename");
        }
    }
    
    /**
     * Exportar a JSON
     */
    private function exportJson($leads, $filename)
    {
        $json = json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($filename, $json);
        
        if ($this->logger) {
            $this->logger->info("Exportado a JSON: $filename");
        }
    }
}