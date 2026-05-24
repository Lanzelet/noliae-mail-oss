#!/bin/sh
# Noliae Mail OSS — scheduler interne (PID 1 du container scheduler optionnel).
# Lance `php artisan schedule:run` chaque minute. Plus simple qu'un cron host.
set -e
cd /var/www/html
while true; do
  php artisan schedule:run >>/var/log/scheduler.log 2>&1 || true
  sleep 60
done
