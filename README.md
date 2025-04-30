<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://laravel.com/img/logotype.min.svg" width="400"></a></p>

## Sistem Kursus Online PT Tanamin Bumi Nusantara - Backend

Ini adalah repository **backend** dari aplikasi **Sistem Kursus Online** yang dibangun untuk **PT Tanamin Bumi Nusantara** menggunakan framework [Laravel](https://laravel.com). Backend ini menyediakan RESTful API yang akan diakses oleh frontend React (yang berada di repository terpisah).

---

### ⚙️ Requirement

-   PHP >= 8.3
-   Composer
-   Laravel 11
-   MySQL
-   FrankenPHP
-   Docker

---

### 🚀 Instalasi

```bash
# Clone repository
git clone https://github.com/nama-user/nama-repo-backend.git
cd nama-repo-backend

# Install dependencies
composer install

# Salin file .env
cp .env.example .env

# Generate application key
php artisan key:generate

# Konfigurasi koneksi database di file .env
# Lalu jalankan migrasi
php artisan migrate

# menambahkan key `JWT_SECRET` ke file `.env`.
php artisan jwt:secret

# (Opsional) Seed data awal
php artisan db:seed

# Jalankan server lokal
php artisan serve
```

---

### 🐳 Menjalankan dengan Docker dan FrankenPHP

1.  **Pastikan Docker sudah terinstal** di sistem Anda. Jika belum, silakan instal Docker dari [sini](https://www.docker.com/).

2.  **Bangun dan jalankan container Docker:**

    ```bash
    # Bangun image Docker
    docker build -t backend-tanamin .

    # Jalankan container
    docker run -d -p 80:80 --name backend-tanamin backend-tanamin
    ```

3.  **Akses aplikasi Laravel** melalui browser di `http://localhost`.

4.  **Hentikan container** jika diperlukan:

    ```bash
    docker stop backend-tanamin
    ```

5.  **Hapus container** jika tidak lagi digunakan:

    ```bash
    docker rm backend-tanamin
    ```

6.  **Masuk ke dalam container Docker** untuk menjalankan perintah Laravel:

    ```bash
    docker exec -it laravel-franken bash
    ```

    Setelah masuk ke dalam container, Anda dapat menjalankan perintah Laravel seperti biasa, contohnya:

    ```bash
    php artisan migrate
    php artisan db:seed
    ```

---

### 📂 Struktur Direktori

-   `dockerfile`: File konfigurasi Docker untuk membangun image Laravel dengan FrankenPHP.
-   `app/`: Direktori utama aplikasi Laravel.
-   `.env`: File konfigurasi lingkungan aplikasi.

---
