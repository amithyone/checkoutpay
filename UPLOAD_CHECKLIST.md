# File Upload Checklist for Live Server

## 📁 Required Files and Folders

Upload ALL of these to your server:

### Core Laravel Files
- ✅ `app/` - Entire folder
- ✅ `bootstrap/` - Entire folder
- ✅ `config/` - Entire folder
- ✅ `database/` - Entire folder
- ✅ `public/` - Entire folder
- ✅ `resources/` - Entire folder
- ✅ `routes/` - Entire folder
- ✅ `storage/` - Entire folder
- ✅ `vendor/` - Entire folder (or install via composer)
- ✅ `artisan` - File
- ✅ `composer.json` - File
- ✅ `composer.lock` - File (if exists)

### Configuration Files
- ✅ `.env` - Create on server (don't upload from local)
- ✅ `.env.example` - Upload for reference
- ✅ `.htaccess` - Upload (both root and public)

### Optional but Recommended
- ✅ `README.md`
- ✅ Documentation files (*.md)

## ❌ DO NOT Upload

- `node_modules/` - Not needed for Laravel
- `.git/` - Git repository (unless using Git deployment)
- `.env` from local - Create new one on server
- `storage/logs/*.log` - Log files
- Temporary files

## 📋 Upload Order

1. **Upload all folders** (app, bootstrap, config, etc.)
2. **Upload vendor folder** OR run `composer install` on server
3. **Create `.env` file** on server with production values
4. **Set permissions** on storage and bootstrap/cache
5. **Run migrations** and seeders

## 🔍 Verify Upload

After uploading, check these paths exist on server:

```
/home/checzspw/public_html/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/
│   ├── index.php ✅
│   └── .htaccess ✅
├── resources/
├── routes/
├── storage/
├── vendor/ ✅ (MUST EXIST!)
├── artisan ✅
├── composer.json ✅
└── .env ✅
```

## 🚨 Common Issues

### Issue: "vendor folder missing"
**Solution:** Upload vendor folder or run `composer install` on server

### Issue: "storage permissions"
**Solution:** 
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Issue: ".env not found"
**Solution:** Create `.env` file on server from `.env.example`

### Issue: "Database connection failed"
**Solution:** Update database credentials in `.env` file
