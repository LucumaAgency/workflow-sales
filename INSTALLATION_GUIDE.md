# 🛠️ Guía de Instalación Detallada - Workflow Sales

## 📋 Tabla de Contenidos
1. [Instalación en Windows](#windows)
2. [Instalación en Mac](#mac)
3. [Instalación en Linux](#linux)
4. [Solución de Problemas](#troubleshooting)
5. [Verificación](#verificacion)

---

## <a name="windows"></a>💻 Instalación en Windows

### Opción A: Con XAMPP (Recomendado para principiantes)

#### Paso 1: Instalar XAMPP
1. Descargar XAMPP desde: https://www.apachefriends.org/download.html
2. Ejecutar el instalador
3. Durante la instalación:
   - ✅ PHP (obligatorio)
   - ⬜ MySQL (opcional)
   - ⬜ Apache (opcional)
4. Instalar en `C:\xampp`

#### Paso 2: Instalar Composer
1. Descargar: https://getcomposer.org/Composer-Setup.exe
2. Ejecutar el instalador
3. Cuando pregunte por PHP, seleccionar: `C:\xampp\php\php.exe`
4. ✅ Marcar "Add composer to PATH"
5. Finalizar instalación

#### Paso 3: Instalar Git
1. Descargar: https://git-scm.com/download/win
2. Instalar con opciones por defecto

#### Paso 4: Clonar y configurar el proyecto
```cmd
# Abrir Command Prompt o PowerShell
cd C:\
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales

# Instalar dependencias
composer install

# Crear carpetas necesarias
mkdir data\output
mkdir data\cache
mkdir logs

# Copiar configuración
copy config\config.example.php config\config.php

# Ejecutar
php src/main.php --help
```

### Opción B: Con PHP Standalone

#### Paso 1: Instalar PHP
1. Descargar PHP 8.0: https://windows.php.net/downloads/releases/
   - Elegir: "VS16 x64 Non Thread Safe"
2. Extraer en `C:\php`
3. Añadir `C:\php` al PATH del sistema:
   - Panel de Control → Sistema → Configuración avanzada
   - Variables de entorno → PATH → Editar
   - Añadir: `C:\php`

#### Paso 2: Configurar PHP
1. En `C:\php`, copiar `php.ini-development` a `php.ini`
2. Editar `php.ini` y descomentar (quitar `;`):
   ```ini
   extension=curl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   ```

#### Paso 3: Instalar Composer
```powershell
# En PowerShell como Administrador
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
move composer.phar C:\php\composer.phar
echo @php "%~dp0composer.phar" %*>C:\php\composer.bat
set PATH=%PATH%;C:\php
```

#### Paso 4: Configurar el proyecto
```cmd
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales
composer install
mkdir data\output logs
copy config\config.example.php config\config.php
php src/main.php --help
```

### Opción C: Instalación sin Composer global

Si no puedes instalar Composer globalmente:

```cmd
# En la carpeta del proyecto
cd workflow-sales

# Descargar composer.phar localmente
curl -sS https://getcomposer.org/installer | php

# O con PowerShell:
Invoke-WebRequest https://getcomposer.org/installer -OutFile composer-setup.php
php composer-setup.php
del composer-setup.php

# Usar composer.phar en lugar de composer
php composer.phar install

# Ejecutar la aplicación
php src/main.php --help
```

---

## <a name="mac"></a>🍎 Instalación en Mac

### Con Homebrew (Recomendado)

```bash
# Instalar Homebrew si no lo tienes
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Instalar PHP
brew install php@8.0

# Instalar Composer
brew install composer

# Clonar proyecto
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales

# Instalar dependencias
composer install

# Crear directorios
mkdir -p data/output data/cache logs

# Copiar configuración
cp config/config.example.php config/config.php

# Ejecutar
php src/main.php --help
```

### Sin Homebrew

```bash
# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Verificar PHP (Mac viene con PHP)
php -v

# Continuar con la instalación del proyecto
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales
composer install
mkdir -p data/output logs
cp config/config.example.php config/config.php
```

---

## <a name="linux"></a>🐧 Instalación en Linux

### Ubuntu/Debian

```bash
# Actualizar paquetes
sudo apt update

# Instalar PHP y extensiones
sudo apt install php7.4 php7.4-cli php7.4-curl php7.4-mbstring php7.4-xml php7.4-zip

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Git
sudo apt install git

# Clonar y configurar proyecto
git clone https://github.com/LucumaAgency/workflow-sales.git
cd workflow-sales
composer install
mkdir -p data/output data/cache logs
cp config/config.example.php config/config.php

# Dar permisos
chmod 755 src/main.php
chmod 777 data/output logs

# Ejecutar
php src/main.php --help
```

### CentOS/RHEL/Fedora

```bash
# Instalar PHP
sudo yum install php php-cli php-curl php-mbstring php-xml php-zip

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Resto igual que Ubuntu...
```

---

## <a name="troubleshooting"></a>🔧 Solución de Problemas Comunes

### Error: "composer: command not found"

**Windows:**
```cmd
# Usar ruta completa
C:\ProgramData\ComposerSetup\bin\composer install

# O usar composer.phar
php composer.phar install
```

**Mac/Linux:**
```bash
# Reinstalar composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Error: "Failed to open stream: No such file or directory vendor/autoload.php"

```bash
# Las dependencias no están instaladas
composer install
# o
php composer.phar install
```

### Error: "PHP version too old"

Actualizar PHP o modificar `composer.json`:
```json
"require": {
    "php": ">=7.2"  // Cambiar a tu versión
}
```

### Error: "SSL certificate problem"

**Windows:** En `php.ini`:
```ini
curl.cainfo = "C:\xampp\php\extras\ssl\cacert.pem"
```

Descargar certificados de: https://curl.se/docs/caextract.html

### Error: "Memory limit"

En `php.ini`:
```ini
memory_limit = 256M
```

O al ejecutar:
```bash
php -d memory_limit=256M src/main.php
```

### Error: "Permission denied"

**Linux/Mac:**
```bash
chmod 755 src/main.php
chmod -R 777 data/ logs/
```

**Windows:** Ejecutar terminal como Administrador

---

## <a name="verificacion"></a>✅ Verificación de Instalación

### 1. Verificar PHP
```bash
php -v
# Debe mostrar: PHP 7.4.x o superior
```

### 2. Verificar Composer
```bash
composer --version
# Debe mostrar: Composer version 2.x.x
```

### 3. Verificar dependencias
```bash
cd workflow-sales
composer show
# Debe listar: guzzlehttp/guzzle, symfony/dom-crawler, etc.
```

### 4. Test rápido
```bash
php src/main.php --help
# Debe mostrar el menú de ayuda
```

### 5. Test funcional
```bash
php src/main.php --category restaurantes-peru --limit 3
# Debe buscar 3 restaurantes y generar un CSV
```

## 📝 Checklist de Instalación

- [ ] PHP 7.4+ instalado
- [ ] Composer instalado
- [ ] Git instalado
- [ ] Proyecto clonado
- [ ] `composer install` ejecutado
- [ ] Carpetas data/output y logs creadas
- [ ] config.php copiado
- [ ] Test con --help funciona
- [ ] Primer scraping exitoso

## 🆘 ¿Necesitas ayuda?

1. Revisa los logs en `logs/app.log`
2. Abre un issue: https://github.com/LucumaAgency/workflow-sales/issues
3. Incluye:
   - Sistema operativo
   - Versión de PHP (`php -v`)
   - Error completo
   - Pasos que seguiste

---

*Última actualización: Enero 2024*