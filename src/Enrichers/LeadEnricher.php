<?php

namespace WorkflowSales\Enrichers;

class LeadEnricher
{
    private $config;
    private $logger;
    
    public function __construct($config, $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Enriquecer lead con datos adicionales
     */
    public function enrich($lead)
    {
        // Verificar si necesita marketing
        $lead['needs_marketing'] = $this->needsMarketing($lead);
        
        // Calcular tamaño de empresa
        $lead['company_size'] = $this->estimateCompanySize($lead);
        
        // Identificar industria
        $lead['industry'] = $this->identifyIndustry($lead);
        
        // Score adicional basado en enriquecimiento
        if ($lead['needs_marketing']) {
            $lead['score'] += 15;
        }
        
        // Si es empresa nueva (podríamos verificar con SUNAT)
        if ($this->isNewCompany($lead)) {
            $lead['score'] += 20;
            $lead['is_new'] = true;
        }
        
        // Verificar presencia en Google (simulado)
        $lead['has_gmb'] = $this->checkGooglePresence($lead);
        if (!$lead['has_gmb']) {
            $lead['score'] += 30; // Gran oportunidad
        }
        
        return $lead;
    }
    
    /**
     * Determinar si necesita marketing
     */
    private function needsMarketing($lead)
    {
        // Sin website = definitivamente necesita
        if (empty($lead['dominio'])) {
            return true;
        }
        
        // Sin emails válidos = probablemente necesita
        if (empty($lead['emails_valid'])) {
            return true;
        }
        
        // Por industria (restaurantes, hoteles, etc siempre necesitan)
        $highNeedIndustries = ['restaurante', 'hotel', 'clinica', 'colegio', 'inmobiliaria'];
        foreach ($highNeedIndustries as $industry) {
            if (stripos($lead['actividad'] ?? '', $industry) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Estimar tamaño de empresa
     */
    private function estimateCompanySize($lead)
    {
        // Por tipo de email
        if (!empty($lead['emails_valid'])) {
            foreach ($lead['emails_valid'] as $email) {
                // Emails departamentales = empresa más grande
                if (preg_match('/(rrhh|finanzas|marketing|comercial|sistemas)@/', $email)) {
                    return 'mediana';
                }
            }
        }
        
        // Por dominio
        if (!empty($lead['dominio'])) {
            // Dominio propio .com.pe = más establecida
            if (strpos($lead['dominio'], '.com.pe') !== false) {
                return 'pequeña-mediana';
            }
        }
        
        // Por defecto
        return 'pequeña';
    }
    
    /**
     * Identificar industria
     */
    private function identifyIndustry($lead)
    {
        $industries = [
            'restaurante' => ['restaurante', 'cevicheria', 'polleria', 'chifa', 'pizzeria', 'cafe'],
            'hotel' => ['hotel', 'hostal', 'hospedaje', 'lodge', 'resort'],
            'salud' => ['clinica', 'centro medico', 'consultorio', 'laboratorio', 'odonto'],
            'educacion' => ['colegio', 'instituto', 'universidad', 'academia', 'centro educativo'],
            'retail' => ['tienda', 'boutique', 'store', 'venta', 'comercial'],
            'servicios' => ['consultoria', 'asesoria', 'agencia', 'estudio'],
            'inmobiliaria' => ['inmobiliaria', 'constructora', 'edificio', 'condominio'],
            'tecnologia' => ['software', 'sistemas', 'tecnologia', 'digital', 'web']
        ];
        
        $nombre = strtolower($lead['nombre'] ?? '');
        $actividad = strtolower($lead['actividad'] ?? '');
        $searchText = $nombre . ' ' . $actividad;
        
        foreach ($industries as $industry => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($searchText, $keyword) !== false) {
                    return $industry;
                }
            }
        }
        
        return 'otros';
    }
    
    /**
     * Verificar si es empresa nueva
     */
    private function isNewCompany($lead)
    {
        // Verificar patrones en el nombre
        $newPatterns = ['2024', '2023', 'new', 'nuevo', 'nova'];
        $nombre = strtolower($lead['nombre'] ?? '');
        
        foreach ($newPatterns as $pattern) {
            if (stripos($nombre, $pattern) !== false) {
                return true;
            }
        }
        
        // Sin presencia web = probablemente nueva
        if (empty($lead['dominio'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar presencia en Google (simulado)
     */
    private function checkGooglePresence($lead)
    {
        // En un caso real, haríamos una búsqueda en Google Maps API
        // Por ahora, simulamos basándonos en datos disponibles
        
        // Si tiene website, probablemente tiene GMB
        if (!empty($lead['dominio'])) {
            return rand(1, 100) > 30; // 70% probabilidad de tener GMB
        }
        
        // Sin website, probablemente no tiene GMB
        return rand(1, 100) > 80; // 20% probabilidad de tener GMB
    }
}