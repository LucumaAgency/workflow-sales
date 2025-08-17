# 📚 Documentación Completa - Workflow Sales

## 🎯 Visión General

**Workflow Sales** es un sistema automatizado de generación de leads B2B para el mercado peruano. El objetivo es identificar empresas que necesitan servicios de marketing digital mediante el análisis de su presencia online actual.

## 🚀 Propuesta de Valor

### El Problema
- Las agencias de marketing gastan demasiado tiempo buscando clientes potenciales manualmente
- No hay forma eficiente de identificar empresas sin presencia digital en Perú
- Contactar empresas sin saber quién toma decisiones es ineficiente
- Comprar bases de datos es caro y los datos suelen estar desactualizados

### La Solución
Un pipeline automatizado que:
1. **Encuentra empresas** desde fuentes públicas peruanas
2. **Identifica oportunidades** (sin web, sin GMB, empresas nuevas)
3. **Genera emails inteligentemente** basándose en patrones comunes
4. **Verifica emails** con SMTP/DNS
5. **Identifica decision makers** automáticamente
6. **Califica leads** con un sistema de scoring

## 🏗️ Arquitectura del Sistema

```
┌─────────────────┐     ┌──────────────┐     ┌─────────────────┐
│                 │     │              │     │                 │
│  DATA SOURCES   │────▶│  PROCESSING  │────▶│     OUTPUT      │
│                 │     │              │     │                 │
└─────────────────┘     └──────────────┘     └─────────────────┘
        │                      │                      │
        ▼                      ▼                      ▼
  - UniversidadPeru      - Email Gen         - CSV Export
  - SUNAT Padrón        - Validation        - JSON Export  
  - MercadoLibre        - Enrichment        - CRM Ready
  - Indeed              - Scoring           - Email Lists
```

## 📊 Fuentes de Datos

### 1. **UniversidadPeru.com** (Implementado)
- +400,000 empresas peruanas
- Datos: RUC, nombre, dominio, teléfono, dirección
- Categorías: restaurantes, hoteles, clínicas, colegios, etc.

### 2. **SUNAT Padrón** (Próximamente)
- +2 millones de RUCs activos
- Descarga gratuita mensual
- Datos: RUC, razón social, actividad CIIU, estado

### 3. **MercadoLibre** (Próximamente)  
- Vendedores exitosos sin web propia
- Identificación de empresas en crecimiento

### 4. **Indeed/Computrabajo** (Próximamente)
- Empresas contratando = presupuesto disponible
- Señal clara de necesidad de marketing

## 🔧 Flujo de Trabajo

### Paso 1: Scraping
```php
$scraper = new UniversidadPeruScraper();
$empresas = $scraper->scrapeByCategory('restaurantes-peru', 100);
```

### Paso 2: Generación de Emails
```php
$validator = new EmailValidator();
$emails = $validator->generateEmails('empresa.com');
// Genera: info@empresa.com, ventas@empresa.com, gerencia@empresa.com
```

### Paso 3: Verificación
```php
// Verificación DNS - Rápida
$hasMX = checkdnsrr('empresa.com', 'MX');

// Verificación SMTP - Precisa
$exists = $validator->validateSMTP('gerencia@empresa.com');
```

### Paso 4: Lead Scoring
```
Score = 0
+ 20 pts si tiene website
+ 25 pts si tiene emails verificados  
+ 25 pts si encontramos decision maker
+ 30 pts si NO tiene Google My Business
+ 20 pts si es empresa nueva (<6 meses)
+ 15 pts si está contratando marketing
───────────────────────────────
Score > 80 = HOT LEAD 🔥
```

### Paso 5: Identificación de Decision Makers
```php
Prioridad 1: CEO, Gerente General, Director
Prioridad 2: Gerente Marketing, Gerente Comercial
Prioridad 3: Administración, Ventas
Ignorar: info@, contacto@, soporte@
```

## 💻 Instalación

### Requisitos Previos

