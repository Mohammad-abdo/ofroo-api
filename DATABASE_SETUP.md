# Database Setup - MySQL Configuration

## ⚠️ Important: Use MySQL, NOT SQLite

This project is configured to use **MySQL** database. The `database.sqlite` file should be ignored.

## Database Configuration

### 1. Create MySQL Database

```sql
CREATE DATABASE ofroo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Update .env File

Make sure your `.env` file has:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ofroo
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. OR Use SQL Script

```bash
mysql -u root -p ofroo < database/ofroo_database.sql
```

## Remove SQLite File

The `database/database.sqlite` file should be deleted or ignored:

```bash
# Add to .gitignore
echo "database/database.sqlite" >> .gitignore

# Delete the file
rm database/database.sqlite
```

## Verify Database Connection

```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

If you see a PDO object, MySQL is connected correctly.

## Migration Files

All migration files are configured for MySQL:
- Uses `BIGINT` for IDs
- Uses `DECIMAL(10,7)` for coordinates
- Uses `JSON` for complex data
- Uses `ENUM` for status fields
- Proper foreign keys and indexes

## Notes

- The project uses MySQL 8.0+ features
- All tables use `utf8mb4_unicode_ci` collation
- Foreign keys are properly configured
- Indexes are optimized for performance


