# OFROO Deployment Guide

## Quick Fix for Images Not Loading

If images are not showing on production, run these commands on your server:

```bash
# 1. Create storage link (IMPORTANT!)
php artisan storage:link

# 2. Set correct permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# 3. Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. Or use the setup command
php artisan setup:production
```

## Alternative: Manual Storage Link

If `php artisan storage:link` doesn't work on your hosting:

### Method 1: Create symbolic link manually via FTP/cPanel
```
Navigate to: /public_html/ (or your public folder)
Create a symlink:
- Link: storage
- Target: ../storage/app/public
```

### Method 2: Use PHP script
Create `link_storage.php` in public folder:
```php
<?php
$target = realpath(__DIR__ . '/../storage/app/public');
$link = __DIR__ . '/storage';
if (!file_exists($link)) {
    symlink($target, $link);
    echo "Storage linked!";
} else {
    echo "Already linked";
}
```
Then visit: `https://yourdomain.com/link_storage.php`

### Method 3: Direct access via route
Images will work if you update your `.env`:
```
FILESYSTEM_DISK=public
```

## Production Checklist

1. **Update .env**:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
FILESYSTEM_DISK=public
```

2. **Generate App Key**:
```bash
php artisan key:generate
```

3. **Create Storage Link**:
```bash
php artisan storage:link
```

4. **Set Permissions**:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage
```

5. **Optimize**:
```bash
php artisan config:cache
php artisan route:cache
php artisan optimize
```

## Verify Storage Works

Check if storage link exists:
```bash
ls -la public/storage
```

Should show something like:
```
storage -> /path/to/storage/app/public
```

## If Images Still Don't Work

Check your `StorageHelper.php` - it generates URLs like:
```
https://your-domain.com/storage/offers/image.jpg
```

Make sure:
1. `APP_URL` is set correctly in `.env`
2. Storage link is created
3. Files exist in `storage/app/public/`
