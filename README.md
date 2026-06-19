# Master Data Sekolah (MDS)

Aplikasi pusat repositori data akademik dan digital arsip dokumen untuk **Siswa, Guru, dan Pegawai**. Dirancang secara native (tanpa framework) dengan **PHP & MySQL** agar sangat ringan dijalankan pada komputer lama dengan spesifikasi RAM 4GB.

## Fitur Utama

- **Dashboard Statistik**: Menampilkan rangkuman total siswa, guru, kelas, dokumen, serta persebaran kelas dan log aktivitas.
- **Login Multi-Role**: Sistem hak akses berpemilik terbagi menjadi 4 peran:
  - **Super Admin**: Akses penuh, manajemen user, pencadangan/restorasi database, audit logs.
  - **Operator**: Mengelola data siswa, guru, dan berkas unggahan.
  - **Guru**: Hak akses baca-saja data siswa. Dapat mengelola berkas/dokumen pribadinya.
  - **Kepala Sekolah**: Hak akses baca-saja seluruh laporan dan log aktivitas.
- **CRUD Siswa & Guru**: Mengelola profil terperinci dilengkapi fitur upload foto profil.
- **Arsip Dokumen Digital**: Locker penyimpanan berkas dengan validasi file (PDF, JPG, PNG) berukuran hingga 5MB.
- **Cetak Laporan**: Cetak profil kartu siswa ramah printer dan export data ke Microsoft Excel (.xls).
- **REST API Sederhana**: Integrasi data keluar-masuk berformat JSON untuk aplikasi eksternal.
- **Perawatan Sistem**: Backup otomatis database menjadi file SQL serta menu pemulihan (restore) database.
- **Audit Log Aktivitas**: Log otomatis setiap aksi pengguna mencakup waktu, nama user, aktivitas, keterangan, dan alamat IP.
- **Tema Gelap (Dark Mode)**: Antarmuka yang ramah di mata dengan tombol sakelar tema yang persisten (tersimpan di lokal).

---

## Struktur Folder

