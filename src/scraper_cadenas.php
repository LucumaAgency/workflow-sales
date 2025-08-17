<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/EmailVerifier.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use WorkflowSales\EmailVerifier;

/**
 * Scraper para cadenas y empresas grandes que SÍ tienen website
 */

class ScraperCadenas
{
    private $client;
    private $outputDir;
    private $emailVerifier;
    
    // Lista de empresas conocidas con websites en Perú
    private $empresasConWebsite = [
        // Restaurantes y Fast Food
        'Bembos' => 'bembos.com.pe',
        'Papa Johns' => 'papajohns.com.pe',
        'Pizza Hut' => 'pizzahut.com.pe',
        'KFC' => 'kfc.com.pe',
        'Popeyes' => 'popeyes.com.pe',
        'Burger King' => 'burgerking.pe',
        'McDonalds' => 'mcdonalds.com.pe',
        'Subway' => 'subway.com',
        'Starbucks' => 'starbucks.com.pe',
        'Dunkin' => 'dunkindonuts.pe',
        'China Wok' => 'chinawok.com.pe',
        'Norkys' => 'norkys.pe',
        'Rokys' => 'rokys.com',
        'Pardos Chicken' => 'pardoschicken.pe',
        'La Lucha' => 'lalucha.com.pe',
        'Tanta' => 'tanta.com.pe',
        'La Mar' => 'lamarcebicheria.com',
        'Osaka' => 'osaka.com.pe',
        'Madam Tusan' => 'madamtusan.pe',
        'El Hornero' => 'elhornero.com.pe',
        'Segundo Muelle' => 'segundomuelle.com',
        'La Rosa Nautica' => 'larosanautica.com',
        'Astrid y Gaston' => 'astridygaston.com',
        'Central Restaurante' => 'centralrestaurante.com.pe',
        'Maido' => 'maido.pe',
        
        // Retail y Supermercados
        'Plaza Vea' => 'plazavea.com.pe',
        'Metro' => 'metro.pe',
        'Wong' => 'wong.pe',
        'Vivanda' => 'vivanda.com.pe',
        'Tottus' => 'tottus.com.pe',
        'Oechsle' => 'oechsle.pe',
        'Ripley' => 'ripley.com.pe',
        'Saga Falabella' => 'sagafalabella.com.pe',
        'Promart' => 'promart.pe',
        'Sodimac' => 'sodimac.com.pe',
        'Maestro' => 'maestro.com.pe',
        
        // Farmacias
        'Inkafarma' => 'inkafarma.pe',
        'Mifarma' => 'mifarma.com.pe',
        'Boticas Peru' => 'boticasperu.com',
        'Farmacia Universal' => 'farmaciauniversal.com',
        
        // Hoteles
        'Casa Andina' => 'casa-andina.com',
        'Costa del Sol' => 'costadelsolperu.com',
        'Sonesta' => 'sonesta.com/peru',
        'Hilton' => 'hilton.com',
        'Marriott' => 'marriott.com',
        'Sheraton' => 'marriott.com',
        'Belmond' => 'belmond.com',
        
        // Educación
        'UPC' => 'upc.edu.pe',
        'USIL' => 'usil.edu.pe',
        'UPN' => 'upn.edu.pe',
        'TECSUP' => 'tecsup.edu.pe',
        'Cibertec' => 'cibertec.edu.pe',
        'ISIL' => 'isil.pe',
        'Toulouse' => 'toulouselautrec.edu.pe',
        
        // Clínicas
        'Clinica Ricardo Palma' => 'crp.com.pe',
        'Clinica Internacional' => 'clinicainternacional.com.pe',
        'Clinica Javier Prado' => 'clinicajavierprado.com.pe',
        'Clinica San Pablo' => 'sanpablo.com.pe',
        'Auna' => 'auna.pe',
        'Sanna' => 'sanna.pe',
        
        // Bancos y Financieras (excluir si quieres)
        'BCP' => 'viabcp.com',
        'BBVA' => 'bbva.pe',
        'Scotiabank' => 'scotiabank.com.pe',
        'Interbank' => 'interbank.pe',
        
        // Servicios
        'Rappi' => 'rappi.com.pe',
        'PedidosYa' => 'pedidosya.com.pe',
        'Uber Eats' => 'ubereats.com',
        'Glovo' => 'glovoapp.com',
        'InDriver' => 'indriver.com',
        'Beat' => 'thebeat.co',
        'Cabify' => 'cabify.com',
        
        // Gimnasios
        'Smart Fit' => 'smartfit.com.pe',
        'Gold Gym' => 'goldsgym.com.pe',
        'Bodytech' => 'bodytech.com.pe',
        'Sportlife' => 'sportlife.cl',
        
        // Entretenimiento
        'Cineplanet' => 'cineplanet.com.pe',
        'Cinemark' => 'cinemark.com.pe',
        'Cinepolis' => 'cinepolis.com.pe',
        'UVK' => 'uvkmulticines.com'
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
        $this->emailVerifier = new EmailVerifier();
        
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
    }
    
