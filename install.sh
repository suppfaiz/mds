#!/bin/bash

# ==============================================================================
#            INSTALLER MASTER DATA SEKOLAH (MDS) - LINUX VPS (Ubuntu/Debian)
# ==============================================================================
#
# Skrip ini menginstal seluruh stack web server (Nginx, PHP-FPM, MariaDB),
# membuat database, mengimpor skema, mengatur hak akses, dan
# mengonfigurasi Nginx server block serta mengamankan folder sensitif.
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

# Override echo untuk POSIX / sh (dash) compatibility
echo() {
    if [ "$1" = "-e" ]; then
        shift
        printf "%b\n" "$*"
    else
        printf "%b\n" "$*"
    fi
}

# Bersihkan Layar
clear

echo -e "${BLUE}======================================================================${NC}"
echo -e "${PURPLE}          INSTALLER OTOMATIS MASTER DATA SEKOLAH (MDS)${NC}"
echo -e "${BLUE}======================================================================${NC}"
echo -e "${CYAN}Sistem Operasi Target: Ubuntu / Debian Server${NC}"
echo -e "${CYAN}Direktori Aplikasi:   $(pwd)${NC}"
echo ""

# 1. Pastikan dijalankan sebagai root/sudo
if [ "$(id -u)" -ne 0 ]; then
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
    # Atur izin direktori induk /var/www/mds agar dapat diakses oleh Nginx
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

# Hentikan Apache2 terlebih dahulu jika sedang berjalan agar port 80 tidak bentrok
systemctl stop apache2 2>/dev/null
systemctl disable apache2 2>/dev/null

# 4. Instalasi Nginx, MariaDB, PHP-FPM & Ekstensi yang Dibutuhkan
echo -e "${BLUE}[2/7] Menginstal Nginx, MariaDB, PHP-FPM & dependensi lainnya...${NC}"
apt-get install -y nginx mariadb-server php-fpm php-mysql php-xml php-mbstring php-curl php-gd unzip curl openssl
if [ $? -ne 0 ]; then
    echo -e "${RED}[ERROR] Instalasi dependensi gagal. Pastikan repository apt Anda aktif.${NC}"
    exit 1
fi
echo -e "${GREEN}[OK] Instalasi paket selesai.${NC}"
echo ""

# 5. Menyalakan & Mengaktifkan Layanan Server
echo -e "${BLUE}[3/7] Mengaktifkan layanan Nginx, MariaDB, dan PHP-FPM...${NC}"
systemctl start nginx
systemctl enable nginx
systemctl start mariadb
systemctl enable mariadb

# Cari nama unit service php-fpm yang terinstal secara dinamis
FPM_SERVICE=$(systemctl list-unit-files | grep -E -o 'php[0-9.]+-fpm\.service' | head -n 1)
if [ -n "$FPM_SERVICE" ]; then
    systemctl start "$FPM_SERVICE"
    systemctl enable "$FPM_SERVICE"
fi
echo -e "${GREEN}[OK] Layanan Nginx, MariaDB, & PHP-FPM berhasil berjalan.${NC}"
echo ""

# 6. Konfigurasi Database (Membuat User & Database Aman)
echo -e "${BLUE}[4/7] Mengonfigurasi database MySQL/MariaDB...${NC}"

DB_NAME="master_data_sekolah"
DB_USER="mds_user"
# Hasilkan password acak yang aman
DB_PASS=$(openssl rand -base64 16 | tr -d '/=+' | cut -c1-16)

# Kueri MySQL untuk setup database lokal & remote
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"

# Buat juga user remote '%' agar bisa diakses langsung via DBeaver
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';"
mysql -e "FLUSH PRIVILEGES;"

# Konfigurasi MariaDB agar mendengarkan di semua interface (akses remote)
MARIADB_CONF="/etc/mysql/mariadb.conf.d/50-server.cnf"
if [ -f "$MARIADB_CONF" ]; then
    echo -e "${BLUE}[INFO] Membuka port remote MariaDB (bind-address = 0.0.0.0)...${NC}"
    sed -i "s/bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/g" "$MARIADB_CONF"
    systemctl restart mariadb
fi

echo -e "${GREEN}[OK] Database \`${DB_NAME}\` dan user \`${DB_USER}\` (lokal & remote) berhasil dikonfigurasi.${NC}"
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

# 7. Konfigurasi Aplikasi (Create/Update .env)
echo -e "${BLUE}[6/7] Menyesuaikan berkas konfigurasi .env...${NC}"
ENV_FILE=".env"
EXISTING_KEY=""

if [ -f "$ENV_FILE" ]; then
    # Ambil APP_KEY yang sudah ada agar tidak berubah saat reinstall
    EXISTING_KEY=$(grep '^APP_KEY=' "$ENV_FILE" | cut -d'=' -f2)
fi

