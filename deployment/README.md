# Lightsail deployment

Production uses atomic releases on the Lightsail origin. The systemd timer checks
the GitHub `main` branch every minute and deploys a new commit when available.

Runtime state is kept outside releases:

- `/var/www/ai-airbagszentrum-shared/.env`
- `/var/www/ai-airbagszentrum-shared/storage`
- `/var/www/ai-airbagszentrum-shared/public/images`
- `/var/www/ai-airbagszentrum-shared/public/exports`

The active release is `/var/www/ai-airbagszentrum-current`. Five releases are
retained for recovery. Deployment runs Composer, database migrations, Laravel
cache rebuilding, and reloads PHP-FPM before returning the application online.
