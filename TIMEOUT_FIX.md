# Fixing 504 Gateway Timeout for SEC Companies Endpoint

## Problem
The `/api/companies/sec/all?oil_gas_only=true` endpoint is timing out because:
1. It takes time to fetch and process data from SEC API
2. Nginx has a default 60-second timeout
3. PHP-FPM might also have timeout limits

## Solution 1: If Using Docker

The nginx configuration has been updated with longer timeouts. Restart your containers:

```bash
docker-compose restart nginx
# Or if using docker-compose.dev.yml
docker-compose -f docker-compose.dev.yml restart nginx
```

## Solution 2: If Using `php artisan serve` (Local Development)

The built-in PHP server doesn't use nginx, but PHP itself has timeout limits. 

### Option A: Increase PHP timeout in php.ini
```ini
max_execution_time = 600  ; 10 minutes
```

### Option B: Set timeout in the controller
The endpoint now respects longer execution times, but you may need to adjust your local PHP settings.

## Solution 3: Quick Fix - Use Smaller Limits

Instead of requesting 10 companies, try smaller batches:

```bash
# Request 5 companies (faster)
GET /api/companies/sec/all?oil_gas_only=true&limit=5

# Or use pagination
GET /api/companies/sec/all?oil_gas_only=true&limit=5&offset=0
GET /api/companies/sec/all?oil_gas_only=true&limit=5&offset=5
```

## Performance Expectations

- **All companies (no filter)**: ~6 seconds for initial fetch (cached afterwards)
- **Oil & Gas filtering**: 
  - ~0.5-1 second per company checked
  - For 10 companies: May need to check ~100 companies = ~50-100 seconds
  - This is why the default limit is 20 (estimated ~2 minutes)

## Additional Optimizations

The code already includes:
- ✅ Rate limiting protection (120ms delay between SEC API calls)
- ✅ Caching (24 hours for ticker list, 1 hour for company metadata)
- ✅ Early termination when limit is reached
- ✅ Default limit of 20 for oil & gas filtering

## Testing

After restarting nginx (if using Docker), test with:
```bash
curl "http://localhost:8000/api/companies/sec/all?oil_gas_only=true&limit=10"
```

Expected response time: ~60-120 seconds for 10 oil & gas companies.

