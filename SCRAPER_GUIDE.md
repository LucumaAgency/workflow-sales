# 📋 Guía del Scraper por Categorías

## 🎯 Características principales

- **Scraping por categorías**: Procesa empresas de categorías específicas
- **Paginación persistente**: Guarda el progreso y permite continuar donde quedaste
- **Control de límites**: Define cuántas empresas procesar por sesión
- **Exportación automática**: Guarda resultados en CSV con timestamp
- **Sistema de tracking**: Registra qué empresas ya fueron procesadas

## 📦 Instalación

```bash
# Asegúrate de tener las dependencias instaladas
composer install
```

## 🚀 Uso básico

### Ver categorías disponibles
```bash
php src/scraper_categoria.php --listar
```

### Scrapear primeras 20 empresas de una categoría
```bash
php src/scraper_categoria.php -c restaurantes -l 20
```

### Continuar scrapeando las siguientes 20 empresas
```bash
# Simplemente ejecuta el mismo comando nuevamente
php src/scraper_categoria.php -c restaurantes -l 20
```

### Empezar desde cero (ignorar progreso anterior)
```bash
php src/scraper_categoria.php -c restaurantes -l 20 --nuevo
```

## 📊 Gestión del progreso

### Ver progreso actual de todas las categorías
```bash
php src/scraper_categoria.php --progreso
```

### Resetear progreso de una categoría específica
```bash
php src/scraper_categoria.php --reset restaurantes
```

### Resetear todo el progreso
```bash
php src/scraper_categoria.php --reset all
```

## 📁 Archivos generados

### Archivos CSV
Los resultados se guardan en:
```
data/output/categoria_[nombre]_[fecha]_[hora].csv
```

Ejemplo: `categoria_restaurantes_2025-08-17_143022.csv`

### Archivo de progreso
El progreso se guarda en:
```
data/scraper_progress.json
```

## 🔄 Ejemplo de flujo de trabajo

### Día 1: Scrapear primeras 50 empresas
```bash
# Primera tanda de 25
php src/scraper_categoria.php -c hoteles -l 25

# Segunda tanda de 25 (continúa automáticamente)
php src/scraper_categoria.php -c hoteles -l 25
```

### Día 2: Continuar con las siguientes 50
```bash
# Tercera tanda de 25 (continúa desde empresa #51)
php src/scraper_categoria.php -c hoteles -l 25

# Cuarta tanda de 25 (continúa desde empresa #76)
php src/scraper_categoria.php -c hoteles -l 25
```

### Ver cuántas empresas has procesado
```bash
php src/scraper_categoria.php --progreso
```

Salida esperada:
```
📁 Categoría: hoteles
   • Empresas procesadas: 100
   • Próxima empresa: #101
   • Última sesión: 2025-08-18 10:30:00
```

## 🎨 Opciones disponibles

| Opción | Descripción | Ejemplo |
|--------|-------------|---------|
| `-c, --categoria` | Categoría a scrapear | `-c restaurantes` |
| `-l, --limite` | Número de empresas por sesión | `-l 30` |
| `--nuevo` | Iniciar desde cero | `--nuevo` |
| `--listar` | Ver categorías disponibles | `--listar` |
| `--progreso` | Ver progreso guardado | `--progreso` |
| `--reset` | Resetear progreso | `--reset restaurantes` |
| `-h, --help` | Mostrar ayuda | `--help` |

## 💡 Tips y mejores prácticas

1. **Procesa en tandas pequeñas**: Es mejor hacer varias sesiones de 20-50 empresas que una sola de 1000
2. **Revisa el progreso regularmente**: Usa `--progreso` para ver cuántas empresas has procesado
3. **Backup de los CSV**: Los archivos CSV se acumulan, considera organizarlos por fecha
4. **Monitorea errores**: Si una empresa falla, el scraper continúa con la siguiente
5. **Respeta los límites**: No hagas scraping muy agresivo para evitar bloqueos

## 🔍 Estructura del CSV generado

El archivo CSV incluye las siguientes columnas:
- `#`: Número de empresa en la secuencia
- `Nombre`: Nombre de la empresa
- `RUC`: Registro Único de Contribuyente
- `Teléfono`: Número de contacto
- `Website`: Sitio web oficial
- `Email Probable`: Email generado basado en el dominio
- `Dirección`: Dirección física
- `Tipo`: Tipo de empresa
- `Estado`: Estado actual (Activo/Inactivo)
- `Fecha Scraping`: Timestamp del momento de extracción

## ⚠️ Notas importantes

- El progreso se guarda automáticamente después de cada sesión
- Si el script se interrumpe, el progreso se mantiene hasta la última empresa procesada exitosamente
- Los archivos CSV no se sobrescriben, cada sesión genera un nuevo archivo
- El sistema detecta automáticamente cuando se han procesado todas las empresas de una categoría

## 🐛 Solución de problemas

### El scraper no encuentra empresas
- Verifica que la categoría existe con `--listar`
- Algunas categorías pueden estar vacías o tener pocas empresas

### Quiero empezar de nuevo
```bash
php src/scraper_categoria.php --reset [categoria]
php src/scraper_categoria.php -c [categoria] -l 20 --nuevo
```

### No sé dónde quedé
```bash
php src/scraper_categoria.php --progreso
```

### Los datos están incompletos
Algunas empresas pueden no tener todos los datos disponibles. El scraper extrae lo que encuentra y deja en blanco los campos faltantes.