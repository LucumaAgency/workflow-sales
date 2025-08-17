# 🚀 Workflow Sales - Pipeline de Generación de Leads para Perú

Sistema automatizado para generar leads B2B en Perú usando fuentes públicas.

## 📊 Características

- ✅ Scraping de UniversidadPeru.com (400k+ empresas)
- ✅ Extracción de RUC + Dominio
- ✅ Generación inteligente de emails
- ✅ Verificación SMTP de emails
- ✅ Lead scoring automático
- ✅ Identificación de decision makers
- ✅ Export a CSV/JSON

## 🛠️ Instalación

```bash
# Clonar repositorio
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales

# Instalar dependencias PHP
composer install

# Copiar configuración
cp config/config.example.php config/config.php

# Ejecutar
php src/main.php
```

## 📁 Estructura

```
workflow-sales/
├── src/
│   ├── scrapers/          # Scrapers por fuente
│   ├── validators/         # Verificación de emails
│   ├── enrichers/         # Enriquecimiento de datos
│   └── pipeline/          # Pipeline principal
├── config/                # Configuración
├── data/                  # Datos y resultados
└── logs/                  # Logs de ejecución
```

## 🎯 Uso Rápido

```php
// Generar leads de restaurantes
php src/main.php --category restaurantes --limit 100

// Verificar emails de un CSV
php src/verify_emails.php --input data/empresas.csv

// Buscar decision makers
php src/find_decision_makers.php --domain ejemplo.com
```

## 📈 Resultados Esperados

- 100 empresas scrapeadas → 60 con dominio
- 60 con dominio → 40 dominios activos  
- 40 dominios activos → 24 con emails verificables
- 24 con emails → 10-15 decision makers identificados
- **ROI: 5-8 hot leads por cada 100 empresas**

## 🔧 Configuración

Editar `config/config.php`:

```php
return [
    'scrapers' => [
        'universidadperu' => true,
        'datosperu' => true,
    ],
    'email_verification' => [
        'smtp_check' => true,
        'dns_check' => true,
    ],
    'rate_limits' => [
        'requests_per_second' => 1,
        'delay_between_requests' => 2,
    ]
];
```

## 📝 Licencia

MIT License