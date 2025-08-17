<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/EmailVerifier.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use WorkflowSales\EmailVerifier;

/**
 * Scraper jerárquico para UniversidadPeru
 * Navega: Categoría -> Departamento -> Provincia -> Distrito -> Empresas
 */

class ScraperJerarquico
{
    private $client;
    private $outputDir;
    private $progressFile;
    private $baseUrl = 'https://www.universidadperu.com/empresas/';
    private $emailVerifier;
    
    // Mapeo de departamentos con sus códigos
    private $departamentos = [
        '01' => 'AMAZONAS',
        '02' => 'ANCASH',
        '03' => 'APURIMAC',
        '04' => 'AREQUIPA',
        '05' => 'AYACUCHO',
        '06' => 'CAJAMARCA',
        '07' => 'CUSCO',
        '08' => 'HUANCAVELICA',
        '09' => 'HUANUCO',
        '10' => 'ICA',
        '11' => 'JUNIN',
        '12' => 'LA LIBERTAD',
        '13' => 'LAMBAYEQUE',
        '14' => 'LIMA',
        '15' => 'LORETO',
        '16' => 'MADRE DE DIOS',
        '17' => 'MOQUEGUA',
        '18' => 'PASCO',
        '19' => 'PIURA',
        '20' => 'PROV. CONST. DEL CALLAO',
        '21' => 'PUNO',
        '22' => 'SAN MARTIN',
        '23' => 'TACNA',
        '24' => 'TUMBES',
        '25' => 'UCAYALI'
    ];
    
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
        $this->progressFile = __DIR__ . '/../data/scraper_jerarquico_progress.json';
        $this->emailVerifier = new EmailVerifier();
        
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Listar todas las categorías disponibles
     */
    public function listarCategorias()
    {
        echo "Obteniendo categorías disponibles...\n\n";
        
        $response = $this->client->get($this->baseUrl . 'categorias.php');
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $categorias = [];
        
        $crawler->filter('a[href*="-categoria.php"]')->each(function (Crawler $node) use (&$categorias) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            if (preg_match('/\/([\w-]+)-categoria\.php/', $href, $matches)) {
                $slug = $matches[1];
                $categorias[$slug] = $text;
            }
        });
        