```text
Wathonul Master Core/
├── api/
│   ├── guru.php                # REST API CRUD untuk Guru
│   ├── payroll.php             # REST API untuk data Payroll
│   └── siswa.php               # REST API CRUD untuk Siswa
├── assets/
│   ├── css/
│   │   └── style.css           # Styling kustom, layout, & CSS Dark Mode
│   └── js/
│       └── main.js             # Skrip interaktif & tema switcher
├── auth/
│   ├── login.php               # Halaman Login
│   └── logout.php              # Logout prosesor
├── config/
│   └── db.php                  # Koneksi database MySQL PDO & session helper
├── database/
│   ├── backup.php              # Pencadangan/Restorasi Database UI
│   └── schema.sql              # Struktur tabel & data awal (seeds)
├── dokumen/
│   ├── delete.php              # Penghapus berkas dokumen
│   ├── upload.php              # Pengunggah berkas dokumen
│   └── view.php                # Gatekeeper pengaman akses dokumen
├── guru/
│   ├── create.php              # Form tambah guru
│   ├── delete.php              # Penghapus data guru & filenya
│   ├── detail.php              # Detail profil & locker dokumen guru
│   ├── edit.php                # Form ubah guru
│   ├── export_excel.php        # Cetak Excel data guru
│   └── index.php               # Daftar guru
├── karyawan/
│   ├── create.php              # Form tambah staf karyawan
│   ├── delete.php              # Penghapus data staf karyawan
│   ├── detail.php              # Detail profil & locker dokumen staf
│   ├── edit.php                # Form ubah staf karyawan
│   ├── export_excel.php        # Cetak Excel data staf karyawan
│   └── index.php               # Daftar staf karyawan
├── kelas/
│   ├── index.php               # Daftar kelas, Form Tambah & Edit Kelas
│   └── delete.php              # Penghapus data pilihan kelas
├── includes/
│   ├── audit.php               # Fungsi pembantu log aktivitas
│   ├── auth_check.php          # Fungsi validasi session & hak akses
│   ├── footer.php              # Bagian kaki HTML & import JS
│   ├── header.php              # Bagian atas HTML & Navigasi
│   └── sidebar.php             # Navigasi samping kustom dropdown per role
├── logs/
│   └── index.php               # Tabel log aktivitas user
├── payroll/
│   ├── create.php              # Form pencatatan gaji & auto-kalkulasi
│   ├── delete.php              # Penghapus data slip gaji
│   ├── edit.php                # Form ubah komponen gaji
│   ├── export_excel.php        # Cetak Excel rekapitulasi gaji bulanan
│   ├── index.php               # Daftar gaji & filter periode
│   └── print.php               # Slip gaji A4 terbilang teks rupiah
├── presensi/
│   ├── pegawai.php             # Lembar absensi guru & karyawan (UNION)
│   ├── siswa.php               # Lembar absensi harian siswa per kelas
│   └── siswa_rekap.php         # Matriks absensi kelas bulanan & Excel
├── siswa/
│   ├── create.php              # Form tambah siswa & wali murid
│   ├── delete.php              # Penghapus data siswa & filenya
│   ├── detail.php              # Detail profil, transkrip nilai, & riwayat SPP
│   ├── edit.php                # Form ubah siswa & wali murid
│   ├── export_excel.php        # Cetak Excel data siswa
│   ├── import.php              # Portal bulk importer excel / CSV
│   ├── import_template.php     # Penyedia template file CSV bulk import
│   ├── index.php               # Daftar siswa
│   ├── nilai_delete.php        # Penghapus data nilai akademik
│   ├── nilai_input.php         # Form input dan edit nilai akademik
│   └── print.php               # Biodata cetak A4 siswa
├── spp/
│   ├── create.php              # Form pencatatan bayar SPP & token generator
│   ├── delete.php              # Penghapus riwayat pembayaran
│   ├── edit.php                # Form ubah pembayaran
│   ├── export_excel.php        # Cetak Excel rekapitulasi SPP
│   ├── index.php               # Daftar SPP & tombol WhatsApp share
│   ├── invoice.php             # Halaman invoice online eksternal (public)
│   └── print.php               # Kuitansi bayar SPP A5 terbilang teks rupiah
├── pmb/
│   ├── create.php              # Form pendaftaran offline (manual oleh admin)
│   ├── daftar.php              # Form pendaftaran online (publik)
│   ├── delete.php              # Penghapus data pendaftar & berkas
│   ├── detail.php              # Evaluasi berkas & konversi ke siswa master
│   ├── export_excel.php        # Cetak spreadsheet data pendaftar
│   ├── index.php               # Roster pendaftar & status portal PMB
│   ├── pengaturan.php          # Panel atur status pendaftaran, jadwal, & kuota
│   ├── status.php              # Tracker status seleksi pendaftar (publik)
│   └── view_doc.php            # Viewer berkas pendaftar terproteksi
├── uploads/
│   ├── secure/                 # Folder berkas sensitif terproteksi .htaccess
│   ├── guru/                   # Berkas fisik foto profil guru
│   ├── karyawan/               # Berkas fisik foto profil karyawan
│   └── siswa/                  # Berkas fisik foto profil siswa
├── index.php                   # Halaman Utama (Dashboard & Visual Analytics)
├── install.bat                 # Installer otomatis XAMPP Windows (One-Click)
└── README.md                   # Panduan Instalasi (Dokumen ini)
```

---

## Panduan Instalasi & Setup

Ikuti salah satu metode di bawah ini untuk menginstal dan menjalankan aplikasi di komputer lokal:

### METODE A: Instalasi Otomatis Satu-Klik (Khusus Windows)
Metode ini adalah cara tercepat untuk menginstal aplikasi beserta konfigurasinya jika Anda menggunakan Windows dan XAMPP:

1. **Jalankan Installer**:
   - Cari file **`install.bat`** di dalam root folder project ini.
   - Klik kanan pada **`install.bat`** dan pilih **Run as Administrator** (Jalankan sebagai Administrator).
2. **Proses Otomatis**:
   - Script akan mendeteksi instalasi XAMPP Anda (default di `C:\xampp`).
   - Secara otomatis menyalin seluruh file project ke folder XAMPP `htdocs/master-data-sekolah`.
   - Mengaktifkan layanan **Apache** dan **MySQL** di background (jika belum berjalan).
   - Membuat database **`master_data_sekolah`** dan mengimpor seluruh struktur tabel beserta data seeds awal.
   - Otomatis membuka web browser default ke halaman Login.

---

### METODE B: Instalasi Manual (Windows, macOS, Linux)
Gunakan metode ini jika Anda menggunakan macOS, Linux, atau ingin melakukan instalasi secara manual langkah-demi-langkah:

#### 1. Unduh dan Instal XAMPP
- Unduh XAMPP (direkomendasikan versi PHP 8.0, 8.1, atau 8.2) dari situs resmi [Apache Friends](https://www.apachefriends.org).
- Jalankan installer dan ikuti instruksi penginstalan sampai selesai.

#### 2. Salin Project ke Folder htdocs
- Ekstrak seluruh folder project ini ke dalam direktori `htdocs` XAMPP Anda.
- Ganti nama folder project menjadi **`master-data-sekolah`**.
- Jalur direktori tujuan biasanya berada di:
  - **Windows**: `C:\xampp\htdocs\master-data-sekolah`
  - **macOS**: `/Applications/XAMPP/xamppfiles/htdocs/master-data-sekolah`
  - **Linux**: `/opt/lampp/htdocs/master-data-sekolah`

#### 3. Jalankan Apache & MySQL Server
- Buka **XAMPP Control Panel**.
- Klik tombol **Start** pada modul **Apache** dan **MySQL** hingga lampu indikator berwarna hijau.

#### 4. Konfigurasi & Impor Database
- Buka browser Anda (Chrome/Firefox/Edge) dan akses `http://localhost/phpmyadmin`.
- Buat database baru bernama **`master_data_sekolah`** dengan mengeklik menu **New** di kolom kiri, masukkan nama database, lalu klik **Create**.
- Klik database **`master_data_sekolah`** tersebut, kemudian buka tab **Import** di bagian atas.
- Klik tombol **Choose File** (Pilih Berkas) dan cari file skema database di dalam folder project Anda:
  `database/schema.sql`.
- Scroll ke bawah dan klik tombol **Import** (atau **Go**) untuk memulai impor data. Struktur tabel dan data contoh berhasil dimasukkan.

#### 5. Akses Aplikasi & Halaman Login
- Buka tab baru di browser Anda dan ketik alamat URL:
  `http://localhost/master-data-sekolah`
- Halaman login otomatis akan terbuka.

---

## Akun Login Bawaan (Default Credentials)

Untuk memudahkan proses pengujian awal, gunakan akun berikut:

| Peran (Role) | Username | Password | Keterangan Akses |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin` | `admin` | Full akses, backup database, user management, audit logs. |
| **Operator** | `operator` | `operator` | Mengelola CRUD data siswa, data guru, upload dokumen. |
| **Guru** | `guru` | `guru` | Baca-saja data siswa & guru, upload dokumen profil mandiri. |
| **Kepala Sekolah** | `kepsek` | `kepsek` | Baca-saja data, grafik dashboard, logs aktivitas. |

---

## Integrasi REST API Sederhana

Aplikasi eksternal dapat mengambil atau mengelola data secara mudah melalui HTTP request. Seluruh output API menggunakan format **JSON**.

### API Siswa (`api/siswa.php`)

- **GET `/api/siswa.php`**: Mengambil daftar seluruh siswa.
  - *Parameter opsional*: `search` (pencarian nama/nis/nisn), `kelas_id` (filter ID kelas).
- **GET `/api/siswa.php?id=X`**: Mengambil detail profil satu siswa (ID = X).
- **POST `/api/siswa.php`**: Menambahkan siswa baru.
  - *Format payload*: JSON (atau Standard Form Data).
  - *Field wajib*: `nis`, `nisn`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `agama`, `no_hp`.
- **PUT `/api/siswa.php`** atau **PUT `/api/siswa.php?id=X`**: Memperbarui data siswa (ID = X).
  - *Format payload*: JSON (Kirimkan field yang ingin diperbarui beserta `id`).
- **DELETE `/api/siswa.php?id=X`**: Menghapus data siswa (ID = X) beserta berkas fisik fotonya.

### API Guru (`api/guru.php`)

- **GET `/api/guru.php`**: Mengambil daftar seluruh guru.
  - *Parameter opsional*: `search` (pencarian nama/nip).
- **GET `/api/guru.php?id=X`**: Mengambil detail satu guru.
- **POST `/api/guru.php`**: Menambahkan guru baru.
  - *Field wajib*: `nama`, `mata_pelajaran`, `jabatan`, `pendidikan_terakhir`, `no_hp`, `alamat`.
- **PUT `/api/guru.php`**: Memperbarui data guru.
- **DELETE `/api/guru.php?id=X`**: Menghapus data guru (ID = X) beserta seluruh dokumen fisiknya.

---
*MDS v1.0 - Optimal untuk Kebutuhan Server Sekolah RAM Kecil & Sistem Operasi Lama.*