if [ -z "$EXISTING_KEY" ]; then
    # Generate key acak 32 karakter jika belum ada
    APP_KEY=$(openssl rand -base64 32 | tr -d '/=+' | cut -c1-32)
else
    APP_KEY="$EXISTING_KEY"
fi

# Tulis berkas .env baru
cat <<EOF > "$ENV_FILE"
DB_HOST=localhost
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
APP_KEY=${APP_KEY}
EOF

echo -e "${GREEN}[OK] Berkas '.env' berhasil dibuat/diperbarui dengan kredensial baru.${NC}"
echo ""

# 8. Konfigurasi VirtualHost Nginx & Hak Akses Folder
echo -e "${BLUE}[7/7] Mengatur konfigurasi web server Nginx & Hak Akses...${NC}"

APP_DIR=$(pwd)

# Cari file socket PHP-FPM yang aktif di sistem secara dinamis
PHP_SOCK=$(find /run/php/ -name "php*.sock" | head -n 1)
if [ -z "$PHP_SOCK" ]; then
    # Jika tidak ketemu, coba backup default path berdasarkan FPM service
    PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
fi

# Tulis file Server Block baru ke Nginx dengan pengaman berkas sensitif & anti-webshell
cat <<EOF > /etc/nginx/sites-available/default
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    root \${APP_DIR};
    index index.php index.html index.htm;

    server_name _;

    # Menonaktifkan listing direktori untuk keamanan
    autoindex off;

    # =========================================================
    # SECRET ADMIN PATH
    # Akses: http://192.168.200.142/01aac7d617a6d8b2
    #
    # Nginx langsung jalankan gate.php saat path ini diakses.
    # Tidak pakai rewrite — lebih simpel dan pasti bekerja.
    # =========================================================
    location ~ ^/01aac7d617a6d8b2 {
        fastcgi_pass  unix:\${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \${APP_DIR}/gate.php;
        fastcgi_param REQUEST_URI     \$request_uri;
        include       fastcgi_params;
    }

    # Blokir akses langsung ke gate.php via nama file
    location = /gate.php {
        return 404;
    }

    # =========================================================
    # ROUTE NORMAL
    # =========================================================
    location / {
        try_files \$uri \$uri/ =404;
    }

    # Blok akses langsung ke dokumen privat (menggantikan Deny from all di .htaccess)
    location ^~ /uploads/secure/ {
        deny all;
        return 403;
    }

    # Mencegah akses langsung dari luar ke berkas dokumen sensitif di folder uploads
    location ~* ^/uploads/.*\.(pdf|docx|xlsx|doc|xls|zip|rar|txt|csv)\$ {
        deny all;
        return 403;
    }

    # Mencegah eksekusi file PHP di folder uploads (keamanan ekstra anti-webshell)
    location ~* ^/uploads/.*\.php\$ {
        deny all;
    }

    # Folder config & system tetap diblokir (disembunyikan dengan 404)
    location ^~ /config/ {
        return 404;
    }

    # Blok akses langsung ke folder database/ (disembunyikan dengan 404)
    location ^~ /database/ {
        return 404;
    }

    # Blok akses langsung ke folder includes/ (disembunyikan dengan 404)
    location ^~ /includes/ {
        return 404;
    }

    # Teruskan request PHP ke socket PHP-FPM
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:\${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Blok akses berkas konfigurasi sensitif (.git, .htaccess, .env, dll)
    location ~ /\. {
        deny all;
        return 404;
    }

    # Blokir file dengan ekstensi tertentu
    location ~* \.(sh|bat|sql|bak|log|env)\$ {
        deny all;
        return 404;
    }
}
EOF

# Pastikan symlink Nginx sites-enabled aktif
if [ ! -f /etc/nginx/sites-enabled/default ]; then
    ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default 2>/dev/null
fi

# Atur Kepemilikan & Hak Akses berkas
chown -R www-data:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 755 {} \;
find "${APP_DIR}" -type f -exec chmod 644 {} \;
# Pastikan skrip installer ini tetap executable
chmod +x "${APP_DIR}/install.sh"

# Tes konfigurasi Nginx dan Restart
nginx -t
if [ $? -eq 0 ]; then
    systemctl restart nginx
    # Restart php-fpm juga untuk penyegaran
    if [ -n "$FPM_SERVICE" ]; then
        systemctl restart "$FPM_SERVICE"
    fi
    echo -e "${GREEN}[OK] Konfigurasi Nginx & Hak Akses berhasil diperbarui.${NC}"
else
    echo -e "${RED}[ERROR] Konfigurasi Nginx salah! Periksa kembali file /etc/nginx/sites-available/default.${NC}"
    exit 1
fi
echo ""

# Dapatkan IP Lokal Server
SERVER_IP=$(hostname -I | awk '{print $1}')
if [ -z "$SERVER_IP" ]; then
    SERVER_IP="127.0.0.1"
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
