#!/bin/bash
set -e

echo "Deploying EbookReader to ebook.runready.cloud..."

# Sync code files (never touch storage on the server)
rsync -avz \
    --exclude="tests" \
    --exclude=".git" \
    --exclude="storage" \
    --exclude=".env" \
    ./ root@runready.cloud:/var/www/ebook.runready.cloud/

# Ensure webserver permissions on server
ssh root@runready.cloud "chown -R www-data:www-data /var/www/ebook.runready.cloud/storage && chmod -R 775 /var/www/ebook.runready.cloud/storage"

echo "Deployment finished successfully!"
