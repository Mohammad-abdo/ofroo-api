# Environment Setup Guide

## Required .env Configuration

Copy `.env.example` to `.env` and configure the following:

```env
APP_NAME=OFROO
APP_ENV=local
APP_KEY=base64:... (run: php artisan key:generate)
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Kuwait

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ofroo
DB_USERNAME=root
DB_PASSWORD=

# Mail Configuration (SMTP/SendGrid)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ofroo.com
MAIL_FROM_NAME="${APP_NAME}"

# Google Maps API
GOOGLE_MAPS_API_KEY=your_google_maps_api_key

# Barcode Settings
BARCODE_TYPE=code128
BARCODE_FORMAT=png

# Queue Configuration
QUEUE_CONNECTION=database
# or use Redis:
# QUEUE_CONNECTION=redis
# REDIS_HOST=127.0.0.1
# REDIS_PASSWORD=null
# REDIS_PORT=6379

# Firebase Cloud Messaging (FCM)
FCM_SERVER_KEY=your_fcm_server_key
FCM_SENDER_ID=your_fcm_sender_id

# Session & Cache
SESSION_DRIVER=database
CACHE_DRIVER=file
# or use Redis:
# CACHE_DRIVER=redis
# SESSION_DRIVER=redis

# File Storage
FILESYSTEM_DISK=local
# or use S3:
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=
# AWS_USE_PATH_STYLE_ENDPOINT=false

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:3000
SESSION_DOMAIN=localhost
```

## API Keys Setup

### 1. Google Maps API Key
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable Maps JavaScript API and Places API
4. Create credentials (API Key)
5. Add to `.env` as `GOOGLE_MAPS_API_KEY`

### 2. SendGrid API Key
1. Sign up at [SendGrid](https://sendgrid.com/)
2. Create API Key with Mail Send permissions
3. Add to `.env` as `MAIL_PASSWORD`

### 3. Firebase Cloud Messaging
1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create a new project
3. Add Android/iOS app
4. Get Server Key from Cloud Messaging settings
5. Add to `.env` as `FCM_SERVER_KEY` and `FCM_SENDER_ID`

## Initial Setup Commands

```bash
# Install dependencies
composer install
npm install

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Or use SQL script
mysql -u root -p ofroo < database/ofroo_database.sql

# Create storage link
php artisan storage:link

# Publish vendor assets
php artisan vendor:publish --tag=laravel-assets

# Start development server
php artisan serve

# Start queue worker (in separate terminal)
php artisan queue:work

# Start scheduler (in separate terminal)
php artisan schedule:work
```

## Default Credentials (from seeders)

### Admin
- Email: admin@ofroo.com
- Password: password

### Merchant
- Email: merchant1@example.com
- Password: password

### User
- Email: ahmed@example.com
- Password: password

