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

echo "Scraper Mejorado - UniversidadPeru.com\n";
echo "=======================================\n\n";

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

// Procesar primeras 3 empresas con extracción mejorada
$limite = min(3, count($empresas));
$resultados = [];

echo "Extrayendo datos detallados de $limite empresas...\n";
echo "================================================\n\n";

for ($i = 0; $i < $limite; $i++) {
    $empresa = $empresas[$i];
    echo ($i + 1) . ". Procesando: " . $empresa['nombre'] . "\n";
    
    try {
        $response = $client->get($empresa['url']);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        // Extraer datos mejorado
        $data = [
            'nombre_listado' => $empresa['nombre'],
            'url_perfil' => $empresa['url']
        ];
        
        // Buscar en tabla de datos
        $rows = $crawler->filter('table tr');
        $rows->each(function($row) use (&$data) {
            $cells = $row->filter('td');
            if ($cells->count() >= 2) {
                $label = trim($cells->eq(0)->text());
                $value = trim($cells->eq(1)->text());
                
                if (strpos($label, 'RUC') !== false) {
                    $data['ruc'] = $value;
                } elseif (strpos($label, 'Razón Social') !== false || strpos($label, 'Razon Social') !== false) {
                    $data['razon_social'] = $value;
                } elseif (strpos($label, 'Nombre Comercial') !== false) {
                    $data['nombre_comercial'] = $value;
                } elseif (strpos($label, 'Tipo') !== false) {
                    $data['tipo'] = $value;
                } elseif (strpos($label, 'Fecha') !== false) {
                    $data['fecha_inicio'] = $value;
                } elseif (strpos($label, 'Estado') !== false) {
                    $data['estado'] = $value;
                } elseif (strpos($label, 'Dirección') !== false || strpos($label, 'Direccion') !== false) {
                    $data['direccion'] = $value;
                } elseif (strpos($label, 'Teléfono') !== false || strpos($label, 'Telefono') !== false) {
                    $data['telefono'] = $value;
                } elseif (strpos($label, 'Departamento') !== false) {
                    $data['departamento'] = $value;
                } elseif (strpos($label, 'Provincia') !== false) {
                    $data['provincia'] = $value;
                } elseif (strpos($label, 'Distrito') !== false) {
                    $data['distrito'] = $value;
                }
            }
        });
        
        // Buscar título H1
        $h1Elements = $crawler->filter('h1');
        if ($h1Elements->count() > 0) {
            $data['nombre_h1'] = trim($h1Elements->first()->text());
        }
        
        // Buscar sitio web
        $webLinks = $crawler->filter('a[href*="http"]');
        if ($webLinks->count() > 0) {
            $webLinks->each(function($node) use (&$data) {
                $href = $node->attr('href');
                // Excluir links internos y redes sociales comunes
                if (!strpos($href, 'universidadperu.com') && 
                    !strpos($href, 'facebook.com') && 
                    !strpos($href, 'twitter.com') &&
                    !strpos($href, 'instagram.com') &&
                    !isset($data['website'])) {
                    $data['website'] = $href;
                    return false; // stop iteration
                }
            });
        }
        
        // Buscar emails en el texto completo
        $text = $crawler->text();
        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $emails = array_unique($matches[0]);
            // Filtrar emails que no sean del sitio
            $emails = array_filter($emails, function($email) {
                return !strpos($email, 'universidadperu.com');
            });
            if (!empty($emails)) {
                $data['emails_encontrados'] = array_values($emails);
            }
        }
        
        // Generar emails probables si tiene website
        if (isset($data['website']) && !isset($data['emails_encontrados'])) {
            $domain = parse_url($data['website'], PHP_URL_HOST);
            $domain = str_replace('www.', '', $domain);
            if ($domain) {
                $data['emails_probables'] = [
                    "info@$domain",
                    "ventas@$domain",
                    "contacto@$domain",
                    "administracion@$domain"
                ];
            }
        }
        
        // Calcular score básico
        $score = 0;
        if (isset($data['ruc'])) $score += 20;
        if (isset($data['telefono'])) $score += 15;
        if (isset($data['direccion'])) $score += 15;
        if (isset($data['website'])) $score += 25;
        if (isset($data['emails_encontrados']) || isset($data['emails_probables'])) $score += 25;
        $data['score'] = $score;
        
        $resultados[] = $data;
        
        // Mostrar datos extraídos
        echo "   ✓ Datos extraídos:\n";
        foreach ($data as $key => $value) {
            if ($key == 'emails_probables' || $key == 'emails_encontrados') {
                echo "     - $key: " . implode(', ', $value) . "\n";
            } else {
                echo "     - $key: $value\n";
            }
        }
        echo "\n";
        
        // Pequeña pausa entre requests
        sleep(1);
        
    } catch (Exception $e) {
        echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Resumen final
echo "\n================================================\n";
echo "RESUMEN FINAL\n";
echo "================================================\n";
echo "Total empresas procesadas: " . count($resultados) . "\n";
echo "Empresas con website: " . count(array_filter($resultados, function($r) { return isset($r['website']); })) . "\n";
echo "Empresas con RUC: " . count(array_filter($resultados, function($r) { return isset($r['ruc']); })) . "\n";
echo "Empresas con teléfono: " . count(array_filter($resultados, function($r) { return isset($r['telefono']); })) . "\n\n";

// Mostrar top empresas por score
usort($resultados, function($a, $b) {
    return $b['score'] - $a['score'];
});

echo "TOP EMPRESAS POR SCORE:\n";
foreach ($resultados as $idx => $empresa) {
    echo ($idx + 1) . ". " . ($empresa['nombre_h1'] ?? $empresa['nombre_listado']) . " (Score: " . $empresa['score'] . ")\n";
    if (isset($empresa['website'])) {
        echo "   Website: " . $empresa['website'] . "\n";
    }
    if (isset($empresa['emails_probables'])) {
        echo "   Emails probables: " . implode(', ', array_slice($empresa['emails_probables'], 0, 2)) . "\n";
    }
}

// Guardar resultados en CSV
$csvFile = __DIR__ . '/../data/output/scraper_mejorado_' . date('Y-m-d_H-i-s') . '.csv';
$dir = dirname($csvFile);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$fp = fopen($csvFile, 'w');
if ($fp) {
    // Headers
    fputcsv($fp, ['Nombre', 'RUC', 'Website', 'Teléfono', 'Dirección', 'Email', 'Score']);
    
    // Data
    foreach ($resultados as $empresa) {
        $email = '';
        if (isset($empresa['emails_encontrados'])) {
            $email = $empresa['emails_encontrados'][0];
        } elseif (isset($empresa['emails_probables'])) {
            $email = $empresa['emails_probables'][0];
        }
        
        fputcsv($fp, [
            $empresa['nombre_h1'] ?? $empresa['nombre_listado'],
            $empresa['ruc'] ?? '',
            $empresa['website'] ?? '',
            $empresa['telefono'] ?? '',
            $empresa['direccion'] ?? '',
            $email,
            $empresa['score']
        ]);
    }
    
    fclose($fp);
    echo "\n✅ Resultados guardados en: $csvFile\n";
}

echo "\n¡Proceso completado exitosamente!\n";