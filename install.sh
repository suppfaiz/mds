#!/bin/bash

# ==============================================================================
#            INSTALLER MASTER DATA SEKOLAH (MDS) - LINUX VPS (Ubuntu/Debian)
# ==============================================================================
#
# Skrip ini menginstal seluruh stack web server (Apache, PHP, MariaDB),
# membuat database, mengimpor skema, mengatur hak akses, dan
# mengonfigurasi Apache untuk mendukung file .htaccess.
#
# Cara menjalankan:
# sudo sh install.sh
# ==============================================================================

# Warna Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Bersihkan Layar
clear

echo -e "${BLUE}======================================================================${NC}"
echo -e "${PURPLE}          INSTALLER OTOMATIS MASTER DATA SEKOLAH (MDS)${NC}"
echo -e "${BLUE}======================================================================${NC}"
echo -e "${CYAN}Sistem Operasi Target: Ubuntu / Debian Server${NC}"
echo -e "${CYAN}Direktori Aplikasi:   $(pwd)${NC}"
echo ""

# 1. Pastikan dijalankan sebagai root/sudo
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}[ERROR] Harap jalankan skrip ini menggunakan sudo atau sebagai root!${NC}"
  echo -e "${YELLOW}Contoh: sudo sh install.sh${NC}"
  exit 1
fi

# 2. Deteksi OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS_NAME=$NAME
else
    OS_NAME=$(uname -s)
fi

echo -e "${GREEN}[INFO] Sistem Operasi Terdeteksi: ${OS_NAME}${NC}"
echo -e "${YELLOW}[INFO] Memulai proses instalasi stack server...${NC}"
echo ""

# Setup Target Directory /var/www/mds/mds
TARGET_DIR="/var/www/mds/mds"
CURRENT_DIR="$(pwd)"

if [ "$CURRENT_DIR" != "$TARGET_DIR" ]; then
    echo -e "${BLUE}[INFO] Menyiapkan direktori target: ${TARGET_DIR}...${NC}"
    # Buat direktori target secara rekursif
    mkdir -p "$TARGET_DIR"
    # Atur izin direktori induk /var/www/mds agar dapat diakses oleh Apache
    chmod 755 /var/www/mds
    
    echo -e "${BLUE}[INFO] Menyalin file proyek ke ${TARGET_DIR}...${NC}"
    # Salin seluruh isi folder proyek ke target
    cp -r "$CURRENT_DIR"/. "$TARGET_DIR"/
    
    # Pindah ke direktori target agar proses berikutnya berjalan di tempat yang tepat
    cd "$TARGET_DIR"
    echo -e "${GREEN}[OK] File proyek berhasil disalin ke ${TARGET_DIR}.${NC}"
    echo ""
fi

# 3. Update Package List & Upgrade Minimal
echo -e "${BLUE}[1/7] Memperbarui daftar paket sistem...${NC}"
apt-get update -y
if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR] Gagal memperbarui paket sistem. Periksa koneksi internet Anda.${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] Pembaruan paket selesai.${NC}"
echo ""

# 4. Instalasi Apache, MariaDB, PHP & Ekstensi yang Dibutuhkan
echo -e "${BLUE}[2/7] Menginstal Apache2, MariaDB, PHP & dependensi lainnya...${NC}"
apt-get install -y apache2 mariadb-server php php-mysql php-xml php-mbstring php-curl php-gd unzip curl openssl
if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR] Instalasi dependensi gagal. Pastikan repository apt Anda aktif.${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] Instalasi paket selesai.${NC}"
echo ""

# 5. Menyalakan & Mengaktifkan Layanan Server
echo -e "${BLUE}[3/7] Mengaktifkan layanan Apache2 dan MariaDB...${NC}"
systemctl start apache2
systemctl enable apache2
systemctl start mariadb
systemctl enable mariadb
echo -e "${GREEN}[OK] Layanan Apache2 & MariaDB berhasil berjalan.${NC}"
echo ""

# 6. Konfigurasi Database (Membuat User & Database Aman)
echo -e "${BLUE}[4/7] Mengonfigurasi database MySQL/MariaDB...${NC}"

DB_NAME="master_data_sekolah"
DB_USER="mds_user"
# Hasilkan password acak yang aman
DB_PASS=$(openssl rand -base64 16 | tr -d '/=+' | cut -c1-16)

