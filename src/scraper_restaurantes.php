<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

/**
 * Scraper especializado para restaurantes y negocios locales
 * Usa búsqueda directa en lugar de categorías
 */

class ScraperRestaurantes
{
    private $client;
    private $outputDir;
    
    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $this->outputDir = __DIR__ . '/../data/output/';
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Buscar restaurantes usando RUCs conocidos
     */
    public function buscarRestaurantesPorRUC()
    {
        echo "================================================\n";
        echo "BUSCADOR DE RESTAURANTES POR RUC\n";
        echo "================================================\n\n";
        
        // Lista de RUCs de restaurantes conocidos en Perú
        // Puedes agregar más RUCs aquí
        $restaurantesConocidos = [
            '20505670443' => 'La Lucha Sanguchería',
            '20100123763' => 'Pardo\'s Chicken',
            '20513571560' => 'Bembos',
            '20100130204' => 'Pizza Hut Perú',
            '20505377142' => 'Papa John\'s',
            '20492193869' => 'KFC Perú',
            '20101952961' => 'McDonald\'s Perú',
            '20518126874' => 'China Wok',
            '20514883026' => 'Subway Perú',
            '20506217716' => 'Starbucks Perú',
            '20100035392' => 'Norky\'s',
            '20517716301' => 'Roky\'s',
            '20100070970' => 'Burger King Perú',
            '20544817486' => 'Popeyes',
            '20552334311' => 'Dunkin Donuts',
            '20509507151' => 'Tanta',
            '20512528008' => 'La Mar',
            '20551856767' => 'Central Restaurante',
            '20550154065' => 'Maido',
            '20601327318' => 'Isolina'
        ];
        
        $resultados = [];
        $contador = 0;
        
        foreach ($restaurantesConocidos as $ruc => $nombreConocido) {
            $contador++;
            echo "[$contador] Buscando: $nombreConocido (RUC: $ruc)\n";
            
            try {
                // Buscar en UniversidadPeru por RUC
                $data = $this->buscarPorRUC($ruc);
                
                if ($data) {
                    $data['nombre_conocido'] = $nombreConocido;
                    $data['categoria'] = 'Restaurante';
                    $resultados[] = $data;
                    echo "   ✓ Datos encontrados\n";
                } else {
                    echo "   ✗ No encontrado en la base de datos\n";
                }
                
                sleep(1); // Pausa entre búsquedas
                
            } catch (Exception $e) {
                echo "   ✗ Error: " . $e->getMessage() . "\n";
            }
        }
        
        // Guardar resultados
        if (!empty($resultados)) {
            $this->guardarCSV($resultados, 'restaurantes_peru');
            
            echo "\n================================================\n";
            echo "RESUMEN\n";
            echo "================================================\n";
            echo "✅ Restaurantes encontrados: " . count($resultados) . " de $contador\n";
            echo "📁 Archivo guardado en: data/output/\n";
        }
        
        return $resultados;
    }
    
    /**
     * Buscar empresa por RUC en UniversidadPeru
     */
    private function buscarPorRUC($ruc)
    {
        // Intentar búsqueda directa
        $url = "https://www.universidadperu.com/empresas/busqueda.php";
        
        try {
            $response = $this->client->post($url, [
                'form_params' => [
                    'buscaempresa' => $ruc
                ]
            ]);
            
            $html = $response->getBody()->getContents();
            
            // Parsear resultados
            if (strpos($html, 'No se encontraron') === false && strpos($html, $ruc) !== false) {
                return $this->extraerDatos($html, $ruc);
            }
            
        } catch (Exception $e) {
            // Silenciar errores de búsqueda individual
        }
        
        return null;
    }
    
    /**
     * Extraer datos del HTML
     */
    private function extraerDatos($html, $ruc)
    {
        $data = ['ruc' => $ruc];
        
        // Extraer nombre
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
            $data['nombre'] = trim(strip_tags($matches[1]));
        }
        