    /**
     * Buscar empresas conocidas con website
     */
    public function buscarEmpresasConWebsite($categoria = 'todas', $limite = 50)
    {
        echo "================================================\n";
        echo "BUSCANDO EMPRESAS CON WEBSITE\n";
        echo "================================================\n\n";
        
        $resultados = [];
        $contador = 0;
        
        foreach ($this->empresasConWebsite as $nombre => $website) {
            if ($contador >= $limite) {
                break;
            }
            
            $contador++;
            echo "[$contador] Buscando: $nombre\n";
            
            // Buscar en UniversidadPeru
            $urlBusqueda = "https://www.universidadperu.com/empresas/busqueda.php";
            
            try {
                $response = $this->client->post($urlBusqueda, [
                    'form_params' => [
                        'buscaempresa' => $nombre
                    ]
                ]);
                
                $html = $response->getBody()->getContents();
                $crawler = new Crawler($html);
                
                // Buscar enlaces a empresas
                $empresa = null;
                $crawler->filter('a[href^="/empresas/"]')->each(function($node) use (&$empresa, $nombre) {
                    $href = $node->attr('href');
                    $texto = $node->text();
                    
                    // Verificar si coincide con el nombre buscado
                    if (stripos($texto, explode(' ', $nombre)[0]) !== false && !$empresa) {
                        if (preg_match('/\/empresas\/([\w-]+)\.php/', $href, $matches)) {
                            $empresa = [
                                'slug' => $matches[1],
                                'nombre' => trim($texto),
                                'url' => 'https://www.universidadperu.com' . $href
                            ];
                        }
                    }
                });
                
                if ($empresa) {
                    // Obtener detalles
                    $detalles = $this->obtenerDetallesEmpresa($empresa['url']);
                    
                    // Agregar website conocido
                    $detalles['website'] = 'https://' . $website;
                    $detalles['nombre_comercial'] = $nombre;
                    
                    // Generar y verificar emails
                    $emails = $this->generarYVerificarEmails($website);
                    $detalles = array_merge($detalles, $emails);
                    
                    $resultados[] = $detalles;
                    echo "   ✓ Encontrado";
                    if (isset($detalles['ruc'])) {
                        echo " (RUC: {$detalles['ruc']})";
                    }
                    if (isset($detalles['email_mejor'])) {
                        echo " - Email: {$detalles['email_mejor']}";
                    }
                    echo "\n";
                } else {
                    echo "   ✗ No encontrado en la base de datos\n";
                }
                
                sleep(1);
                
            } catch (Exception $e) {
                echo "   ✗ Error: " . $e->getMessage() . "\n";
            }
        }
        
        // Guardar resultados
        if (!empty($resultados)) {
            $this->guardarCSV($resultados);
        }
        
        echo "\n================================================\n";
        echo "RESUMEN\n";
        echo "================================================\n";
        echo "✅ Empresas encontradas: " . count($resultados) . " de $contador buscadas\n";
        echo "📁 Archivo guardado en: data/output/\n";
        
        return $resultados;
    }
    
    /**
     * Obtener detalles de la empresa
     */
    private function obtenerDetallesEmpresa($url)
    {
        $response = $this->client->get($url);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);
        
        $data = [];
        
        // Extraer RUC del texto
        if (preg_match('/RUC[:\s]+(\d{11})/', $crawler->text(), $matches)) {
            $data['ruc'] = $matches[1];
        }
        
