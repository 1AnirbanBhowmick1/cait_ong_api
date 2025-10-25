# Companies API - Deployment Checklist

Use this checklist to ensure the API is properly deployed and configured.

## Pre-Deployment

### ✅ Environment Configuration
- [ ] `.env` file is configured with correct database credentials
- [ ] `DB_CONNECTION` is set to `pgsql`
- [ ] `DB_SCHEMA` is set to `cait_dev` (or your schema name)
- [ ] Database credentials are correct (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- [ ] `APP_ENV` is set appropriately (`local` for dev, `production` for prod)
- [ ] `APP_DEBUG` is `false` for production
- [ ] `APP_KEY` is generated (run `php artisan key:generate` if needed)

### ✅ Database Setup
- [ ] Database exists and is accessible
- [ ] `companies` table exists in the `cait_dev` schema
- [ ] Table has required columns:
  - `company_id` (primary key)
  - `company_name`
  - `ticker_symbol`
  - `sec_cik_number`
  - `company_type`
  - `status`
  - `created_at`
  - `updated_at`
- [ ] Performance indexes are created:
  ```sql
  CREATE INDEX IF NOT EXISTS idx_companies_status ON companies(status);
  CREATE INDEX IF NOT EXISTS idx_companies_name ON companies(company_name);
  CREATE INDEX IF NOT EXISTS idx_companies_ticker ON companies(ticker_symbol);
  CREATE INDEX IF NOT EXISTS idx_companies_status_name ON companies(status, company_name);
  ```

### ✅ Laravel Configuration
- [ ] Composer dependencies installed (`composer install --no-dev --optimize-autoloader` for production)
- [ ] Config cache cleared (`php artisan config:clear`)
- [ ] Route cache built (`php artisan route:cache` for production)
- [ ] View cache cleared (`php artisan view:clear`)
- [ ] Application cache cleared (`php artisan cache:clear`)

### ✅ File Permissions (Linux/Unix)
- [ ] Storage directory is writable (`chmod -R 775 storage`)
- [ ] Bootstrap/cache is writable (`chmod -R 775 bootstrap/cache`)
- [ ] Log directory exists and is writable

## Testing

### ✅ Local Testing
- [ ] Server starts successfully (`php artisan serve`)
- [ ] Route is registered (check `php artisan route:list | grep companies`)
- [ ] Endpoint responds at `http://localhost:8000/api/companies`
- [ ] Basic request returns data:
  ```bash
  curl http://localhost:8000/api/companies
  ```
- [ ] Search works:
  ```bash
  curl "http://localhost:8000/api/companies?search=Energy"
  ```
- [ ] Pagination works:
  ```bash
  curl "http://localhost:8000/api/companies?limit=5&offset=0"
  ```
- [ ] Invalid parameters return 400:
  ```bash
  curl "http://localhost:8000/api/companies?limit=-5"
  ```

### ✅ Unit Tests
- [ ] All tests pass (`php artisan test --filter CompanyApiTest`)
- [ ] Test output shows no errors or warnings

### ✅ Performance Testing
- [ ] First request completes within acceptable time
- [ ] Cached requests are faster than first request
- [ ] Cache is working (verify with logs or monitoring)
- [ ] Large result sets (100 items) return within acceptable time
- [ ] Concurrent requests are handled correctly

## Post-Deployment

### ✅ Verification
- [ ] API is accessible at production URL
- [ ] HTTPS is working (if applicable)
- [ ] CORS headers are configured (if needed for frontend)
- [ ] Response times are acceptable (< 500ms for cached, < 2s for uncached)
- [ ] Error handling works correctly (test with invalid DB credentials temporarily)

### ✅ Monitoring Setup
- [ ] Application logs are being written to `storage/logs/laravel.log`
- [ ] Log rotation is configured
- [ ] Error monitoring is set up (Sentry, Bugsnag, etc.)
- [ ] Performance monitoring is configured (New Relic, DataDog, etc.)
- [ ] API endpoint is added to health checks/uptime monitoring

### ✅ Documentation
- [ ] API documentation is accessible to team
- [ ] Postman collection is shared with team
- [ ] Quick reference guide is available
- [ ] Database schema is documented

## Production Optimizations

### ✅ Caching
- [ ] Consider using Redis for cache driver:
  ```env
  CACHE_DRIVER=redis
  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=null
  REDIS_PORT=6379
  ```
- [ ] Redis is installed and running (if using Redis)
- [ ] Cache TTL is appropriate for your use case (currently 5 minutes)

### ✅ Performance
- [ ] OPcache is enabled (PHP)
- [ ] Config cache is built (`php artisan config:cache`)
- [ ] Route cache is built (`php artisan route:cache`)
- [ ] Database connection pooling is configured
- [ ] Query logging is disabled in production

### ✅ Security
- [ ] API rate limiting is configured (if needed):
  ```php
  Route::middleware(['throttle:60,1'])->get('/companies', ...);
  ```
- [ ] SQL injection protection verified (uses parameter binding)
- [ ] Input validation is working (test with malicious input)
- [ ] Error messages don't leak sensitive information
- [ ] HTTPS is enforced (if applicable)

## Rollback Plan

### If Issues Occur
1. **Check logs**: `tail -f storage/logs/laravel.log`
2. **Verify database connection**: 
   ```bash
   php artisan tinker
   >>> \App\Models\Company::count()
   ```
3. **Clear all caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```
4. **Restart PHP-FPM/Web Server** (if using production server)
5. **Rollback code** (if necessary)

## Common Issues & Solutions

### Issue: 404 Not Found
**Solution**: 
- Check `php artisan route:list` to verify route is registered
- Ensure `bootstrap/app.php` includes `api: __DIR__.'/../routes/api.php'`
- Clear route cache: `php artisan route:clear`

### Issue: 500 Internal Server Error
**Solution**:
- Check `storage/logs/laravel.log` for details
- Verify database connection
- Check file permissions on storage directory
- Ensure APP_KEY is set

### Issue: Empty Results
**Solution**:
- Verify `companies` table has data
- Check if `status` column has `true` values (default filter is active_only=true)
- Try with `?active_only=false` parameter
- Check database schema name matches configuration

### Issue: Slow Performance
**Solution**:
- Verify indexes exist on database
- Check if caching is working
- Enable query logging temporarily to identify slow queries
- Consider upgrading cache driver to Redis

### Issue: ILIKE Not Working (MySQL)
**Solution**:
If using MySQL instead of PostgreSQL, update the search scope in `Company.php`:
```php
public function scopeSearch($query, $search)
{
    if (empty($search)) {
        return $query;
    }
    
    return $query->where(function ($q) use ($search) {
        $q->where('company_name', 'LIKE', '%' . $search . '%')
          ->orWhere('ticker_symbol', 'LIKE', '%' . $search . '%');
    });
}
```

## Sign-Off

- [ ] Developer tested locally
- [ ] QA tested in staging
- [ ] Performance metrics reviewed
- [ ] Stakeholders notified
- [ ] Documentation updated
- [ ] Team trained on new endpoint

---

**Deployment Date**: _____________  
**Deployed By**: _____________  
**Version**: 1.0.0  
**Status**: ⬜ Pending | ⬜ In Progress | ⬜ Complete