        // Extraer dirección
        if (preg_match('/Direcci[oó]n[^<]*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $matches)) {
            $data['direccion'] = trim(strip_tags($matches[1]));
        }
        
        // Extraer teléfono
        if (preg_match('/Tel[eé]fono[^<]*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $matches)) {
            $data['telefono'] = trim(strip_tags($matches[1]));
        }
        
        // Extraer estado
        if (preg_match('/Estado[^<]*<\/td>\s*<td[^>]*>([^<]+)/i', $html, $matches)) {
            $data['estado'] = trim(strip_tags($matches[1]));
        }
        
        // Generar emails probables para restaurantes
        if (isset($data['nombre'])) {
            $nombreLimpio = strtolower(preg_replace('/[^a-z0-9]/i', '', $data['nombre']));
            $data['emails_probables'] = [
                "reservas@{$nombreLimpio}.com.pe",
                "contacto@{$nombreLimpio}.com.pe",
                "info@{$nombreLimpio}.com.pe"
            ];
        }
        
        $data['fecha_busqueda'] = date('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Buscar restaurantes por término de búsqueda
     */
    public function buscarPorTermino($termino, $limite = 20)
    {
        echo "================================================\n";
        echo "BÚSQUEDA: $termino\n";
        echo "================================================\n\n";
        
        // Lista de términos relacionados con restaurantes
        $terminos = [
            $termino,
            "restaurante $termino",
            "restaurant $termino",
            "comida $termino",
            "food $termino"
        ];
        
        $resultados = [];
        $empresasUnicas = [];
        
        foreach ($terminos as $busqueda) {
            echo "Buscando: $busqueda\n";
            
            try {
                $url = "https://www.universidadperu.com/empresas/busqueda.php";
                $response = $this->client->post($url, [
                    'form_params' => [
                        'buscaempresa' => $busqueda
                    ]
                ]);
                
                $html = $response->getBody()->getContents();
                
                // Extraer enlaces a empresas
                if (preg_match_all('/<a href="\/empresas\/([^"]+)\.php"[^>]*>([^<]+)<\/a>/i', $html, $matches)) {
                    for ($i = 0; $i < count($matches[1]) && count($resultados) < $limite; $i++) {
                        $slug = $matches[1][$i];
                        $nombre = trim(strip_tags($matches[2][$i]));
                        
                        // Evitar duplicados
                        if (!isset($empresasUnicas[$slug])) {
                            $empresasUnicas[$slug] = true;
                            
                            echo "  • Encontrado: $nombre\n";
                            
                            // Obtener detalles
                            $detalles = $this->obtenerDetallesEmpresa($slug);
                            if ($detalles) {
                                $detalles['termino_busqueda'] = $termino;
                                $detalles['categoria_probable'] = $this->determinarCategoria($nombre);
                                $resultados[] = $detalles;
                            }
                            
                            sleep(1);
                        }
                    }
                }
                
            } catch (Exception $e) {
                echo "  ✗ Error en búsqueda: " . $e->getMessage() . "\n";
            }
            
            if (count($resultados) >= $limite) {
                break;
            }
        }
        
        // Guardar resultados
        if (!empty($resultados)) {
            $filename = 'busqueda_' . preg_replace('/[^a-z0-9]/i', '_', $termino);
            $this->guardarCSV($resultados, $filename);
            
            echo "\n================================================\n";
            echo "RESUMEN\n";
            echo "================================================\n";
            echo "✅ Empresas encontradas: " . count($resultados) . "\n";
            echo "📁 Archivo guardado en: data/output/\n";
        } else {
            echo "\n❌ No se encontraron resultados para: $termino\n";
        }
        
        return $resultados;
    }
    
    /**
     * Obtener detalles de una empresa específica
     */
    private function obtenerDetallesEmpresa($slug)
    {
        try {
            $url = "https://www.universidadperu.com/empresas/{$slug}.php";
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
            
            return $this->extraerDatos($html, '');
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Determinar categoría probable basada en el nombre
     */
    private function determinarCategoria($nombre)
    {
        $nombre = strtolower($nombre);
        
        $categorias = [
            'Restaurante' => ['restaurant', 'restaurante', 'cevicheria', 'polleria', 'pizzeria', 'chifa'],
            'Fast Food' => ['burger', 'pizza', 'chicken', 'pollo', 'sandwich', 'hot dog'],
            'Cafetería' => ['cafe', 'coffee', 'cafeteria', 'starbucks', 'dunkin'],
            'Bar' => ['bar', 'pub', 'cerveceria', 'brewery'],
            'Panadería' => ['panaderia', 'pasteleria', 'bakery'],
            'Heladería' => ['helado', 'ice cream', 'gelateria'],
            'Comida China' => ['china', 'chifa', 'wok', 'oriental'],
            'Comida Japonesa' => ['sushi', 'japonesa', 'nikkei', 'ramen'],
            'Comida Criolla' => ['criolla', 'anticucho', 'ceviche', 'criollo']
        ];
        
        foreach ($categorias as $categoria => $palabras) {
            foreach ($palabras as $palabra) {
                if (strpos($nombre, $palabra) !== false) {
                    return $categoria;
                }
            }
        }
        
        return 'Alimentos y Bebidas';
    }
    
    /**
     * Guardar resultados en CSV
     */
    private function guardarCSV($resultados, $prefijo)
    {
        $fecha = date('Y-m-d_His');
        $filename = "{$prefijo}_{$fecha}.csv";
        $filepath = $this->outputDir . $filename;
        
        $fp = fopen($filepath, 'w');
        if ($fp) {
            // Headers
            fputcsv($fp, [
                'Nombre',
                'RUC',
                'Categoría',
                'Dirección',
                'Teléfono',
                'Estado',
                'Email Probable',
                'Fecha Búsqueda'
            ]);
            
            // Data
            foreach ($resultados as $empresa) {
                $email = '';
                if (isset($empresa['emails_probables']) && is_array($empresa['emails_probables'])) {
                    $email = $empresa['emails_probables'][0];
                }
                
                fputcsv($fp, [
                    $empresa['nombre'] ?? $empresa['nombre_conocido'] ?? '',
                    $empresa['ruc'] ?? '',
                    $empresa['categoria'] ?? $empresa['categoria_probable'] ?? '',
                    $empresa['direccion'] ?? '',
                    $empresa['telefono'] ?? '',
                    $empresa['estado'] ?? '',
                    $email,
                    $empresa['fecha_busqueda'] ?? date('Y-m-d H:i:s')
                ]);
            }
            
            fclose($fp);
            echo "\n💾 Resultados guardados en: $filename\n";
        }
    }
}

// ============================================
// EJECUCIÓN PRINCIPAL
// ============================================

$scraper = new ScraperRestaurantes();

// Parsear argumentos
$options = getopt("t:l:h", ["termino:", "limite:", "rucs", "help"]);

// Ayuda
if (isset($options['h']) || isset($options['help'])) {
    echo "\nUSO: php scraper_restaurantes.php [opciones]\n\n";
    echo "OPCIONES:\n";
    echo "  --rucs                     Buscar restaurantes conocidos por RUC\n";
    echo "  -t, --termino <palabra>    Buscar por término (ej: pizza, ceviche)\n";
    echo "  -l, --limite <numero>      Límite de resultados (default: 20)\n";
    echo "  -h, --help                 Mostrar esta ayuda\n\n";
    echo "EJEMPLOS:\n";
    echo "  php scraper_restaurantes.php --rucs\n";
    echo "  php scraper_restaurantes.php -t pizza -l 10\n";
    echo "  php scraper_restaurantes.php -t \"comida criolla\"\n";
    echo "  php scraper_restaurantes.php -t cevicheria\n\n";
    exit(0);
}

// Buscar restaurantes conocidos por RUC
if (isset($options['rucs'])) {
    $scraper->buscarRestaurantesPorRUC();
    exit(0);
}

// Buscar por término
$termino = $options['t'] ?? $options['termino'] ?? null;
$limite = $options['l'] ?? $options['limite'] ?? 20;

if ($termino) {
    $scraper->buscarPorTermino($termino, $limite);
} else {
    echo "Usa --rucs para buscar restaurantes conocidos\n";
    echo "O usa -t <término> para buscar por palabra clave\n";
    echo "Ejemplo: php scraper_restaurantes.php -t pizza\n";
    echo "\nUsa --help para ver todas las opciones.\n";
}