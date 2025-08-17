<?php

namespace WorkflowSales\Validators;

class EmailValidator
{
    private $logger;
    private $config;
    
    public function __construct($config = [], $logger = null)
    {
        $this->config = array_merge([
            'smtp_check' => true,
            'dns_check' => true,
            'syntax_check' => true,
            'timeout' => 5
        ], $config);
        
        $this->logger = $logger;
    }
    
    /**
     * Validar un email completo
     */
    public function validate($email)
    {
        $email = strtolower(trim($email));
        
        // 1. Validación de sintaxis
        if ($this->config['syntax_check'] && !$this->validateSyntax($email)) {
            return false;
        }
        
        // 2. Validación DNS
        if ($this->config['dns_check'] && !$this->validateDNS($email)) {
            return false;
        }
        
        // 3. Validación SMTP (más costosa)
        if ($this->config['smtp_check'] && !$this->validateSMTP($email)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar múltiples emails
     */
    public function validateBatch($emails)
    {
        $results = [];
        
        foreach ($emails as $email) {
            $results[$email] = $this->validate($email);
            
            if ($this->logger) {
                $status = $results[$email] ? 'válido' : 'inválido';
                $this->logger->debug("Email $email: $status");
            }
        }
        
        return $results;
    }
    
    /**
     * Generar emails probables para un dominio
     */
    public function generateEmails($domain, $patterns = null)
    {
        if (!$domain) {
            return [];
        }
        
        // Limpiar dominio
        $domain = str_replace(['http://', 'https://', 'www.'], '', $domain);
        $domain = rtrim($domain, '/');
        
        // Patrones por defecto para Perú
        if (!$patterns) {
            $patterns = [
                // Genéricos
                "info@{domain}",
                "ventas@{domain}",
                "contacto@{domain}",
                "administracion@{domain}",
                "consultas@{domain}",
                
                // Decision makers
                "gerencia@{domain}",
                "gerente@{domain}",
                "director@{domain}",
                "ceo@{domain}",
                "presidente@{domain}",
                
                // Departamentos
                "marketing@{domain}",
                "comercial@{domain}",
                "finanzas@{domain}",
                "contabilidad@{domain}",
                "rrhh@{domain}",
                
                // Variaciones comunes
                "admin@{domain}",
                "soporte@{domain}",
                "atencion@{domain}",
                "recepcion@{domain}"
            ];
            
            // Si no tiene .pe, agregar variaciones con .pe
            if (!strpos($domain, '.pe')) {
                $patterns[] = "info@{domain}.pe";
                $patterns[] = "ventas@{domain}.pe";
                $patterns[] = "contacto@{domain}.pe";
                $patterns[] = "gerencia@{domain}.pe";
            }
        }
        
        $emails = [];
        foreach ($patterns as $pattern) {
            $email = str_replace('{domain}', $domain, $pattern);
            $emails[] = $email;
        }
        
        // También generar con el nombre de la empresa (sin extensión)
        $companyName = explode('.', $domain)[0];
        if (strlen($companyName) > 3) {
            $emails[] = $companyName . "@gmail.com";
            $emails[] = $companyName . "@hotmail.com";
            $emails[] = $companyName . "peru@gmail.com";
        }
        
        return array_unique($emails);
    }
    
    /**
     * Identificar decision makers de una lista de emails
     */
    public function identifyDecisionMakers($emails)
    {
        $scores = [];
        
        $decisionKeywords = [
            'ceo' => 100,
            'gerente' => 90,
            'gerencia' => 90,
            'director' => 85,
            'presidente' => 85,
            'owner' => 80,
            'propietario' => 80,
            'administracion' => 70,
            'admin' => 70,
            'marketing' => 60,
            'comercial' => 60,
            'ventas' => 50,
            'finanzas' => 50
        ];
        
        foreach ($emails as $email) {
            $score = 0;
            $localPart = explode('@', $email)[0];
            
            foreach ($decisionKeywords as $keyword => $points) {
                if (stripos($localPart, $keyword) !== false) {
                    $score = max($score, $points);
                }
            }
            
            // Penalizar emails genéricos sin valor
            $genericEmails = ['info', 'contacto', 'consultas', 'soporte', 'noreply'];
            if (in_array($localPart, $genericEmails) && $score == 0) {
                $score = 10;
            }
            
            // Emails con nombres propios probablemente son de personas
            if (preg_match('/^[a-z]{3,10}$/', $localPart)) {
                $score = max($score, 40);
            }
            
            // Iniciales (ej: jc@, mp@) probablemente son ejecutivos
            if (preg_match('/^[a-z]{1,2}$/', $localPart)) {
                $score = max($score, 35);
            }
            
            $scores[$email] = $score;
        }
        
        // Ordenar por score
        arsort($scores);
        
        // Retornar el mejor o los top 3
        $top = array_slice(array_keys($scores), 0, 3);
        
        return [
            'best' => $top[0] ?? null,
            'all' => $scores,
            'top3' => $top
        ];
    }
    
    /**
     * Validación de sintaxis
     */
    private function validateSyntax($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validación DNS
     */
    private function validateDNS($email)
    {
        list($user, $domain) = explode('@', $email);
        
        // Verificar registros MX
        if (checkdnsrr($domain, 'MX')) {
            return true;
        }
        
        // Fallback: verificar registro A
        if (checkdnsrr($domain, 'A')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validación SMTP (más precisa pero más lenta)
     */
    private function validateSMTP($email)
    {
        list($user, $domain) = explode('@', $email);
        
        // Obtener registros MX
        $mxRecords = [];
        if (!getmxrr($domain, $mxRecords)) {
            return false;
        }
        
        // Intentar conectar al servidor SMTP
        $smtp = @fsockopen($mxRecords[0], 25, $errno, $errstr, $this->config['timeout']);
        
        if (!$smtp) {
            return false;
        }
        
        // Leer respuesta inicial
        $response = fgets($smtp, 1024);
        if (substr($response, 0, 3) != '220') {
            fclose($smtp);
            return false;
        }
        
        // HELO
        fwrite($smtp, "HELO validator.com\r\n");
        $response = fgets($smtp, 1024);
        
        // MAIL FROM
        fwrite($smtp, "MAIL FROM: <test@validator.com>\r\n");
        $response = fgets($smtp, 1024);
        
        // RCPT TO (aquí verificamos si el email existe)
        fwrite($smtp, "RCPT TO: <{$email}>\r\n");
        $response = fgets($smtp, 1024);
        
        // QUIT
        fwrite($smtp, "QUIT\r\n");
        fclose($smtp);
        
        // Códigos 250 o 251 indican que el email existe
        return (substr($response, 0, 3) == '250' || substr($response, 0, 3) == '251');
    }
}