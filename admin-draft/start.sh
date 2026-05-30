#!/bin/bash
PORT="${PORT:-8000}"

# Create upload directories if they don't exist
mkdir -p microfin_web/uploads/business_permits
mkdir -p microfin_web/uploads/client_documents
mkdir -p microfin_web/uploads/hero
mkdir -p microfin_web/uploads/tenant_logos

# Log PHP configuration for debugging
echo "=== PHP Configuration Debug ===" >&2
echo "PHP version: $(php -v | head -n 1)" >&2
echo "Current directory: $(pwd)" >&2
echo "php.ini exists: $(test -f microfin_web/php.ini && echo 'yes' || echo 'no')" >&2
echo "================================" >&2

# Start PHP with custom php.ini using absolute path
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
php -c "${SCRIPT_DIR}/microfin_web/php.ini" -S 0.0.0.0:${PORT} -t .
