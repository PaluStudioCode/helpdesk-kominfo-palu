#!/bin/bash
set -e

echo "=== [1/6] Sinkronisasi Git dari GitHub ==="
cd /var/www/helpdesk-kominfo-palu

# Simpan perubahan konfigurasi khusus server
git stash push -m "server-local-config" app/Http/Controllers/TicketActionController.php config/broadcasting.php resources/js/bootstrap.js 2>/dev/null || true
git fetch origin main
git reset --hard origin/main
git stash pop 2>/dev/null || true

echo "=== [2/6] Membangun Frontend Aset (Vite) ==="
NODE_OPTIONS="--max-old-space-size=1536" npm run build

echo "=== [3/6] Menjalankan Database Fresh & Seeding ==="
php artisan migrate:fresh --seed --force

echo "=== [4/6] Memperbarui Cache Konfigurasi & Route ==="
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== [5/6] Me-restart Queue Worker & Reverb ==="
php artisan queue:restart
systemctl restart reverb.service

echo "=== [6/6] Me-reload PHP-FPM, Nginx & Mengatur Permission ==="
systemctl reload php8.3-fpm
systemctl reload nginx
chown -R www-data:www-data public/build storage bootstrap/cache
chmod -R 775 public/build storage bootstrap/cache

echo "=== Deploy Berhasil Diselesaikan! ==="
