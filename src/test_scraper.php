<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client([
    'timeout' => 30,
    'verify' => false,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]
]);

echo "Probando scraper de UniversidadPeru.com\n";
echo "========================================\n\n";

// Obtener página principal de empresas
$response = $client->get('https://www.universidadperu.com/empresas/');
$html = $response->getBody()->getContents();

$crawler = new Crawler($html);

// Buscar enlaces a empresas
$empresas = [];
$crawler->filter('a[href^="/empresas/"]')->each(function (Crawler $node) use (&$empresas) {
    $href = $node->attr('href');
    $text = $node->text();
    
    // Filtrar solo empresas (no categorías ni otras páginas)
    if (preg_match('/\/empresas\/([\w-]+)\.php$/', $href, $matches)) {
        $slug = $matches[1];
        
        // Excluir páginas especiales
        $exclude = ['categorias', 'busqueda', 'ranking'];
        if (!in_array($slug, $exclude) && !strpos($slug, 'categoria')) {
            $empresas[] = [
                'slug' => $slug,
                'nombre' => trim($text),
                'url' => 'https://www.universidadperu.com' . $href
            ];
        }
    }
});

echo "Empresas encontradas: " . count($empresas) . "\n\n";

// Mostrar primeras 5 empresas
echo "Primeras 5 empresas:\n";
foreach (array_slice($empresas, 0, 5) as $empresa) {
    echo "- " . $empresa['nombre'] . "\n";
    echo "  URL: " . $empresa['url'] . "\n\n";
}

// Probar scraping de una empresa específica
if (count($empresas) > 0) {
    echo "\nProbando scraping de empresa individual...\n";
    echo "==========================================\n";
    
    $empresaUrl = $empresas[0]['url'];
    echo "Empresa: " . $empresas[0]['nombre'] . "\n";
    
    try {
        $response = $client->get($empresaUrl);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        // Extraer datos
        $data = [];
        
        // RUC
        $rucElement = $crawler->filter('td:contains("RUC")');
        if ($rucElement->count() > 0) {
            $rucText = $rucElement->nextAll()->text();
            if (preg_match('/(\d{11})/', $rucText, $matches)) {
                $data['ruc'] = $matches[1];
            }
        }
        
        // Teléfono
        $phoneElement = $crawler->filter('td:contains("Teléfono")');
        if ($phoneElement->count() > 0) {
            $data['telefono'] = trim($phoneElement->nextAll()->text());
        }
        
        // Dirección
        $addressElement = $crawler->filter('td:contains("Dirección")');
        if ($addressElement->count() > 0) {
            $data['direccion'] = trim($addressElement->nextAll()->text());
        }
        
        // Web
        $webLinks = $crawler->filter('a[href*="http"]');
        if ($webLinks->count() > 0) {
            $webLinks->each(function($node) use (&$data) {
                $href = $node->attr('href');
                if (!strpos($href, 'universidadperu.com')) {
                    $data['web'] = $href;
                    return false; // stop iteration
                }
            });
        }
        
        echo "\nDatos extraídos:\n";
        print_r($data);
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}