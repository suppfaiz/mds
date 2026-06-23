#!/bin/bash
# ==============================================================================
#            SCRIPT VALIDASI KEAMANAN - MASTER DATA SEKOLAH (MDS)
# ==============================================================================
# 
# Skrip ini menguji aturan hardening Nginx dan perlindungan sesi PHP secara lokal.
# Cara menjalankan:
# bash verify_security.sh
# ==============================================================================

# Warna Output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

clear
echo -e "${BLUE}======================================================================${NC}"
echo -e "${YELLOW}           PENGUJIAN VALIDASI KEAMANAN SISTEM (SECURITY AUDIT)${NC}"
echo -e "${BLUE}======================================================================${NC}"

# Target URL (Lokal)
TARGET_URL="http://127.0.0.1"

# Fungsi pembantu untuk mencocokkan status HTTP
check_url() {
    local path="$1"
    local expected_code="$2"
    local description="$3"
    
    # Kirim request curl dan ambil HTTP status code
    local code=$(curl -s -o /dev/null -w "%{http_code}" "$TARGET_URL$path")
    
    if [ "$code" = "$expected_code" ]; then
        echo -e "${GREEN}[PASS]${NC} ${description}"
        echo -e "       Path: ${path} (Status: ${code} - Sesuai Ekspektasi)"
    else
        echo -e "${RED}[FAIL]${NC} ${description}"
        echo -e "       Path: ${path} (Status: ${code} - HARUSNYA ${expected_code})"
    fi
}

echo -e "\n${BLUE}[1/3] Menguji Proteksi Folder & File Sensitif (Nginx Hardening)...${NC}"
echo -e "----------------------------------------------------------------------"
check_url "/config/db.php" "404" "Memblokir akses langsung ke folder config/db.php"
check_url "/database/schema.sql" "404" "Memblokir unduhan berkas skema SQL awal (.sql)"
check_url "/includes/audit.php" "404" "Memblokir akses langsung ke folder includes/"
check_url "/.env" "404" "Memblokir akses berkas konfigurasi rahasia .env"
check_url "/install.sh" "404" "Memblokir akses langsung berkas skrip instalasi .sh"

echo -e "\n${BLUE}[2/3] Menguji Pengaman Folder Uploads (Anti-Webshell & Dokumen)...${NC}"
echo -e "----------------------------------------------------------------------"
check_url "/uploads/secure/secret.txt" "403" "Memblokir akses direktori uploads/secure/"
check_url "/uploads/guru/ktp_aisyah.pdf" "403" "Memblokir akses langsung file PDF di uploads/"
check_url "/uploads/webshell.php" "403" "Mencegah eksekusi file PHP (Anti-Webshell) di uploads/"

echo -e "\n${BLUE}[3/3] Menguji Mekanisme Portal Admin Gate (Secret URL)...${NC}"
echo -e "----------------------------------------------------------------------"
check_url "/auth/login.php" "404" "Memblokir akses langsung ke halaman login (Tanpa Gate)"
check_url "/01aac7d617a6d8b2f90a8c2d5e7b4f3a" "302" "Mengizinkan akses lewat URL Rahasia (Redirect ke Login)"

echo -e "${BLUE}======================================================================${NC}"
echo -e "${YELLOW}                 PENGUJIAN SELESAI${NC}"
echo -e "${BLUE}======================================================================${NC}"
