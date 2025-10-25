# Companies API - Quick Reference Card

## 🚀 Endpoint
```
GET /api/companies
```

## 📋 Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `active_only` | boolean | `true` | Show only active companies |
| `search` | string | - | Search name/ticker (case-insensitive) |
| `limit` | integer | `50` | Results per page (max: 100) |
| `offset` | integer | `0` | Skip N results |

## 💻 Quick Commands

```bash
# Start server
php artisan serve

# Test endpoint
curl http://localhost:8000/api/companies

# Search companies
curl "http://localhost:8000/api/companies?search=Energy"

# Pagination
curl "http://localhost:8000/api/companies?limit=10&offset=20"

# Run tests
php artisan test --filter CompanyApiTest

# Clear cache
php artisan cache:clear
```

## 📊 Response Format

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

## 🔑 Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Invalid parameters |
| 500 | Server/database error |

## 📁 Key Files

```
app/Http/Controllers/CompanyController.php  - Main logic
app/Models/Company.php                      - Database model
routes/api.php                              - Route definition
tests/Feature/CompanyApiTest.php            - Tests
```

## 🧪 Using Model Scopes

```php
// In your code
use App\Models\Company;

// Get active companies
$companies = Company::active()->get();

// Search companies
$companies = Company::search('Energy')->get();

// Combine filters
$companies = Company::active()
    ->search('Oil')
    ->orderBy('company_name')
    ->limit(10)
    ->get();
```

## ⚙️ Environment Setup

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_SCHEMA=cait_dev
```

## 🎯 Common Use Cases

### Dropdown/Select List
```javascript
fetch('/api/companies?active_only=true&limit=100')
  .then(r => r.json())
  .then(data => {
    data.data.forEach(company => {
      console.log(`${company.ticker_symbol}: ${company.company_name}`);
    });
  });
```

### Autocomplete Search
```javascript
async function searchCompanies(term) {
  const response = await fetch(`/api/companies?search=${term}&limit=10`);
  return await response.json();
}
```

### Admin Table with Pagination
```javascript
async function loadPage(page = 0, pageSize = 50) {
  const offset = page * pageSize;
  const response = await fetch(
    `/api/companies?active_only=false&limit=${pageSize}&offset=${offset}`
  );
  return await response.json();
}
```

## 🔍 Debugging

```bash
# Check logs
tail -f storage/logs/laravel.log

# Check routes
php artisan route:list

# Test database connection
php artisan tinker
>>> \App\Models\Company::count()
```

## 📚 Documentation

- **Full API Docs**: `API_DOCUMENTATION.md`
- **Implementation Guide**: `COMPANIES_API_README.md`
- **Summary**: `IMPLEMENTATION_SUMMARY.md`
- **Postman Collection**: `postman_collection.json`

---

**Cache TTL**: 5 minutes | **Default Limit**: 50 | **Max Limit**: 100

