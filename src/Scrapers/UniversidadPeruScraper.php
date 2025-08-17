<?php

namespace WorkflowSales\Scrapers;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class UniversidadPeruScraper
{
    private $client;
    private $baseUrl = 'https://www.universidadperu.com/empresas/';
    private $logger;
    private $delay;
    
    public function __construct($logger = null, $delay = 2)
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        $this->logger = $logger;
        $this->delay = $delay;
    }
    
    /**
     * Buscar empresa por RUC
     */
    public function searchByRuc($ruc)
    {
        try {
            $url = $this->baseUrl . "search.php?ruc=" . $ruc;
            $html = $this->fetchPage($url);
            
            if (!$html) {
                return null;
            }
            
            return $this->parseEmpresaData($html, $ruc);
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error buscando RUC $ruc: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Scraping por categoría
     */
    public function scrapeByCategory($category, $limit = 100)
    {
        $empresas = [];
        $page = 1;
        $maxPages = ceil($limit / 20); // Aproximadamente 20 empresas por página
        
        while ($page <= $maxPages && count($empresas) < $limit) {
            try {
                $url = $this->baseUrl . $category . ".php?page=" . $page;
                
                if ($this->logger) {
                    $this->logger->info("Scrapeando página $page de $category");
                }
                
                $html = $this->fetchPage($url);
                if (!$html) {
                    break;
                }
                
                $crawler = new Crawler($html);
                
                // Buscar links a empresas individuales
                $links = $crawler->filter('a[href*="/empresas/"]')->each(function (Crawler $node) {
                    $href = $node->attr('href');
                    if (preg_match('/\/empresas\/(.*?)\.php/', $href, $matches)) {
                        return $matches[1];
                    }
                    return null;
                });
                
                // Filtrar nulls
                $links = array_filter($links);
                
                // Scraping de cada empresa
                foreach ($links as $slug) {
                    if (count($empresas) >= $limit) {
                        break;
                    }
                    
                    $empresa = $this->scrapeEmpresa($slug);
                    if ($empresa && !empty($empresa['ruc'])) {
                        $empresas[] = $empresa;
                        
                        if ($this->logger) {
                            $this->logger->debug("Empresa encontrada: " . $empresa['nombre']);
                        }
                    }
                    
                    // Delay entre requests
                    sleep($this->delay);
                }
                
                $page++;
                
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error("Error en página $page: " . $e->getMessage());
                }
                break;
            }
        }
        
        return $empresas;
    }
    
    /**
     * Scraping de empresa individual
     */
    public function scrapeEmpresa($slug)
    {
        try {
            $url = $this->baseUrl . $slug . ".php";
            $html = $this->fetchPage($url);
            
            if (!$html) {
                return null;
            }
            
            return $this->parseEmpresaData($html);
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error scrapeando empresa $slug: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Parsear datos de empresa desde HTML
     */
    private function parseEmpresaData($html, $ruc = null)
    {
        $crawler = new Crawler($html);
        $data = [];
        
        // RUC
        if ($ruc) {
            $data['ruc'] = $ruc;
        } else {
            $rucText = $crawler->filter('td:contains("RUC")')->count() > 0 
                ? $crawler->filter('td:contains("RUC")')->nextAll()->text() 
                : null;
            
            if ($rucText && preg_match('/(\d{11})/', $rucText, $matches)) {
                $data['ruc'] = $matches[1];
            }
        }
        
        // Nombre
        $h1 = $crawler->filter('h1')->count() > 0 
            ? $crawler->filter('h1')->text() 
            : null;
        
        if ($h1) {
            $data['nombre'] = trim($h1);
        }
        
        // Página web
        $webLink = $crawler->filter('a[href*="http"]')->count() > 0 
            ? $crawler->filter('a[href*="http"]')->first() 
            : null;
        
        if ($webLink) {
            $url = $webLink->attr('href');
            $data['web'] = $url;
            $data['dominio'] = $this->extractDomain($url);
        }
        
        // Teléfono
        $phoneText = $crawler->filter('td:contains("Teléfono")')->count() > 0 
            ? $crawler->filter('td:contains("Teléfono")')->nextAll()->text() 
            : null;
        
        if ($phoneText) {
            $data['telefono'] = trim($phoneText);
        }
        
        // Dirección
        $addressText = $crawler->filter('td:contains("Dirección")')->count() > 0 
            ? $crawler->filter('td:contains("Dirección")')->nextAll()->text() 
            : null;
        
        if ($addressText) {
            $data['direccion'] = trim($addressText);
        }
        
        // Actividad
        $activityText = $crawler->filter('td:contains("Actividad")')->count() > 0 
            ? $crawler->filter('td:contains("Actividad")')->nextAll()->text() 
            : null;
        
        if ($activityText) {
            $data['actividad'] = trim($activityText);
        }
        
        return empty($data) ? null : $data;
    }
    
    /**
     * Fetch página con reintentos
     */
    private function fetchPage($url, $retries = 3)
    {
        for ($i = 0; $i < $retries; $i++) {
            try {
                $response = $this->client->get($url);
                if ($response->getStatusCode() === 200) {
                    return $response->getBody()->getContents();
                }
            } catch (\Exception $e) {
                if ($i === $retries - 1) {
                    throw $e;
                }
                sleep(5); // Esperar antes de reintentar
            }
        }
        
        return null;
    }
    
    /**
     * Extraer dominio limpio de URL
     */
    private function extractDomain($url)
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return null;
        }
        
        $host = $parsed['host'];
        
        // Remover www.
        $host = preg_replace('/^www\./', '', $host);
        
        return $host;
    }
}