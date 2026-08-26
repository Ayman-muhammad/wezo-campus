#!/bin/bash

# ============================================
# WEZO CAMPUS HUB Installation Script
# Phase 1 & 2 Setup
# Powered by AYGLOBE INC
# ============================================

echo "==========================================="
echo "   WEZO CAMPUS HUB Installation"
echo "   Powered by AYGLOBE INC"
echo "   Founder: Ayman Muhammad"
echo "==========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  Please run as root or use sudo"
    exit 1
fi

# Function to check command success
check_success() {
    if [ $? -eq 0 ]; then
        echo "✅ $1"
    else
        echo "❌ $1"
        exit 1
    fi
}

# Update system
echo "📦 Updating system packages..."
apt-get update -y
check_success "System updated"

# Install required packages
echo "📦 Installing required packages..."
apt-get install -y apache2 mysql-server php php-mysql php-curl php-gd php-mbstring php-xml php-zip php-json libapache2-mod-php curl git unzip
check_success "Packages installed"

# Install PHP extensions
echo "📦 Installing PHP extensions..."
apt-get install -y php8.1-common php8.1-mysql php8.1-xml php8.1-curl php8.1-gd php8.1-mbstring php8.1-tokenizer php8.1-bcmath php8.1-zip
check_success "PHP extensions installed"

# Configure PHP
echo "⚙️  Configuring PHP..."
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/g' /etc/php/8.1/apache2/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 50M/g' /etc/php/8.1/apache2/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/g' /etc/php/8.1/apache2/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 256M/g' /etc/php/8.1/apache2/php.ini
check_success "PHP configured"

# Start and enable services
echo "🚀 Starting services..."
systemctl start apache2
systemctl enable apache2
systemctl start mysql
systemctl enable mysql
check_success "Services started"

# Configure MySQL
echo "🔐 Configuring MySQL security..."
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';"
mysql -e "DELETE FROM mysql.user WHERE User='';"
mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
mysql -e "DROP DATABASE IF EXISTS test;"
mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
mysql -e "FLUSH PRIVILEGES;"
check_success "MySQL secured"

# Create database and user
echo "🗄️  Creating database..."
mysql -e "CREATE DATABASE IF NOT EXISTS wezo_campus_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'wch_admin'@'localhost' IDENTIFIED BY 'WCH_secure_pass_2024!';"
mysql -e "GRANT ALL PRIVILEGES ON wezo_campus_hub.* TO 'wch_admin'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
check_success "Database created"

# Create project directory
echo "📁 Creating project structure..."
mkdir -p /var/www/wezo-campus
mkdir -p /var/www/wezo-campus/public/assets/{css,js,images,uploads}
mkdir -p /var/www/wezo-campus/admin/{users,ads,marketplace,hostels,events,content-review,analytics,settings}
mkdir -p /var/www/wezo-campus/core
mkdir -p /var/www/wezo-campus/database
mkdir -p /var/www/wezo-campus/api
mkdir -p /var/www/wezo-campus/tests

# Set permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data /var/www/wezo-campus
chmod -R 755 /var/www/wezo-campus
chmod -R 777 /var/www/wezo-campus/public/assets/uploads
check_success "Permissions set"

# Create Apache virtual host
echo "🌐 Configuring Apache..."
cat > /etc/apache2/sites-available/wezo-campus.conf << EOF
<VirtualHost *:80>
    ServerName wezocampus.local
    ServerAdmin admin@ayglobe.com
    DocumentRoot /var/www/wezo-campus/public
    
    <Directory /var/www/wezo-campus/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/wezo-campus-error.log
    CustomLog \${APACHE_LOG_DIR}/wezo-campus-access.log combined
    
    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
EOF

# Enable site and modules
a2ensite wezo-campus.conf
a2dissite 000-default.conf
a2enmod rewrite headers
systemctl restart apache2
check_success "Apache configured"

# Install Composer (if not installed)
echo "📦 Installing Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi
check_success "Composer installed"

# Create environment file
echo "⚙️  Creating environment configuration..."
cat > /var/www/wezo-campus/.env << EOF
# WEZO CAMPUS HUB Configuration
# Powered by AYGLOBE INC

APP_NAME="WEZO CAMPUS HUB"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://wezocampus.local

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=wezo_campus_hub
DB_USERNAME=wch_admin
DB_PASSWORD=WCH_secure_pass_2024!

# Security
APP_KEY=base64:$(openssl rand -base64 32)
JWT_SECRET=$(openssl rand -base64 32)

# File Uploads
MAX_UPLOAD_SIZE=52428800
ALLOWED_EXTENSIONS=pdf,doc,docx,jpg,jpeg,png,gif,mp4

# Company Info
COMPANY_NAME="AYGLOBE INC"
FOUNDER_NAME="Ayman Muhammad"
COMPANY_EMAIL="contact@ayglobe.com"
SUPPORT_EMAIL="support@wezocampushub.com"
EOF

chown www-data:www-data /var/www/wezo-campus/.env
chmod 600 /var/www/wezo-campus/.env
check_success "Environment configured"

# Create database schema
echo "🗃️  Creating database schema..."
mysql wezo_campus_hub < /var/www/wezo-campus/database/schema.sql
check_success "Database schema created"

# Install sample data (optional)
echo "📊 Installing sample data..."
mysql wezo_campus_hub < /var/www/wezo-campus/database/sample_data.sql
check_success "Sample data installed"

echo ""
echo "==========================================="
echo "   INSTALLATION COMPLETE!"
echo "==========================================="
echo ""
echo "🌐 Access your site: http://wezocampus.local"
echo "🔑 Admin Panel: http://wezocampus.local/admin"
echo "📧 Default Admin: admin@wezocampus.local / admin123"
echo ""
echo "📋 Next steps:"
echo "   1. Update .env with production values"
echo "   2. Configure SSL certificate"
echo "   3. Set up backup schedule"
echo "   4. Configure email server"
echo ""
echo "💡 Powered by AYGLOBE INC"
echo "   Founder: Ayman Muhammad"
echo "==========================================="