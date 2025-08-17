<?php

namespace WorkflowSales\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private $logger;
    
    public function __construct($config = [])
    {
        $this->logger = new MonologLogger('workflow-sales');
        
        // Configuración por defecto
        $config = array_merge([
            'enabled' => true,
            'level' => 'INFO',
            'file' => __DIR__ . '/../../logs/app.log',
            'max_files' => 7
        ], $config);
        
        if (!$config['enabled']) {
            return;
        }
        
        // Crear directorio de logs si no existe
        $logDir = dirname($config['file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        // Formato personalizado
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s"
        );
        
        // Handler para archivo rotativo
        $fileHandler = new RotatingFileHandler(
            $config['file'],
            $config['max_files'],
            $this->getLevel($config['level'])
        );
        $fileHandler->setFormatter($formatter);
        $this->logger->pushHandler($fileHandler);
        
        // Handler para consola (solo warnings y errores)
        $consoleHandler = new StreamHandler(
            'php://stdout',
            MonologLogger::WARNING
        );
        $consoleHandler->setFormatter($formatter);
        $this->logger->pushHandler($consoleHandler);
    }
    
    /**
     * Convertir string de nivel a constante Monolog
     */
    private function getLevel($level)
    {
        $levels = [
            'DEBUG' => MonologLogger::DEBUG,
            'INFO' => MonologLogger::INFO,
            'WARNING' => MonologLogger::WARNING,
            'ERROR' => MonologLogger::ERROR,
            'CRITICAL' => MonologLogger::CRITICAL
        ];
        
        return $levels[strtoupper($level)] ?? MonologLogger::INFO;
    }
    
    // Métodos proxy
    public function debug($message, $context = [])
    {
        $this->logger->debug($message, $context);
    }
    
    public function info($message, $context = [])
    {
        $this->logger->info($message, $context);
    }
    
    public function warning($message, $context = [])
    {
        $this->logger->warning($message, $context);
    }
    
    public function error($message, $context = [])
    {
        $this->logger->error($message, $context);
    }
    
    public function critical($message, $context = [])
    {
        $this->logger->critical($message, $context);
    }
}