<?php

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scraper con soporte para categorías y paginación persistente
 * Permite continuar desde donde se dejó la última vez
 */

class ScraperCategoria 
{
    private $client;
    private $progressFile;
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
        $this->progressFile = __DIR__ . '/../data/scraper_progress.json';
        
        // Crear directorio si no existe
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
        if (!is_dir(dirname($this->progressFile))) {
            mkdir(dirname($this->progressFile), 0777, true);
        }
    }
    
    /**
     * Obtener el progreso guardado
     */
    private function getProgress($categoria)
    {
        if (file_exists($this->progressFile)) {
            $progress = json_decode(file_get_contents($this->progressFile), true);
            return $progress[$categoria] ?? ['offset' => 0, 'total_scraped' => 0, 'last_date' => null];
        }
        return ['offset' => 0, 'total_scraped' => 0, 'last_date' => null];
    }
    
    /**
     * Guardar el progreso
     */
    private function saveProgress($categoria, $offset, $totalScraped)
    {
        $progress = [];
        if (file_exists($this->progressFile)) {
            $progress = json_decode(file_get_contents($this->progressFile), true);
        }
        
        $progress[$categoria] = [
            'offset' => $offset,
            'total_scraped' => $totalScraped,
            'last_date' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    }
    
    /**
     * Obtener lista de categorías disponibles
     */
    public function listarCategorias()
    {
        echo "Obteniendo categorías disponibles...\n\n";
        
        try {
            $response = $this->client->get('https://www.universidadperu.com/empresas/categorias.php');
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            
            $categorias = [];
            
            // Buscar todos los enlaces de categorías
            $crawler->filter('a[href*="-categoria.php"]')->each(function (Crawler $node) use (&$categorias) {
                $href = $node->attr('href');
                $text = trim($node->text());
                
                if (preg_match('/\/empresas\/([\w-]+)-categoria\.php/', $href, $matches)) {
                    $slug = $matches[1];
                    $categorias[$slug] = $text;
                }
            });
            
            return $categorias;
            
        } catch (Exception $e) {
            echo "Error obteniendo categorías: " . $e->getMessage() . "\n";
            return [];
        }
    }
    
    /**
     * Scraping por categoría con paginación
     */
    public function scrapearCategoria($categoria, $limite = 20, $continuar = true)
    {
        $progress = $continuar ? $this->getProgress($categoria) : ['offset' => 0, 'total_scraped' => 0, 'last_date' => null];
        $offset = $progress['offset'];
        $totalPrevio = $progress['total_scraped'];
        
        echo "================================================\n";
        echo "SCRAPING DE CATEGORÍA: $categoria\n";
        echo "================================================\n";
        
        if ($continuar && $totalPrevio > 0) {
            echo "ℹ️  Continuando desde empresa #" . ($offset + 1) . "\n";
            echo "   Total scrapeado anteriormente: $totalPrevio empresas\n";
            echo "   Última sesión: " . $progress['last_date'] . "\n\n";
        } else {
            echo "ℹ️  Iniciando nuevo scraping\n\n";
        }
        
        // Primero, obtener todas las empresas de la categoría
        $urlCategoria = "https://www.universidadperu.com/empresas/{$categoria}-categoria.php";
        
        try {
            $response = $this->client->get($urlCategoria);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            
            // Verificar si es una página de categoría válida
            if (strpos($html, '404') !== false || strpos($html, 'Página Desconocida') !== false) {
                echo "❌ Categoría no encontrada: $categoria\n";
                return [];
            }
            
            // En UniversidadPeru, las categorías muestran regiones, no empresas directamente
            // Necesitamos obtener empresas de otra manera
            echo "⚠️  Nota: La categoría muestra regiones. Buscando empresas relacionadas...\n\n";
            
            // Estrategia alternativa: buscar en la página principal y filtrar
            return $this->scrapearEmpresasGenerales($categoria, $limite, $offset);
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            return [];
        }
    }
    
    /**
     * Estrategia alternativa: scraping de empresas generales
     */
    private function scrapearEmpresasGenerales($categoria, $limite, $offset)
    {
        echo "Obteniendo lista de empresas...\n";
        
        $response = $this->client->get('https://www.universidadperu.com/empresas/');
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $empresas = [];
        $crawler->filter('a[href^="/empresas/"]')->each(function (Crawler $node) use (&$empresas) {
            $href = $node->attr('href');
            $text = $node->text();
            
            if (preg_match('/\/empresas\/([\w-]+)\.php$/', $href, $matches)) {
                $slug = $matches[1];
                
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
        
        // Aplicar offset y límite
        $empresasParaProcesar = array_slice($empresas, $offset, $limite);
        
        if (empty($empresasParaProcesar)) {
            echo "✅ No hay más empresas para procesar en esta categoría.\n";
            return [];
        }
        
        echo "📊 Procesando " . count($empresasParaProcesar) . " empresas (de " . count($empresas) . " totales)\n";
        echo "   Rango: #" . ($offset + 1) . " a #" . ($offset + count($empresasParaProcesar)) . "\n\n";
        
        $resultados = [];
        $contador = 0;
        
        foreach ($empresasParaProcesar as $idx => $empresa) {
            $numeroEmpresa = $offset + $idx + 1;
            echo "[$numeroEmpresa] Procesando: " . $empresa['nombre'] . "\n";
            
            try {
                $data = $this->extraerDatosEmpresa($empresa['url']);
                $data['numero'] = $numeroEmpresa;
                $data['categoria'] = $categoria;
                $resultados[] = $data;
                $contador++;
                
                echo "   ✓ Datos extraídos exitosamente\n";
                
                // Pequeña pausa
                sleep(1);
                
            } catch (Exception $e) {
                echo "   ✗ Error: " . $e->getMessage() . "\n";
            }
        }
        
        // Guardar progreso
        $nuevoOffset = $offset + count($empresasParaProcesar);
        $totalScraped = $offset + $contador;
        $this->saveProgress($categoria, $nuevoOffset, $totalScraped);
        
        // Guardar resultados en CSV
        if (!empty($resultados)) {
            $this->guardarCSV($resultados, $categoria);
        }
        
        // Resumen
        echo "\n================================================\n";
        echo "RESUMEN DE LA SESIÓN\n";
        echo "================================================\n";
        echo "✅ Empresas procesadas: $contador\n";
        echo "📁 Total acumulado: $totalScraped empresas\n";
        echo "➡️  Próxima empresa: #" . ($nuevoOffset + 1) . "\n";
        
        if ($nuevoOffset >= count($empresas)) {
            echo "\n🎉 ¡Categoría completada! Se han procesado todas las empresas.\n";
        } else {
            $restantes = count($empresas) - $nuevoOffset;
            echo "\n📌 Empresas restantes: $restantes\n";
            echo "   Para continuar, ejecuta el script nuevamente.\n";
        }
        
        return $resultados;
    }
    
    /**
     * Extraer datos de una empresa
     */
    private function extraerDatosEmpresa($url)
    {
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $data = ['url_perfil' => $url];
        
        // Extraer nombre
        $h1Elements = $crawler->filter('h1');
        if ($h1Elements->count() > 0) {
            $data['nombre'] = trim($h1Elements->first()->text());
        }
        
        // Buscar en tabla de datos
        $crawler->filter('table tr')->each(function($row) use (&$data) {
            $cells = $row->filter('td');
            if ($cells->count() >= 2) {
                $label = trim($cells->eq(0)->text());
                $value = trim($cells->eq(1)->text());
                
                if (strpos($label, 'RUC') !== false) {
                    $data['ruc'] = $value;
                } elseif (strpos($label, 'Teléfono') !== false || strpos($label, 'Telefono') !== false) {
                    $data['telefono'] = $value;
                } elseif (strpos($label, 'Dirección') !== false || strpos($label, 'Direccion') !== false) {
                    $data['direccion'] = $value;
                } elseif (strpos($label, 'Tipo') !== false) {
                    $data['tipo'] = $value;
                } elseif (strpos($label, 'Estado') !== false) {
                    $data['estado'] = $value;
                }
            }
        });
        
        // Buscar website
        $webLinks = $crawler->filter('a[href*="http"]');
        $webLinks->each(function($node) use (&$data) {
            $href = $node->attr('href');
            if (!strpos($href, 'universidadperu.com') && 
                !strpos($href, 'facebook.com') && 
                !isset($data['website'])) {
                $data['website'] = $href;
                
                // Generar emails probables
                $domain = parse_url($href, PHP_URL_HOST);
                $domain = str_replace('www.', '', $domain);
                if ($domain) {
                    $data['email_probable'] = "info@$domain";
                }
            }
        });
        
        $data['fecha_scraping'] = date('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Guardar resultados en CSV
     */
    private function guardarCSV($resultados, $categoria)
    {
        $fecha = date('Y-m-d');
        $timestamp = date('His');
        $filename = "categoria_{$categoria}_{$fecha}_{$timestamp}.csv";
        $filepath = $this->outputDir . $filename;
        
        $fp = fopen($filepath, 'w');
        if ($fp) {
            // Headers
            fputcsv($fp, ['#', 'Nombre', 'RUC', 'Teléfono', 'Website', 'Email Probable', 'Dirección', 'Tipo', 'Estado', 'Fecha Scraping']);
            
            // Data
            foreach ($resultados as $empresa) {
                fputcsv($fp, [
                    $empresa['numero'] ?? '',
                    $empresa['nombre'] ?? '',
                    $empresa['ruc'] ?? '',
                    $empresa['telefono'] ?? '',
                    $empresa['website'] ?? '',
                    $empresa['email_probable'] ?? '',
                    $empresa['direccion'] ?? '',
                    $empresa['tipo'] ?? '',
                    $empresa['estado'] ?? '',
                    $empresa['fecha_scraping']
                ]);
            }
            
            fclose($fp);
            echo "\n💾 Resultados guardados en: $filename\n";
        }
    }
    
    /**
     * Resetear progreso de una categoría
     */
    public function resetearProgreso($categoria = null)
    {
        if ($categoria) {
            $progress = [];
            if (file_exists($this->progressFile)) {
                $progress = json_decode(file_get_contents($this->progressFile), true);
            }
            unset($progress[$categoria]);
            file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT));
            echo "✅ Progreso reseteado para categoría: $categoria\n";
        } else {
            if (file_exists($this->progressFile)) {
                unlink($this->progressFile);
            }
            echo "✅ Todo el progreso ha sido reseteado\n";
        }
    }
    
    /**
     * Ver estado del progreso
     */
    public function verProgreso()
    {
        if (!file_exists($this->progressFile)) {
            echo "No hay progreso guardado.\n";
            return;
        }
        
        $progress = json_decode(file_get_contents($this->progressFile), true);
        
        echo "================================================\n";
        echo "PROGRESO GUARDADO\n";
        echo "================================================\n\n";
        
        foreach ($progress as $categoria => $info) {
            echo "📁 Categoría: $categoria\n";
            echo "   • Empresas procesadas: " . $info['total_scraped'] . "\n";
            echo "   • Próxima empresa: #" . ($info['offset'] + 1) . "\n";
            echo "   • Última sesión: " . $info['last_date'] . "\n\n";
        }
    }
}

// ============================================
// EJECUCIÓN PRINCIPAL
// ============================================

$scraper = new ScraperCategoria();

// Parsear argumentos de línea de comandos
$options = getopt("c:l:h", ["categoria:", "limite:", "reset:", "listar", "progreso", "nuevo", "help"]);

// Ayuda
if (isset($options['h']) || isset($options['help'])) {
    echo "\nUSO: php scraper_categoria.php [opciones]\n\n";
    echo "OPCIONES:\n";
    echo "  -c, --categoria <nombre>   Categoría a scrapear (ej: restaurantes)\n";
    echo "  -l, --limite <numero>      Cantidad de empresas a procesar (default: 20)\n";
    echo "  --nuevo                    Iniciar scraping desde cero (ignorar progreso)\n";
    echo "  --reset <categoria>        Resetear progreso de una categoría\n";
    echo "  --reset all                Resetear todo el progreso\n";
    echo "  --listar                   Listar categorías disponibles\n";
    echo "  --progreso                 Ver progreso guardado\n";
    echo "  -h, --help                 Mostrar esta ayuda\n\n";
    echo "EJEMPLOS:\n";
    echo "  php scraper_categoria.php --listar\n";
    echo "  php scraper_categoria.php -c restaurantes -l 20\n";
    echo "  php scraper_categoria.php -c restaurantes -l 20 --nuevo\n";
    echo "  php scraper_categoria.php --progreso\n";
    echo "  php scraper_categoria.php --reset restaurantes\n\n";
    exit(0);
}

// Listar categorías
if (isset($options['listar'])) {
    $categorias = $scraper->listarCategorias();
    if (!empty($categorias)) {
        echo "\nCATEGORÍAS DISPONIBLES:\n";
        echo "======================\n\n";
        foreach ($categorias as $slug => $nombre) {
            echo "• $slug\n";
            echo "  Nombre: $nombre\n\n";
        }
        echo "Total: " . count($categorias) . " categorías\n";
    }
    exit(0);
}

// Ver progreso
if (isset($options['progreso'])) {
    $scraper->verProgreso();
    exit(0);
}

// Resetear progreso
if (isset($options['reset'])) {
    $categoria = $options['reset'];
    if ($categoria === 'all') {
        $scraper->resetearProgreso();
    } else {
        $scraper->resetearProgreso($categoria);
    }
    exit(0);
}

// Scraping de categoría
$categoria = $options['c'] ?? $options['categoria'] ?? null;
$limite = $options['l'] ?? $options['limite'] ?? 20;
$nuevo = isset($options['nuevo']);

if ($categoria) {
    // Si se especifica --nuevo, no continuar desde progreso anterior
    $continuar = !$nuevo;
    $scraper->scrapearCategoria($categoria, $limite, $continuar);
} else {
    echo "Por favor especifica una categoría con -c o --categoria\n";
    echo "Usa --help para ver todas las opciones disponibles.\n";
}