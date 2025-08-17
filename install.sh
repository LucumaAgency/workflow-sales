#!/bin/bash

echo "╔════════════════════════════════════════╗"
echo "║     WORKFLOW SALES - INSTALACIÓN       ║"
echo "╚════════════════════════════════════════╝"
echo ""

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP no está instalado. Por favor instala PHP 7.4+"
    exit 1
fi

echo "✅ PHP encontrado: $(php -v | head -n 1)"

# Verificar Composer
if ! command -v composer &> /dev/null; then
    echo "📦 Instalando Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

echo "✅ Composer encontrado"

# Instalar dependencias
echo "📦 Instalando dependencias PHP..."
composer install

# Crear directorios necesarios
echo "📁 Creando estructura de directorios..."
mkdir -p data/output
mkdir -p data/cache
mkdir -p logs

# Copiar configuración
if [ ! -f config/config.php ]; then
    echo "⚙️ Creando archivo de configuración..."
    cp config/config.example.php config/config.php
    echo "   Por favor edita config/config.php con tu configuración"
fi

# Permisos
chmod +x src/main.php
chmod +x install.sh

echo ""
echo "✅ ¡Instalación completada!"
echo ""
echo "Para comenzar, ejecuta:"
echo "  php src/main.php --help"
echo ""
echo "Ejemplo:"
echo "  php src/main.php --category restaurantes-peru --limit 50"