        // Buscar datos en tabla
        $crawler->filter('table tr')->each(function($row) use (&$data) {
            $cells = $row->filter('td');
            if ($cells->count() >= 2) {
                $label = trim($cells->eq(0)->text());
                $value = trim($cells->eq(1)->text());
                
                if (strpos($label, 'Razón Social') !== false) {
                    $data['razon_social'] = $value;
                } elseif (strpos($label, 'Dirección') !== false && !isset($data['direccion'])) {
                    $data['direccion'] = $value;
                } elseif (strpos($label, 'Teléfono') !== false) {
                    $data['telefono'] = $value;
                } elseif (strpos($label, 'Estado') !== false || strpos($label, 'Condición') !== false) {
                    $data['estado'] = $value;
                } elseif (strpos($label, 'Actividad') !== false) {
                    $data['actividad'] = $value;
                }
            }
        });
        
        return $data;
    }
    
    /**
     * Generar y verificar emails para un dominio
     */
    private function generarYVerificarEmails($dominio)
    {
        $emails = [
            "info@$dominio",
            "contacto@$dominio",
            "ventas@$dominio",
            "atencionalcliente@$dominio",
            "reservas@$dominio",  // Para restaurantes
            "delivery@$dominio"   // Para restaurantes
        ];
        
        $mejorEmail = null;
        $mejorScore = 0;
        
        foreach ($emails as $email) {
            $verificacion = $this->emailVerifier->verificar($email);
            if ($verificacion['score'] > $mejorScore) {
                $mejorScore = $verificacion['score'];
                $mejorEmail = $email;
            }
            
            // Si encontramos uno válido (score >= 75), usarlo
            if ($verificacion['score'] >= 75) {
                break;
            }
        }
        
        return [
            'email_mejor' => $mejorEmail,
            'email_score' => $mejorScore,
            'email_verificado' => $mejorScore >= 75 ? 'Sí' : 'No'
        ];
    }
    
    /**
     * Guardar resultados en CSV
     */
    private function guardarCSV($resultados)
    {
        $fecha = date('Y-m-d_His');
        $filename = "empresas_con_website_{$fecha}.csv";
        $filepath = $this->outputDir . $filename;
        
        $fp = fopen($filepath, 'w');
        if ($fp) {
            // Headers
            fputcsv($fp, [
                'Nombre Comercial',
                'RUC',
                'Razón Social',
                'Website',
                'Email',
                'Email Verificado',
                'Email Score',
                'Teléfono',
                'Dirección',
                'Estado',
                'Actividad'
            ]);
            
            // Data
            foreach ($resultados as $empresa) {
                fputcsv($fp, [
                    $empresa['nombre_comercial'] ?? '',
                    $empresa['ruc'] ?? '',
                    $empresa['razon_social'] ?? '',
                    $empresa['website'] ?? '',
                    $empresa['email_mejor'] ?? '',
                    $empresa['email_verificado'] ?? '',
                    $empresa['email_score'] ?? 0,
                    $empresa['telefono'] ?? '',
                    $empresa['direccion'] ?? '',
                    $empresa['estado'] ?? '',
                    $empresa['actividad'] ?? ''
                ]);
            }
            
            fclose($fp);
            echo "\n💾 Archivo guardado: $filename\n";
        }
    }
}

// ============================================
// EJECUCIÓN PRINCIPAL
// ============================================

$scraper = new ScraperCadenas();

// Parsear argumentos
$options = getopt("c:l:h", ["categoria:", "limite:", "help"]);

if (isset($options['h']) || isset($options['help'])) {
    echo "\nUSO: php scraper_cadenas.php [opciones]\n\n";
    echo "OPCIONES:\n";
    echo "  -c, --categoria <tipo>   Categoría (todas, restaurantes, retail, etc)\n";
    echo "  -l, --limite <numero>    Cantidad de empresas a buscar (default: 50)\n";
    echo "  -h, --help               Mostrar esta ayuda\n\n";
    echo "EJEMPLOS:\n";
    echo "  php scraper_cadenas.php -l 20\n";
    echo "  php scraper_cadenas.php -c restaurantes -l 30\n\n";
    exit(0);
}

$categoria = $options['c'] ?? $options['categoria'] ?? 'todas';
$limite = $options['l'] ?? $options['limite'] ?? 50;

$scraper->buscarEmpresasConWebsite($categoria, $limite);