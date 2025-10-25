# Companies API - Laravel Implementation

This document provides implementation details for the Companies API endpoint built with Laravel ORM.

## Overview

The `/api/companies` endpoint returns a list of tracked companies for use in dropdowns, selectors, and admin interfaces. It features:

- ✅ Query parameter validation
- ✅ Fuzzy search by name/ticker
- ✅ Active/inactive filtering
- ✅ Pagination support
- ✅ 5-minute caching
- ✅ Proper error handling
- ✅ Comprehensive test coverage

## Files Created/Modified

### Core Implementation
- `app/Http/Controllers/CompanyController.php` - Main controller with index method
- `app/Models/Company.php` - Eloquent model with scopes for filtering
- `routes/api.php` - API route definitions
- `bootstrap/app.php` - Registered API routes

### Supporting Files
- `database/migrations/2024_01_01_000000_create_companies_table.php` - Database schema
- `database/factories/CompanyFactory.php` - Factory for testing
- `tests/Feature/CompanyApiTest.php` - Comprehensive test suite
- `config/database.php` - Updated PostgreSQL schema configuration

### Documentation
- `API_DOCUMENTATION.md` - Complete API documentation
- `postman_collection.json` - Postman collection for testing
- `COMPANIES_API_README.md` - This file

## Quick Start

### 1. Configure Database

Update your `.env` file with your PostgreSQL connection:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_SCHEMA=cait_dev
```

### 2. Run Migrations (if needed)

If you need to create the companies table:

```bash
php artisan migrate
```

### 3. Start the Server

```bash
php artisan serve
```

### 4. Test the Endpoint

```bash
curl http://localhost:8000/api/companies
```

## Usage Examples

### Basic Request
```bash
curl -X GET "http://localhost:8000/api/companies"
```

Response:
```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 11
  },
  "data": [
    {
      "company_id": 1,
      "company_name": "Diamondback Energy, Inc.",
      "ticker_symbol": "FANG",
      "company_type": "upstream",
      "status": true,
      "created_at": "2025-10-16T14:41:10+00:00"
    }
  ]
}
```

### Search Companies
```bash
curl -X GET "http://localhost:8000/api/companies?search=Energy"
```

### Get All Companies (Including Inactive)
```bash
curl -X GET "http://localhost:8000/api/companies?active_only=false"
```

### Paginated Request
```bash
curl -X GET "http://localhost:8000/api/companies?limit=20&offset=40"
```

## Key Features

### 1. Query Scopes in Model

The `Company` model includes two useful scopes:

```php
// Filter active companies
Company::active()->get();

// Search by name or ticker
Company::search('Energy')->get();
```

### 2. Caching Strategy

Results are cached for 5 minutes with unique cache keys based on parameters:

```php
$cacheKey = sprintf(
    'companies_list_%s_%s_%d_%d',
    $activeOnly ? 'active' : 'all',
    md5($search ?? ''),
    $limit,
    $offset
);

Cache::remember($cacheKey, 300, function() {
    // Query logic
});
```

### 3. Parameter Validation

Built-in validation ensures data integrity:

```php
[
    'active_only' => 'nullable|boolean',
    'search' => 'nullable|string|max:255',
    'limit' => 'nullable|integer|min:1|max:100',
    'offset' => 'nullable|integer|min:0',
]
```

### 4. Error Handling

- **400 Bad Request**: Invalid parameters with detailed error messages
- **500 Internal Server Error**: Database/server errors with logging

## Testing

### Run All Tests
```bash
php artisan test
```

### Run Only Company API Tests
```bash
php artisan test --filter CompanyApiTest
```

### Test Coverage

The test suite includes:
- ✅ Default parameters behavior
- ✅ Active/inactive filtering
- ✅ Search functionality
- ✅ Pagination
- ✅ Parameter validation
- ✅ Result ordering
- ✅ Cache behavior

## Database Schema

```sql
CREATE TABLE companies (
    company_id SERIAL PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    ticker_symbol VARCHAR(10) NOT NULL,
    sec_cik_number VARCHAR(20),
    company_type VARCHAR(20) NOT NULL,
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_companies_status ON companies(status);
CREATE INDEX idx_companies_name ON companies(company_name);
CREATE INDEX idx_companies_ticker ON companies(ticker_symbol);
CREATE INDEX idx_companies_status_name ON companies(status, company_name);
```

## Performance Considerations

1. **Indexes**: The migration includes indexes on commonly queried columns:
   - `status` - for active/inactive filtering
   - `company_name` - for ordering and search
   - `ticker_symbol` - for search
   - Composite index on `(status, company_name)` - for common query pattern

2. **Query Optimization**:
   - Uses `select()` to only fetch needed columns
   - Limits results to max 100 per request
   - Single query with count for total

3. **Caching**:
   - 5-minute cache reduces database load
   - Separate cache entries per parameter combination
   - Cache keys use MD5 hash for search terms

## API Client Examples

### JavaScript/TypeScript
```javascript
const getCompanies = async (params = {}) => {
  const queryString = new URLSearchParams(params).toString();
  const response = await fetch(`/api/companies?${queryString}`);
  return await response.json();
};

// Usage
const companies = await getCompanies({ 
  active_only: true, 
  search: 'Energy',
  limit: 20 
});
```

### PHP
```php
use Illuminate\Support\Facades\Http;

$response = Http::get('http://localhost:8000/api/companies', [
    'active_only' => true,
    'search' => 'Energy',
    'limit' => 20,
]);

$data = $response->json();
```

### Python
```python
import requests

response = requests.get('http://localhost:8000/api/companies', params={
    'active_only': True,
    'search': 'Energy',
    'limit': 20
})

data = response.json()
```

## Troubleshooting

### Issue: API returns 404
**Solution**: Make sure API routes are registered in `bootstrap/app.php`:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',  // <- This line
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### Issue: ILIKE not working
**Solution**: This implementation uses PostgreSQL's ILIKE for case-insensitive search. For MySQL, change to:
```php
$q->where('company_name', 'LIKE', '%' . $search . '%')
  ->orWhere('ticker_symbol', 'LIKE', '%' . $search . '%');
```

### Issue: Schema not found
**Solution**: Ensure your `.env` has the correct schema:
```env
DB_SCHEMA=cait_dev
```

And that `config/database.php` uses it:
```php
'search_path' => env('DB_SCHEMA', 'cait_dev'),
```

## Next Steps

Consider these enhancements:

1. **API Resources**: Use Laravel API Resources for cleaner response formatting
2. **Rate Limiting**: Add throttling to prevent abuse
3. **API Versioning**: Implement `/api/v1/companies` structure
4. **Authentication**: Add API token authentication if needed
5. **OpenAPI/Swagger**: Generate OpenAPI specification
6. **Monitoring**: Add metrics collection (response times, cache hit rates)

## Support

For questions or issues:
1. Check the `API_DOCUMENTATION.md` for detailed API specs
2. Review test cases in `tests/Feature/CompanyApiTest.php`
3. Check Laravel logs at `storage/logs/laravel.log`

