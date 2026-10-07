# Laporan Sapras Sekolah

Sistem pelaporan kerusakan sarana dan prasarana sekolah berbasis PHP Native, MySQL, dan Bootstrap 5.

## Persiapan

Gunakan PHP dengan ekstensi `mysqli` dan `fileinfo`, serta MySQL/MariaDB. Pilih database untuk aplikasi, lalu atur konfigurasi melalui environment variable:

```sh
export DB_HOST=localhost
export DB_USER=dev
export DB_PASSWORD='isi-password-database-di-environment'
export DB_NAME=tbsiswa
```

Jangan simpan password database ke source code atau repository. Terapkan SQL di `database/migration/2025_01_create_table_tbsiswa.sql` pada database aplikasi. Migration mempertahankan data/tabel siswa yang telah ada dan menambahkan tabel akun serta kolom laporan yang dibutuhkan.

## Menjalankan

Jalankan dari root project:

```sh
php -S 127.0.0.1:8080
```

Buka `http://127.0.0.1:8080`. Pada instalasi tanpa akun, buat admin pertama dari localhost. Admin dapat membuat akun siswa, guru, petugas Sapras, dan admin lain. Siswa/guru membuat laporan; petugas/admin menangani status dan catatan tindak lanjut.

Foto laporan berupa JPG/PNG maksimal 2 MB dan disimpan sebagai BLOB di database.
