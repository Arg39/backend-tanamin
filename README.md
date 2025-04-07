<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://laravel.com/img/logotype.min.svg" width="400"></a></p>

## Sistem Kursus Online PT Tanamin Bumi Nusantara - Backend

Ini adalah repository **backend** dari aplikasi **Sistem Kursus Online** yang dibangun untuk **PT Tanamin Bumi Nusantara** menggunakan framework [Laravel](https://laravel.com). Backend ini menyediakan RESTful API yang akan diakses oleh frontend React (yang berada di repository terpisah).

---

### ⚙️ Requirement

- PHP >= 8.3
- Composer
- Laravel 11
- MySQL
- FrankenPHP

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

# (Opsional) Seed data awal
php artisan db:seed

# Jalankan server lokal
php artisan serve
# backend-tanamin
# backend-tanamin
