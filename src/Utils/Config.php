<?php

namespace WorkflowSales\Utils;

class Config
{
    /**
     * Cargar configuración
     */
    public static function load()
    {
        $configFile = __DIR__ . '/../../config/config.php';
        
        // Si no existe, usar el ejemplo
        if (!file_exists($configFile)) {
            $configFile = __DIR__ . '/../../config/config.example.php';
        }
        
        if (!file_exists($configFile)) {
            throw new \Exception("No se encontró archivo de configuración");
        }
        
        return require $configFile;
    }
    
    /**
     * Obtener valor de configuración
     */
    public static function get($key, $default = null)
    {
        $config = self::load();
        
        // Soportar notación de punto
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}