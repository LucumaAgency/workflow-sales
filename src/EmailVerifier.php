<?php

namespace WorkflowSales;

/**
 * Verificador de emails con múltiples métodos de validación
 */
class EmailVerifier
{
    /**
     * Verificar si un email es válido y existe
     */
    public function verificar($email)
    {
        $resultado = [
            'email' => $email,
            'sintaxis_valida' => false,
            'dominio_valido' => false,
            'mx_existe' => false,
            'smtp_valido' => false,
            'es_desechable' => false,
            'score' => 0,
            'estado' => 'invalido'
        ];
        
        // 1. Verificar sintaxis
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $resultado['error'] = 'Sintaxis inválida';
            return $resultado;
        }
        $resultado['sintaxis_valida'] = true;
        $resultado['score'] += 25;
        
        // 2. Extraer dominio
        $partes = explode('@', $email);
        $dominio = array_pop($partes);
        
        // 3. Verificar si es un dominio desechable/temporal
        if ($this->esDominioDesechable($dominio)) {
            $resultado['es_desechable'] = true;
            $resultado['error'] = 'Dominio desechable/temporal';
            return $resultado;
        }
        
        // 4. Verificar DNS del dominio
        if (!checkdnsrr($dominio, 'A') && !checkdnsrr($dominio, 'AAAA')) {
            $resultado['error'] = 'Dominio no existe';
            return $resultado;
        }
        $resultado['dominio_valido'] = true;
        $resultado['score'] += 25;
        
        // 5. Verificar registros MX (servidores de correo)
        if (!checkdnsrr($dominio, 'MX')) {
            $resultado['error'] = 'Sin servidores de correo (MX)';
            $resultado['estado'] = 'dudoso';
            return $resultado;
        }
        $resultado['mx_existe'] = true;
        $resultado['score'] += 25;
        
        // 6. Obtener servidores MX
        $mx_records = [];
        getmxrr($dominio, $mx_records, $mx_weight);
        $resultado['mx_servers'] = $mx_records;
        
        // 7. Verificación SMTP (opcional, puede ser lento)
        if (!empty($mx_records)) {
            $smtp_check = $this->verificarSMTP($email, $mx_records[0]);
            $resultado['smtp_valido'] = $smtp_check;
            if ($smtp_check) {
                $resultado['score'] += 25;
            }
        }
        
        // Determinar estado final
        if ($resultado['score'] >= 75) {
            $resultado['estado'] = 'valido';
        } elseif ($resultado['score'] >= 50) {
            $resultado['estado'] = 'probable';
        } else {
            $resultado['estado'] = 'dudoso';
        }
        
        return $resultado;
    }
    
    /**
     * Verificación SMTP básica
     */
    private function verificarSMTP($email, $mx_server, $timeout = 5)
    {
        try {
            // Intentar conexión al servidor SMTP
            $socket = @fsockopen($mx_server, 25, $errno, $errstr, $timeout);
            
            if (!$socket) {
                return false;
            }
            
            // Leer respuesta inicial
            $response = fgets($socket, 1024);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                return false;
            }
            
            // HELO
            fputs($socket, "HELO verify.com\r\n");
            $response = fgets($socket, 1024);
            if (substr($response, 0, 3) != '250') {
                fclose($socket);
                return false;
            }
            
            // MAIL FROM
            fputs($socket, "MAIL FROM: <verify@verify.com>\r\n");
            $response = fgets($socket, 1024);
            if (substr($response, 0, 3) != '250') {
                fclose($socket);
                return false;
            }
            
            // RCPT TO (verificar si el email existe)
            fputs($socket, "RCPT TO: <$email>\r\n");
            $response = fgets($socket, 1024);
            $valid = (substr($response, 0, 3) == '250');
            
            // QUIT
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            return $valid;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar múltiples emails en lote
     */
    public function verificarLote($emails, $mostrarProgreso = true)
    {
        $resultados = [];
        $total = count($emails);
        $contador = 0;
        
        if ($mostrarProgreso) {
            echo "Verificando $total emails...\n\n";
        }
        
        foreach ($emails as $email) {
            $contador++;
            
            if ($mostrarProgreso) {
                echo "[$contador/$total] Verificando: $email... ";
            }
            
            $resultado = $this->verificar($email);
            $resultados[$email] = $resultado;
            
            if ($mostrarProgreso) {
                $icono = $resultado['estado'] == 'valido' ? '✓' : 
                        ($resultado['estado'] == 'probable' ? '?' : '✗');
                echo "$icono {$resultado['estado']} (Score: {$resultado['score']})\n";
            }
            
            // Pequeña pausa para no saturar
            usleep(500000); // 0.5 segundos
        }
        
        return $resultados;
    }
    
    /**
     * Generar emails probables para un dominio
     */
    public function generarEmailsProbables($nombreEmpresa, $dominio = null)
    {
        $emails = [];
        
        // Limpiar nombre de empresa
        $nombreLimpio = strtolower(preg_replace('/[^a-z0-9]/i', '', $nombreEmpresa));
        $nombreCorto = substr($nombreLimpio, 0, 10);
        
        // Si no hay dominio, intentar .com.pe y .pe
        if (!$dominio) {
            $dominios = [
                $nombreLimpio . '.com.pe',
                $nombreLimpio . '.pe',
                $nombreCorto . '.com.pe',
                $nombreCorto . '.pe'
            ];
        } else {
            $dominios = [$dominio];
        }
        
        // Prefijos comunes para empresas peruanas
        $prefijos = [
            'info',
            'contacto',
            'ventas',
            'administracion',
            'gerencia',
            'rrhh',
            'facturacion',
            'atencion',
            'reservas',     // Para restaurantes
            'pedidos',      // Para restaurantes/tiendas
            'delivery'      // Para restaurantes
        ];
        
        foreach ($dominios as $dom) {
            foreach ($prefijos as $prefijo) {
                $emails[] = $prefijo . '@' . $dom;
            }
        }
        
        return array_unique($emails);
    }
    
    /**
     * Verificar si es un dominio de email desechable/temporal
     */
    private function esDominioDesechable($dominio)
    {
        $dominiosDesechables = [
            'mailinator.com',
            'guerrillamail.com',
            '10minutemail.com',
            'tempmail.com',
            'throwaway.email',
            'yopmail.com',
            'temp-mail.org',
            'fakeinbox.com',
            'trashmail.com',
            'maildrop.cc'
        ];
        
        return in_array(strtolower($dominio), $dominiosDesechables);
    }
    
    /**
     * Obtener estadísticas de verificación
     */
    public function obtenerEstadisticas($resultados)
    {
        $stats = [
            'total' => count($resultados),
            'validos' => 0,
            'probables' => 0,
            'dudosos' => 0,
            'invalidos' => 0,
            'con_mx' => 0,
            'con_smtp' => 0,
            'desechables' => 0
        ];
        
        foreach ($resultados as $resultado) {
            switch ($resultado['estado']) {
                case 'valido':
                    $stats['validos']++;
                    break;
                case 'probable':
                    $stats['probables']++;
                    break;
                case 'dudoso':
                    $stats['dudosos']++;
                    break;
                default:
                    $stats['invalidos']++;
            }
            
            if ($resultado['mx_existe']) $stats['con_mx']++;
            if ($resultado['smtp_valido']) $stats['con_smtp']++;
            if ($resultado['es_desechable']) $stats['desechables']++;
        }
        
        $stats['porcentaje_validos'] = $stats['total'] > 0 
            ? round(($stats['validos'] / $stats['total']) * 100, 2) 
            : 0;
        
        return $stats;
    }
}