# Kueri MySQL untuk setup database
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}[OK] Database \`${DB_NAME}\` dan user \`${DB_USER}\` berhasil dibuat.${NC}"
echo ""

# Impor skema database SQL
echo -e "${BLUE}[5/7] Mengimpor skema database awal (seeds)...${NC}"
if [ -f "database/schema.sql" ]; then
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < database/schema.sql
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}[OK] Skema database berhasil diimpor!${NC}"
    else
        echo -e "${RED}[ERROR] Gagal mengimpor file database/schema.sql.${NC}"
        exit 1
    fi
else
    echo -e "${RED}[ERROR] Berkas skema 'database/schema.sql' tidak ditemukan! Pastikan Anda menjalankan skrip ini dari folder root proyek.${NC}"
    exit 1
fi
echo ""

# 7. Konfigurasi Aplikasi (Update db.php)
echo -e "${BLUE}[6/7] Menyesuaikan berkas konfigurasi database...${NC}"
if [ -f "config/db.php" ]; then
    # Backup config sebelum diubah
    cp config/db.php config/db.php.bak
    
    # Update username & password ke db.php
    sed -i "s/\$username = 'root';/\$username = '${DB_USER}';/g" config/db.php
    sed -i "s/\$password = '';/\$password = '${DB_PASS}';/g" config/db.php
    
    echo -e "${GREEN}[OK] Berkas 'config/db.php' berhasil diperbarui dengan kredensial baru.${NC}"
else
    echo -e "${RED}[ERROR] Berkas 'config/db.php' tidak ditemukan!${NC}"
    exit 1
fi
echo ""

# 8. Konfigurasi VirtualHost Apache & Hak Akses Folder
echo -e "${BLUE}[7/7] Mengatur konfigurasi web server Apache & Hak Akses...${NC}"

APP_DIR=$(pwd)

# Tulis file VirtualHost baru ke Apache untuk mengarahkan ke folder saat ini & izinkan .htaccess (.htaccess)
cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Aktifkan modul Rewrite (untuk htaccess)
a2enmod rewrite

# Atur Kepemilikan & Hak Akses berkas
chown -R www-data:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 755 {} \;
find "${APP_DIR}" -type f -exec chmod 644 {} \;
# Pastikan skrip installer ini tetap executable
chmod +x "${APP_DIR}/install.sh"

# Restart Apache
systemctl restart apache2
echo -e "${GREEN}[OK] Konfigurasi Apache & Hak Akses berhasil diperbarui.${NC}"
echo ""

# Dapatkan IP Publik VPS
SERVER_IP=$(curl -s https://ifconfig.me)
if [ -empty "$SERVER_IP" ]; then
    SERVER_IP=$(hostname -I | awk '{print $1}')
fi

# ==============================================================================
#                      SETUP SELESAI - INFORMASI LOGIN
# ==============================================================================
echo -e "${GREEN}======================================================================${NC}"
echo -e "${GREEN}             PROSES INSTALASI SELESAI DENGAN SUKSES!${NC}"
echo -e "${GREEN}======================================================================${NC}"
echo ""
echo -e "Aplikasi Master Data Sekolah (MDS) sekarang dapat diakses melalui browser:"
echo -e "URL Utama:      ${YELLOW}http://${SERVER_IP}/index.php${NC}"
echo -e "Portal PMB:     ${YELLOW}http://${SERVER_IP}/pmb/login.php${NC}"
echo ""
echo -e "${BLUE}Kredensial Default Login Aplikasi:${NC}"
echo -e "----------------------------------------------------------------------"
echo -e "| Peran (Role)     | Username       | Password     |"
echo -e "----------------------------------------------------------------------"
echo -e "| Super Admin      | admin          | admin        |"
echo -e "| Operator         | operator       | operator     |"
echo -e "| Guru             | guru           | guru         |"
echo -e "| Kepala Sekolah   | kepsek         | kepsek       |"
echo -e "----------------------------------------------------------------------"
echo ""
echo -e "${BLUE}Informasi Teknis Database (Tersimpan di config/db.php):${NC}"
echo -e "Database Name:   ${CYAN}${DB_NAME}${NC}"
echo -e "Database User:   ${CYAN}${DB_USER}${NC}"
echo -e "Database Pass:   ${CYAN}${DB_PASS}${NC}"
echo ""
echo -e "${YELLOW}Catatan Keamanan: Silakan hapus file install.sh setelah instalasi selesai${NC}"
echo -e "${YELLOW}atau ubah hak aksesnya demi keamanan server Anda.${NC}"
echo -e "${GREEN}======================================================================${NC}"