#### Windows
- **PHP 7.4+** (XAMPP recomendado: https://www.apachefriends.org/)
- **Composer** (https://getcomposer.org/download/)
- **Git** (https://git-scm.com/download/win)

#### Linux/Mac
```bash
# PHP
sudo apt install php7.4 php7.4-curl php7.4-mbstring php7.4-xml

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Instalación del Proyecto

```bash
# 1. Clonar repositorio
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales

# 2. Instalar dependencias
composer install

# 3. Crear directorios
mkdir data/output data/cache logs

# 4. Copiar configuración
cp config/config.example.php config/config.php

# 5. Ejecutar
php src/main.php --help
```

### Instalación en Windows (Detallada)

1. **Instalar XAMPP**
   - Descargar de https://www.apachefriends.org/
   - Instalar en `C:\xampp`
   - Solo necesitas PHP, puedes desmarcar Apache/MySQL

2. **Instalar Composer**
   - Descargar https://getcomposer.org/Composer-Setup.exe
   - Durante instalación, seleccionar PHP: `C:\xampp\php\php.exe`
   - Marcar "Add to PATH"

3. **Configurar el proyecto**
   ```cmd
   cd workflow-sales
   composer install
   mkdir data\output
   mkdir logs
   copy config\config.example.php config\config.php
   ```

## 📋 Uso

### Comandos Básicos

```bash
# Ver ayuda
php src/main.php --help

# Buscar 50 restaurantes
php src/main.php --category restaurantes-peru --limit 50

# Buscar 30 hoteles
php src/main.php --category hoteles-peru --limit 30

# Especificar archivo de salida
php src/main.php --category clinicas-peru --limit 20 --output data/clinicas.csv
```

### Categorías Disponibles
- `restaurantes-peru` - Restaurantes y servicios de comida
- `hoteles-peru` - Hoteles y hospedajes
- `clinicas-peru` - Clínicas y centros médicos
- `colegios-peru` - Instituciones educativas
- `inmobiliarias-peru` - Agencias inmobiliarias
- `farmacias-peru` - Farmacias y boticas

### Output

El sistema genera un CSV con:
```
RUC | Nombre | Dominio | Email Principal | Decision Maker | Score | Teléfono | Dirección
```

Ejemplo:
```csv
20512345678,RESTAURANT MARINO SAC,marino.pe,gerencia@marino.pe,gerencia@marino.pe,95,01-2345678,Av. Larco 123
20598765432,HOTEL PLAZA LIMA,hotelplaza.com,director@hotelplaza.com,director@hotelplaza.com,90,01-9876543,Jr. Union 456
```

## ⚙️ Configuración

Editar `config/config.php`:

```php
return [
    // Verificación de emails
    'email_verification' => [
        'smtp_check' => true,    // false = más rápido
        'dns_check' => true,     // Verificar MX records
        'timeout' => 5           // Timeout en segundos
    ],
    
    // Lead scoring mínimo
    'filters' => [
        'min_score' => 50,       // Solo leads con score >= 50
    ],
    
    // Rate limiting
    'rate_limits' => [
        'requests_per_second' => 1,
        'delay_between_requests' => 2
    ]
];
```

## 📈 Métricas y Resultados Esperados

### Por cada 100 empresas scrapeadas:
- 60% tienen dominio web listado
- 40% de esos dominios están activos
- 24 empresas con emails verificables
- 10-15 decision makers identificados
- **5-8 HOT LEADS** (score > 80)

### Tiempo de Procesamiento:
- 100 empresas: ~5-10 minutos
- Verificación SMTP: +2-3 segundos por email
- Rate limiting: 2 segundos entre requests

### ROI Esperado:
- Costo: $0 (fuentes públicas)
- Tiempo: 10 minutos por 100 empresas
- Resultado: 5-8 leads calificados
- Conversión esperada: 10-20% (1-2 clientes)

## 🔄 Pipeline Completo de Ventas

```
1. GENERACIÓN (Esta herramienta)
   ↓
2. CALIFICACIÓN
   - Score > 80: Contacto inmediato
   - Score 50-80: Nurturing
   - Score < 50: Descarte
   ↓
3. PRIMER CONTACTO
   - Email personalizado al decision maker
   - Mencionar problema específico
   - Propuesta de valor clara
   ↓
4. SEGUIMIENTO
   - 3 días: Primer follow-up
   - 7 días: Segundo follow-up
   - 14 días: Break-up email
   ↓
5. CIERRE
   - Llamada de 15 minutos
   - Demo personalizada
   - Propuesta comercial
```

## 🚀 Roadmap

### ✅ Fase 1 (Completado)
- [x] Scraper UniversidadPeru
- [x] Generación de emails
- [x] Verificación SMTP/DNS
- [x] Lead scoring
- [x] Export CSV/JSON

### 🔄 Fase 2 (En desarrollo)
- [ ] Integración SUNAT Padrón
- [ ] Scraper MercadoLibre
- [ ] Búsqueda en Indeed
- [ ] API de WhatsApp Business
- [ ] Verificación GMB

### 📅 Fase 3 (Próximamente)
- [ ] Dashboard web
- [ ] Integración CRM (HubSpot, Pipedrive)
- [ ] Email automation
- [ ] Tracking de conversión
- [ ] Machine Learning para scoring

## 🤝 Contribuir

1. Fork el repositorio
2. Crea tu feature branch (`git checkout -b feature/NuevaCaracteristica`)
3. Commit tus cambios (`git commit -m 'Add: Nueva característica'`)
4. Push al branch (`git push origin feature/NuevaCaracteristica`)
5. Abre un Pull Request

## 📝 Licencia

MIT License - Ver `LICENSE` para más detalles.

## 📞 Soporte

- **Issues**: https://github.com/LucumaAgency/workflow-sales/issues
- **Email**: soporte@lucuma.agency
- **Documentación**: Este archivo

## 🙏 Créditos

Desarrollado por **Lucuma Innovation Agency**

Fuentes de datos:
- UniversidadPeru.com
- SUNAT
- Datos públicos del Perú

---

*Última actualización: Enero 2024*