        return $categorias;
    }
    
    /**
     * Scrapear una categoría específica con navegación jerárquica
     */
    public function scrapearCategoria($categoriaSlug, $departamento = null, $provincia = null, $distrito = null)
    {
        echo "================================================\n";
        echo "SCRAPING JERÁRQUICO\n";
        echo "================================================\n";
        echo "Categoría: $categoriaSlug\n";
        
        if ($departamento) {
            echo "Departamento: " . ($this->departamentos[$departamento] ?? $departamento) . "\n";
        }
        if ($provincia) {
            echo "Provincia: $provincia\n";
        }
        if ($distrito) {
            echo "Distrito: $distrito\n";
        }
        
        echo "\n";
        
        $urlBase = $this->baseUrl . $categoriaSlug . '-categoria.php';
        
        // Si no se especifica departamento, listar todos
        if (!$departamento) {
            return $this->listarDepartamentos($urlBase, $categoriaSlug);
        }
        
        // Si no se especifica provincia, listar provincias del departamento
        if (!$provincia) {
            return $this->listarProvincias($urlBase, $categoriaSlug, $departamento);
        }
        
        // Si no se especifica distrito, listar distritos de la provincia
        if (!$distrito) {
            return $this->listarDistritos($urlBase, $categoriaSlug, $departamento, $provincia);
        }
        
        // Si se especifica todo, scrapear empresas del distrito
        return $this->scrapearEmpresas($urlBase, $categoriaSlug, $departamento, $provincia, $distrito);
    }
    
    /**
     * Listar departamentos de una categoría
     */
    private function listarDepartamentos($urlBase, $categoria)
    {
        echo "Obteniendo departamentos...\n\n";
        
        $response = $this->client->get($urlBase);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $departamentos = [];
        
        $crawler->filter('a[href*="?dist="]')->each(function (Crawler $node) use (&$departamentos) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            if (preg_match('/\?dist=(\d{2})$/', $href, $matches)) {
                $codigo = $matches[1];
                $departamentos[$codigo] = $text;
            }
        });
        
        echo "DEPARTAMENTOS DISPONIBLES:\n";
        echo "==========================\n\n";
        
        foreach ($departamentos as $codigo => $nombre) {
            echo sprintf("  %s - %s\n", $codigo, $nombre);
        }
        
        echo "\nTotal: " . count($departamentos) . " departamentos\n";
        echo "\nPara scrapear un departamento específico, usa:\n";
        echo "  php scraper_jerarquico.php -c $categoria -d [código]\n";
        echo "Ejemplo:\n";
        echo "  php scraper_jerarquico.php -c $categoria -d 14  (para Lima)\n";
        
        return $departamentos;
    }
    
    /**
     * Listar provincias de un departamento
     */
    private function listarProvincias($urlBase, $categoria, $codDepartamento)
    {
        echo "Obteniendo provincias del departamento {$this->departamentos[$codDepartamento]}...\n\n";
        
        $url = $urlBase . '?dist=' . $codDepartamento;
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $provincias = [];
        
        $crawler->filter('a[href*="?dist=' . $codDepartamento . '"]')->each(function (Crawler $node) use (&$provincias, $codDepartamento) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            // Buscar códigos de provincia (4 dígitos)
            if (preg_match('/\?dist=(' . $codDepartamento . '\d{2})$/', $href, $matches)) {
                $codigo = $matches[1];
                $provincias[$codigo] = $text;
            }
        });
        
        echo "PROVINCIAS DE {$this->departamentos[$codDepartamento]}:\n";
        echo "=====================================\n\n";
        
        foreach ($provincias as $codigo => $nombre) {
            echo sprintf("  %s - %s\n", $codigo, $nombre);
        }
        
        echo "\nTotal: " . count($provincias) . " provincias\n";
        echo "\nPara scrapear una provincia específica, usa:\n";
        echo "  php scraper_jerarquico.php -c $categoria -d $codDepartamento -p [código]\n";
        echo "Ejemplo:\n";
        echo "  php scraper_jerarquico.php -c $categoria -d $codDepartamento -p " . array_key_first($provincias) . "\n";
        
        return $provincias;
    }
    
    /**
     * Listar distritos de una provincia
     */
    private function listarDistritos($urlBase, $categoria, $codDepartamento, $codProvincia)
    {
        echo "Obteniendo distritos de la provincia $codProvincia...\n\n";
        
        $url = $urlBase . '?dist=' . $codProvincia;
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $distritos = [];
        
        $crawler->filter('a[href*="?dist=' . $codProvincia . '"]')->each(function (Crawler $node) use (&$distritos, $codProvincia) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            // Buscar códigos de distrito (6 dígitos)
            if (preg_match('/\?dist=(' . $codProvincia . '\d{2})$/', $href, $matches)) {
                $codigo = $matches[1];
                $distritos[$codigo] = $text;
            }
        });
        
        echo "DISTRITOS DE PROVINCIA $codProvincia:\n";
        echo "=====================================\n\n";
        
        foreach ($distritos as $codigo => $nombre) {
            echo sprintf("  %s - %s\n", $codigo, $nombre);
        }
        
        echo "\nTotal: " . count($distritos) . " distritos\n";
        echo "\nPara scrapear empresas de un distrito, usa:\n";
        echo "  php scraper_jerarquico.php -c $categoria -d $codDepartamento -p $codProvincia -t [código]\n";
        echo "Ejemplo:\n";
        if (!empty($distritos)) {
            echo "  php scraper_jerarquico.php -c $categoria -d $codDepartamento -p $codProvincia -t " . array_key_first($distritos) . "\n";
        }
        
        return $distritos;
    }
    
    /**
     * Scrapear empresas de un distrito específico
     */
    private function scrapearEmpresas($urlBase, $categoria, $codDepartamento, $codProvincia, $codDistrito)
    {
        echo "Scrapeando empresas del distrito $codDistrito...\n\n";
        
        $url = $urlBase . '?dist=' . $codDistrito;
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $empresas = [];
        
        // Buscar enlaces a empresas individuales
        $crawler->filter('a[href^="/empresas/"]')->each(function (Crawler $node) use (&$empresas, $categoria, $codDepartamento, $codProvincia, $codDistrito) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            // Filtrar solo enlaces a empresas (no categorías ni navegación)
            if (preg_match('/\/empresas\/([\w-]+)\.php$/', $href, $matches)) {
                $slug = $matches[1];
                
                // Excluir páginas especiales
                $exclude = ['categorias', 'busqueda', 'ranking'];
                if (!in_array($slug, $exclude) && !strpos($slug, 'categoria')) {
                    $empresas[] = [
                        'slug' => $slug,
                        'nombre' => $text,
                        'url' => 'https://www.universidadperu.com' . $href,
                        'categoria' => $categoria,
                        'departamento' => $this->departamentos[$codDepartamento] ?? $codDepartamento,
                        'cod_departamento' => $codDepartamento,
                        'cod_provincia' => $codProvincia,
                        'cod_distrito' => $codDistrito
                    ];
                }
            }
        });
        
        echo "Empresas encontradas: " . count($empresas) . "\n\n";
        
        if (empty($empresas)) {
            echo "❌ No se encontraron empresas en este distrito.\n";
            return [];
        }
        
        // Procesar cada empresa para obtener detalles
        $resultados = [];
        foreach ($empresas as $idx => $empresa) {
            echo "[" . ($idx + 1) . "/" . count($empresas) . "] Procesando: " . $empresa['nombre'] . "\n";
            
            try {
                $detalles = $this->obtenerDetallesEmpresa($empresa['url']);
                $empresaCompleta = array_merge($empresa, $detalles);
                $resultados[] = $empresaCompleta;
                
                echo "   ✓ Datos extraídos\n";
                sleep(1);
                
            } catch (Exception $e) {
                echo "   ✗ Error: " . $e->getMessage() . "\n";
            }
        }
        
        // Guardar resultados
        if (!empty($resultados)) {
            $this->guardarCSV($resultados, $categoria, $codDepartamento, $codDistrito);
        }
        
        return $resultados;
    }
    
    /**
     * Obtener detalles de una empresa
     */
    private function obtenerDetallesEmpresa($url)
    {
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $data = [];
        
        // Buscar datos en tabla - Extracción mejorada
        $crawler->filter('table tr')->each(function($row) use (&$data) {
            $cells = $row->filter('td');
            if ($cells->count() >= 2) {
                $label = trim($cells->eq(0)->text());
                $value = trim($cells->eq(1)->text());
                
                // Limpiar el label de dos puntos
                $label = str_replace(':', '', $label);
                
                if (strpos($label, 'RUC') !== false) {
                    $data['ruc'] = $value;
                } elseif (strpos($label, 'Razón Social') !== false || strpos($label, 'Razon Social') !== false) {
                    $data['razon_social'] = $value;
                } elseif (strpos($label, 'Nombre Comercial') !== false) {
                    $data['nombre_comercial'] = $value;
                } elseif (strpos($label, 'Tipo Empresa') !== false) {
                    $data['tipo_empresa'] = $value;
                } elseif (strpos($label, 'Condición') !== false && strpos($label, 'Domicilio') === false) {
                    $data['condicion'] = $value;
                } elseif (strpos($label, 'Fecha Inicio') !== false) {
                    $data['fecha_inicio'] = $value;
                } elseif (strpos($label, 'Actividad Comercial') !== false) {
                    $data['actividad_comercial'] = $value;
                } elseif (strpos($label, 'CIIU') !== false) {
                    $data['ciiu'] = $value;
                } elseif (strpos($label, 'Dirección Legal') !== false || (strpos($label, 'Dirección') !== false && strpos($label, 'Legal') === false)) {
                    $data['direccion'] = $value;
                } elseif (strpos($label, 'Urbanizacion') !== false || strpos($label, 'Urbanización') !== false) {
                    $data['urbanizacion'] = $value;
                } elseif (strpos($label, 'Distrito') !== false && strpos($label, 'Ciudad') !== false) {
                    $data['distrito_ciudad'] = $value;
                } elseif (strpos($label, 'Provincia') !== false && strpos($label, 'Departamento') === false) {
                    $data['provincia_detalle'] = $value;
                } elseif (strpos($label, 'Departamento') !== false) {
                    $data['departamento_detalle'] = $value;
                } elseif (strpos($label, 'Teléfono') !== false || strpos($label, 'Telefono') !== false) {
                    $data['telefono'] = $value;
                } elseif (strpos($label, 'Estado') !== false && strpos($label, 'Domicilio') !== false) {
                    $data['estado_domicilio'] = $value;
                } elseif (strpos($label, 'Estado') !== false) {
                    $data['estado'] = $value;
                }
            }
        });
        
        // Buscar website (excluyendo redes sociales)
        $webLinks = $crawler->filter('a[href*="http"]');
        $webLinks->each(function($node) use (&$data) {
            $href = $node->attr('href');
            
            // Lista de dominios a excluir (redes sociales, directorios, etc)
            $dominiosExcluidos = [
                'facebook.com',
                'twitter.com',
                'instagram.com',
                'linkedin.com',
                'youtube.com',
                'tiktok.com',
                'pinterest.com',
                'universidadperu.com',
                'wikipedia.org',
                'google.com',
                'yahoo.com',
                'bing.com'
            ];
            
            $esExcluido = false;
            foreach ($dominiosExcluidos as $dominio) {
                if (strpos($href, $dominio) !== false) {
                    $esExcluido = true;
                    break;
                }
            }
            
            if (!$esExcluido && !isset($data['website'])) {
                $data['website'] = $href;
                
                // Solo generar emails si es un dominio corporativo real
                $domain = parse_url($href, PHP_URL_HOST);
                $domain = str_replace('www.', '', $domain);
                
                // Verificar que sea un dominio corporativo válido
                if ($domain && !$this->esDominioGenerico($domain)) {
                    // Generar múltiples emails probables
                    $emailsProbables = [
                        "info@$domain",
                        "contacto@$domain",
                        "ventas@$domain"
                    ];
                    
                    // Si es restaurante, agregar emails específicos
                    if (isset($data['actividad_comercial']) && 
                        (stripos($data['actividad_comercial'], 'restaurant') !== false ||
                         stripos($data['actividad_comercial'], 'bar') !== false ||
                         stripos($data['actividad_comercial'], 'cantina') !== false)) {
                        $emailsProbables[] = "reservas@$domain";
                        $emailsProbables[] = "pedidos@$domain";
                    }
                    
                    // Verificar cuál existe
                    $emailValido = null;
                    $scoreMaximo = 0;
                    
                    foreach ($emailsProbables as $email) {
                        $verificacion = $this->emailVerifier->verificar($email);
                        if ($verificacion['score'] > $scoreMaximo) {
                            $scoreMaximo = $verificacion['score'];
                            $emailValido = $email;
                            $data['email_score'] = $verificacion['score'];
                            $data['email_estado'] = $verificacion['estado'];
                        }
                    }
                    
                    if ($emailValido && $scoreMaximo >= 50) { // Solo si tiene score decente
                        $data['email_probable'] = $emailValido;
                        $data['email_verificado'] = ($data['email_estado'] == 'valido');
                    }
                } else {
                    // Si no hay website válido, intentar generar email basado en nombre
                    if (isset($data['nombre_comercial']) && isset($data['ruc'])) {
                        $nombreLimpio = $this->limpiarNombreParaDominio($data['nombre_comercial']);
                        if ($nombreLimpio) {
                            $data['email_sugerido'] = "contacto@{$nombreLimpio}.com.pe";
                            $data['email_nota'] = 'Email sugerido (sin website)';
                        }
                    }
                }
            }
        });
        
        $data['fecha_scraping'] = date('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Guardar resultados en CSV
     */
    private function guardarCSV($resultados, $categoria, $departamento, $distrito)
    {
        $fecha = date('Y-m-d_His');
        $filename = "{$categoria}_{$departamento}_{$distrito}_{$fecha}.csv";
        $filepath = $this->outputDir . $filename;
        
        $fp = fopen($filepath, 'w');
        if ($fp) {
            // Headers
            fputcsv($fp, [
                'Nombre',
                'RUC',
                'Razón Social',
                'Departamento',
                'Provincia',
                'Distrito',
                'Teléfono',
                'Website',
                'Email',
                'Email Verificado',
                'Email Score',
                'Estado',
                'Fecha Scraping'
            ]);
            
            // Data
            foreach ($resultados as $empresa) {
                // Determinar el email a mostrar
                $email = $empresa['email_probable'] ?? $empresa['email_sugerido'] ?? '';
                
                // Usar nombre comercial si existe, sino usar el nombre del listado
                $nombre = $empresa['nombre_comercial'] ?? $empresa['nombre'] ?? '';
                
                fputcsv($fp, [
                    $nombre,
                    $empresa['ruc'] ?? '',
                    $empresa['razon_social'] ?? '',
                    $empresa['departamento'] ?? '',
                    $empresa['cod_provincia'] ?? '',
                    $empresa['cod_distrito'] ?? '',
                    $empresa['telefono'] ?? '',
                    $empresa['website'] ?? '',
                    $email,
                    isset($empresa['email_verificado']) && $empresa['email_verificado'] ? 'Sí' : 'No',
                    $empresa['email_score'] ?? 0,
                    $empresa['condicion'] ?? $empresa['estado'] ?? '',
                    $empresa['fecha_scraping'] ?? ''
                ]);
            }
            
            fclose($fp);
            
            echo "\n================================================\n";
            echo "RESUMEN\n";
            echo "================================================\n";
            echo "✅ Empresas procesadas: " . count($resultados) . "\n";
            echo "💾 Archivo guardado: $filename\n";
        }
    }
    
    /**
     * Verificar si es un dominio genérico (no corporativo)
     */
    private function esDominioGenerico($domain)
    {
        $dominiosGenericos = [
            'gmail.com',
            'hotmail.com',
            'yahoo.com',
            'outlook.com',
            'live.com',
            'msn.com',
            'aol.com',
            'mail.com',
            'protonmail.com',
            'icloud.com'
        ];
        
        foreach ($dominiosGenericos as $generico) {
            if (strpos($domain, $generico) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Limpiar nombre comercial para generar dominio
     */
    private function limpiarNombreParaDominio($nombre)
    {
        // Quitar caracteres especiales y números
        $nombre = preg_replace('/[^a-zA-Z\s]/', '', $nombre);
        
        // Quitar palabras comunes de empresas
        $palabrasExcluir = ['EIRL', 'SAC', 'SA', 'SRL', 'SL', 'SCRL', 'CIA', 'RESTAURANT', 'RESTAURANTE', 'BAR', 'CEVICHERIA'];
        foreach ($palabrasExcluir as $palabra) {
            $nombre = str_ireplace($palabra, '', $nombre);
        }
        
        // Convertir a minúsculas y quitar espacios
        $nombre = strtolower(trim($nombre));
        $nombre = str_replace(' ', '', $nombre);
        
        // Si queda muy corto, no es útil
        if (strlen($nombre) < 3) {
            return null;
        }
        
        return substr($nombre, 0, 20); // Limitar longitud
    }
    
    /**
     * Scrapear todo Lima (ejemplo de scraping masivo)
     */
    public function scrapearLimaCompleto($categoria)
    {
        echo "================================================\n";
        echo "SCRAPING COMPLETO DE LIMA\n";
        echo "================================================\n\n";
        
        $codLima = '14';
        $urlBase = $this->baseUrl . $categoria . '-categoria.php';
        
        // Obtener todas las provincias de Lima
        $url = $urlBase . '?dist=' . $codLima;
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $provincias = [];
        $crawler->filter('a[href*="?dist=14"]')->each(function (Crawler $node) use (&$provincias) {
            $href = $node->attr('href');
            $text = trim($node->text());
            
            if (preg_match('/\?dist=(14\d{2})$/', $href, $matches)) {
                $codigo = $matches[1];
                $provincias[$codigo] = $text;
            }
        });
        
        echo "Provincias de Lima encontradas: " . count($provincias) . "\n\n";
        
        $totalEmpresas = 0;
        
        // Para cada provincia
        foreach ($provincias as $codProvincia => $nombreProvincia) {
            echo "\n📍 Procesando provincia: $nombreProvincia ($codProvincia)\n";
            echo "----------------------------------------\n";
            
            // Obtener distritos
            $url = $urlBase . '?dist=' . $codProvincia;
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            
            $distritos = [];
            $crawler->filter('a[href*="?dist=' . $codProvincia . '"]')->each(function (Crawler $node) use (&$distritos, $codProvincia) {
                $href = $node->attr('href');
                $text = trim($node->text());
                
                if (preg_match('/\?dist=(' . $codProvincia . '\d{2})$/', $href, $matches)) {
                    $codigo = $matches[1];
                    $distritos[$codigo] = $text;
                }
            });
            
            // Para cada distrito
            foreach ($distritos as $codDistrito => $nombreDistrito) {
                echo "  → Distrito: $nombreDistrito\n";
                
                $empresas = $this->scrapearEmpresas($urlBase, $categoria, $codLima, $codProvincia, $codDistrito);
                $totalEmpresas += count($empresas);
                
                // Pausa entre distritos
                sleep(2);
            }
        }
        
        echo "\n================================================\n";
        echo "SCRAPING COMPLETADO\n";
        echo "================================================\n";
        echo "✅ Total de empresas procesadas: $totalEmpresas\n";
        echo "📁 Archivos guardados en: data/output/\n";
    }
}

// ============================================
// EJECUCIÓN PRINCIPAL
// ============================================

$scraper = new ScraperJerarquico();

// Parsear argumentos
$options = getopt("c:d:p:t:lh", ["categoria:", "departamento:", "provincia:", "distrito:", "listar", "lima", "help"]);

// Ayuda
if (isset($options['h']) || isset($options['help'])) {
    echo "\nUSO: php scraper_jerarquico.php [opciones]\n\n";
    echo "NAVEGACIÓN JERÁRQUICA:\n";
    echo "  -c, --categoria <slug>     Categoría (ej: restaurantes-bares-y-cantinas)\n";
    echo "  -d, --departamento <cod>   Código de departamento (ej: 14 para Lima)\n";
    echo "  -p, --provincia <cod>      Código de provincia (ej: 1401)\n";
    echo "  -t, --distrito <cod>       Código de distrito (ej: 140101)\n";
    echo "  -l, --listar               Listar categorías disponibles\n";
    echo "  --lima                     Scrapear todo Lima (requiere -c)\n\n";
    echo "EJEMPLOS:\n";
    echo "  # Listar categorías\n";
    echo "  php scraper_jerarquico.php --listar\n\n";
    echo "  # Ver departamentos de una categoría\n";
    echo "  php scraper_jerarquico.php -c restaurantes-bares-y-cantinas\n\n";
    echo "  # Ver provincias de Lima\n";
    echo "  php scraper_jerarquico.php -c restaurantes-bares-y-cantinas -d 14\n\n";
    echo "  # Ver distritos de Lima provincia\n";
    echo "  php scraper_jerarquico.php -c restaurantes-bares-y-cantinas -d 14 -p 1401\n\n";
    echo "  # Scrapear empresas de un distrito\n";
    echo "  php scraper_jerarquico.php -c restaurantes-bares-y-cantinas -d 14 -p 1401 -t 140101\n\n";
    echo "  # Scrapear todo Lima\n";
    echo "  php scraper_jerarquico.php -c restaurantes-bares-y-cantinas --lima\n\n";
    echo "CÓDIGOS DE DEPARTAMENTOS:\n";
    echo "  01=Amazonas, 02=Ancash, 03=Apurímac, 04=Arequipa, 05=Ayacucho\n";
    echo "  06=Cajamarca, 07=Cusco, 08=Huancavelica, 09=Huánuco, 10=Ica\n";
    echo "  11=Junín, 12=La Libertad, 13=Lambayeque, 14=Lima, 15=Loreto\n";
    echo "  16=Madre de Dios, 17=Moquegua, 18=Pasco, 19=Piura, 20=Callao\n";
    echo "  21=Puno, 22=San Martín, 23=Tacna, 24=Tumbes, 25=Ucayali\n\n";
    exit(0);
}

// Listar categorías
if (isset($options['l']) || isset($options['listar'])) {
    $categorias = $scraper->listarCategorias();
    echo "\nCATEGORÍAS DISPONIBLES:\n";
    echo "======================\n\n";
    foreach ($categorias as $slug => $nombre) {
        echo "• $slug\n";
    }
    exit(0);
}

// Scraping
$categoria = $options['c'] ?? $options['categoria'] ?? null;

if (!$categoria) {
    echo "❌ Debes especificar una categoría con -c\n";
    echo "Usa --listar para ver las categorías disponibles.\n";
    echo "Usa --help para ver todas las opciones.\n";
    exit(1);
}

// Scrapear todo Lima
if (isset($options['lima'])) {
    $scraper->scrapearLimaCompleto($categoria);
    exit(0);
}

// Navegación jerárquica
$departamento = $options['d'] ?? $options['departamento'] ?? null;
$provincia = $options['p'] ?? $options['provincia'] ?? null;
$distrito = $options['t'] ?? $options['distrito'] ?? null;

$scraper->scrapearCategoria($categoria, $departamento, $provincia, $distrito);