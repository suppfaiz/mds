@echo off
title Installer Master Data Sekolah (MDS)
color 0b
chcp 65001 >nul

echo ==========================================================
echo       INSTALLER MASTER DATA SEKOLAH (MDS) - WINDOWS
echo ==========================================================
echo Platform: Windows (XAMPP Environment)
echo.

:: 1. Deteksi Path XAMPP
set "XAMPP_DIR=C:\xampp"

if not exist "%XAMPP_DIR%" (
    echo [PERINGATAN] XAMPP tidak ditemukan di path default C:\xampp.
    echo.
    set /p "XAMPP_DIR=Masukkan lokasi folder instalasi XAMPP Anda (contoh: D:\xampp atau C:\xampp): "
)

:: Hapus tanda petik ganda jika ada dari input user
set "XAMPP_DIR=%XAMPP_DIR:"=%"

if not exist "%XAMPP_DIR%" (
    echo.
    echo [ERROR] Folder XAMPP "%XAMPP_DIR%" tidak valid atau tidak ditemukan.
    echo Silakan install XAMPP terlebih dahulu sebelum menjalankan script ini.
    echo.
    pause
    exit /b
)

echo [OK] XAMPP terdeteksi di: "%XAMPP_DIR%"
echo.

:: 2. Cek Lokasi File & Proses Salin ke htdocs jika diperlukan
set "CURRENT_DIR=%~dp0"
set "HTDOCS_TARGET=%XAMPP_DIR%\htdocs\master-data-sekolah"

:: Menghapus trailing backslash untuk perbandingan path
set "CHECK_PATH=%CURRENT_DIR%"
if "%CHECK_PATH:~-1%"=="\" set "CHECK_PATH=%CHECK_PATH:~0,-1%"

if /i "%CHECK_PATH%"=="%HTDOCS_TARGET%" (
    echo [INFO] Aplikasi sudah berada di folder target htdocs: "%HTDOCS_TARGET%"
    echo.
) else (
    echo [INFO] Menyalin file aplikasi ke folder target XAMPP htdocs...
    echo Asal:   "%CURRENT_DIR%"
    echo Target: "%HTDOCS_TARGET%"
    echo.
    
    if not exist "%HTDOCS_TARGET%" (
        mkdir "%HTDOCS_TARGET%" >nul 2>nul
    )
    
    :: Salin seluruh isi folder secara rekursif (melewati folder target jika berada didalamnya)
    xcopy /s /e /i /y /q "%CURRENT_DIR%*" "%HTDOCS_TARGET%\" >nul
    
    if errorlevel 1 (
        echo [ERROR] Gagal menyalin file. Silakan jalankan CMD sebagai Administrator
        echo dan ulangi kembali proses instalasi.
        echo.
        pause
        exit /b
    )
    echo [OK] Berhasil menyalin seluruh file aplikasi ke htdocs!
    echo.
    
    :: Pindah ke direktori target htdocs agar setup lanjutan berjalan di tempat yang tepat
    cd /d "%HTDOCS_TARGET%"
)

:: 3. Jalankan Layanan MySQL Server
echo [INFO] Memeriksa status MySQL Server...
tasklist /fi "imagename eq mysqld.exe" 2>nul | find /i "mysqld.exe" >nul
if %errorlevel% equ 0 (
    echo [OK] MySQL Server sudah berjalan.
) else (
    echo [INFO] Menyalakan MySQL Server dari XAMPP...
    if exist "%XAMPP_DIR%\mysql_start.bat" (
        start "" /min "%XAMPP_DIR%\mysql_start.bat"
    ) else (
        start "" /b "%XAMPP_DIR%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini" --standalone
    )
    echo Menunggu MySQL Server siap (5 detik)...
    timeout /t 5 >nul
)
echo.

:: 4. Import database
echo [INFO] Mempersiapkan database 'master_data_sekolah'...
set "MYSQL_BIN=%XAMPP_DIR%\mysql\bin\mysql.exe"
set "MYSQL_PASS="

:: Test koneksi root tanpa password
"%MYSQL_BIN%" -u root -e "CREATE DATABASE IF NOT EXISTS master_data_sekolah;" >nul 2>nul
if %errorlevel% neq 0 (
    echo [PERINGATAN] Gagal terhubung ke MySQL Server menggunakan user 'root' tanpa password.
    echo Jika MySQL Anda menggunakan password kustom, masukkan password root di bawah ini.
    echo Jika tidak menggunakan password, tekan enter untuk mencoba kembali.
    echo.
    set /p "MYSQL_PASS=Masukkan Password MySQL Root: "
)

echo.
echo [INFO] Mengimpor skema database dan data seeds awal...
if "%MYSQL_PASS%"=="" (
    :: Import tanpa password
    "%MYSQL_BIN%" -u root -e "CREATE DATABASE IF NOT EXISTS master_data_sekolah;" >nul 2>nul
    "%MYSQL_BIN%" -u root master_data_sekolah < database\schema.sql
) else (
    :: Import dengan password kustom
    "%MYSQL_BIN%" -u root -p%MYSQL_PASS% -e "CREATE DATABASE IF NOT EXISTS master_data_sekolah;" >nul 2>nul
    "%MYSQL_BIN%" -u root -p%MYSQL_PASS% master_data_sekolah < database\schema.sql
)

if %errorlevel% equ 0 (
    echo [OK] Database 'master_data_sekolah' berhasil di-import dan disiapkan!
) else (
    echo [ERROR] Gagal mengimpor database. Silakan jalankan layanan MySQL secara manual
    echo melalui XAMPP Control Panel dan import file 'database/schema.sql' melalui phpMyAdmin.
)
echo.

:: 5. Jalankan Apache Web Server
echo [INFO] Memeriksa status Apache Web Server...
tasklist /fi "imagename eq httpd.exe" 2>nul | find /i "httpd.exe" >nul
if %errorlevel% equ 0 (
    echo [OK] Apache Web Server sudah berjalan.
) else (
    echo [INFO] Menyalakan Apache Web Server dari XAMPP...
    if exist "%XAMPP_DIR%\apache_start.bat" (
        start "" /min "%XAMPP_DIR%\apache_start.bat"
    ) else (
        start "" /b "%XAMPP_DIR%\apache\bin\httpd.exe"
    )
    timeout /t 2 >nul
)
echo.

:: 6. Selesai dan Buka Browser
echo ==========================================================
echo        SETUP SELESAI! APLIKASI MDS SIAP DIGUNAKAN
echo ==========================================================
echo.
echo Alamat URL Aplikasi: http://localhost/master-data-sekolah
echo Username Default: admin
echo Password Default: admin
echo.
echo Membuka browser default Anda untuk mengakses aplikasi...
start http://localhost/master-data-sekolah/index.php
echo.
echo Tekan tombol apa saja untuk menutup jendela installer ini.
pause >nul
exit