// Script de prueba si se ejecuta directamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "================================================\n";
    echo "VERIFICADOR DE EMAILS\n";
    echo "================================================\n\n";
    
    $verificador = new EmailVerifier();
    
    // Parsear argumentos
    $options = getopt("e:d:h", ["email:", "dominio:", "help"]);
    
    if (isset($options['h']) || isset($options['help'])) {
        echo "USO: php EmailVerifier.php [opciones]\n\n";
        echo "OPCIONES:\n";
        echo "  -e, --email <email>      Verificar un email específico\n";
        echo "  -d, --dominio <dominio>  Generar emails probables para un dominio\n";
        echo "  -h, --help               Mostrar esta ayuda\n\n";
        echo "EJEMPLOS:\n";
        echo "  php EmailVerifier.php -e info@empresa.com.pe\n";
        echo "  php EmailVerifier.php -d empresa.com.pe\n\n";
        exit(0);
    }
    
    // Verificar email específico
    if (isset($options['e']) || isset($options['email'])) {
        $email = $options['e'] ?? $options['email'];
        echo "Verificando: $email\n\n";
        
        $resultado = $verificador->verificar($email);
        
        echo "RESULTADO:\n";
        echo "----------\n";
        echo "Email: {$resultado['email']}\n";
        echo "Estado: {$resultado['estado']}\n";
        echo "Score: {$resultado['score']}/100\n";
        echo "Sintaxis válida: " . ($resultado['sintaxis_valida'] ? 'Sí' : 'No') . "\n";
        echo "Dominio válido: " . ($resultado['dominio_valido'] ? 'Sí' : 'No') . "\n";
        echo "MX existe: " . ($resultado['mx_existe'] ? 'Sí' : 'No') . "\n";
        echo "SMTP válido: " . ($resultado['smtp_valido'] ? 'Sí' : 'No') . "\n";
        
        if (isset($resultado['mx_servers'])) {
            echo "Servidores MX: " . implode(', ', $resultado['mx_servers']) . "\n";
        }
        
        if (isset($resultado['error'])) {
            echo "Error: {$resultado['error']}\n";
        }
        
        exit(0);
    }
    
    // Generar emails probables
    if (isset($options['d']) || isset($options['dominio'])) {
        $dominio = $options['d'] ?? $options['dominio'];
        echo "Generando emails probables para: $dominio\n\n";
        
        $nombreEmpresa = explode('.', $dominio)[0];
        $emails = $verificador->generarEmailsProbables($nombreEmpresa, $dominio);
        
        echo "Emails generados:\n";
        foreach ($emails as $email) {
            echo "  • $email\n";
        }
        
        echo "\n¿Verificar estos emails? (s/n): ";
        $respuesta = trim(fgets(STDIN));
        
        if (strtolower($respuesta) == 's') {
            $resultados = $verificador->verificarLote($emails);
            
            echo "\n";
            echo "RESUMEN:\n";
            echo "--------\n";
            
            $stats = $verificador->obtenerEstadisticas($resultados);
            echo "Total verificados: {$stats['total']}\n";
            echo "Válidos: {$stats['validos']} ({$stats['porcentaje_validos']}%)\n";
            echo "Probables: {$stats['probables']}\n";
            echo "Dudosos: {$stats['dudosos']}\n";
            echo "Inválidos: {$stats['invalidos']}\n";
            
            echo "\nEmails válidos:\n";
            foreach ($resultados as $email => $resultado) {
                if ($resultado['estado'] == 'valido') {
                    echo "  ✓ $email\n";
                }
            }
        }
        
        exit(0);
    }
    
    echo "Usa -h o --help para ver las opciones disponibles.\n";
}