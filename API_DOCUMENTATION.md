# Companies API Documentation

## GET /api/companies

Returns a list of tracked companies for use in dropdowns, selectors, and admin interfaces.

### Endpoint

```
GET /api/companies
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `active_only` | boolean | No | `true` | Filter to show only active companies (status=true) |
| `search` | string | No | `null` | Fuzzy search by company name or ticker symbol (case-insensitive) |
| `limit` | integer | No | `50` | Number of results to return (min: 1, max: 100) |
| `offset` | integer | No | `0` | Number of results to skip for pagination (min: 0) |

### Request Examples

#### Basic request (returns active companies only)
```bash
GET /api/companies
```

#### Get all companies (including inactive)
```bash
GET /api/companies?active_only=false
```

#### Search for companies
```bash
GET /api/companies?search=Energy
GET /api/companies?search=FANG
```

#### Pagination
```bash
GET /api/companies?limit=20&offset=40
```

#### Combined filters
```bash
GET /api/companies?active_only=true&search=Oil&limit=10&offset=0
```

### Response Format

#### Success Response (200 OK)

```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 12
  },
  "data": [
    {
      "company_id": 1,
      "company_name": "Diamondback Energy, Inc.",
      "ticker_symbol": "FANG",
      "company_type": "upstream",
      "status": true,
      "created_at": "2020-03-01T00:00:00+00:00"
    },
    {
      "company_id": 2,
      "company_name": "Permian Resources Corporation",
      "ticker_symbol": "PR",
      "company_type": "upstream",
      "status": true,
      "created_at": "2021-05-15T00:00:00+00:00"
    }
  ]
}
```

#### Error Response (400 Bad Request)

Invalid parameters:

```json
{
  "error": "Invalid parameters",
  "messages": {
    "limit": [
      "The limit must be at least 1."
    ]
  }
}
```

#### Error Response (500 Internal Server Error)

Database or server error:

```json
{
  "error": "Internal server error"
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `meta.limit` | integer | Number of items requested per page |
| `meta.offset` | integer | Number of items skipped |
| `meta.total` | integer | Total number of companies matching the filters |
| `data[].company_id` | integer | Unique identifier for the company |
| `data[].company_name` | string | Full legal name of the company |
| `data[].ticker_symbol` | string | Stock ticker symbol |
| `data[].company_type` | string | Type of company (upstream, integrated, midstream, downstream) |
| `data[].status` | boolean | Whether the company is active |
| `data[].created_at` | string | ISO 8601 timestamp of when the record was created |

### Caching

- Results are cached for **5 minutes (300 seconds)**
- Cache key is based on query parameters
- This is suitable since company lists rarely change
- Cache is automatically invalidated after 5 minutes

### Ordering

Results are always ordered by `company_name` in ascending (A-Z) order.

### Performance Notes

1. **Database Query**: Uses indexed columns for optimal performance
   - `status` column is indexed for active/inactive filtering
   - `company_name` and `ticker_symbol` should be indexed for search performance

2. **Caching Strategy**: 
   - Short-term cache (5 minutes) reduces database load
   - Each unique combination of parameters has its own cache entry

3. **Limit**: 
   - Maximum limit is capped at 100 to prevent performance issues
   - Default limit of 50 provides good balance

### Testing

Run the test suite:

```bash
php artisan test --filter CompanyApiTest
```

### Example Usage in Frontend

#### JavaScript (Fetch API)

```javascript
// Get active companies for dropdown
async function getCompanies() {
  const response = await fetch('/api/companies?limit=100');
  const result = await response.json();
  return result.data;
}

// Search companies
async function searchCompanies(searchTerm) {
  const response = await fetch(`/api/companies?search=${encodeURIComponent(searchTerm)}`);
  const result = await response.json();
  return result.data;
}

// Paginated request
async function getCompaniesPaginated(page = 0, pageSize = 50) {
  const offset = page * pageSize;
  const response = await fetch(`/api/companies?limit=${pageSize}&offset=${offset}`);
  return await response.json();
}
```

#### cURL

```bash
# Basic request
curl -X GET "http://localhost:8000/api/companies"

# With search
curl -X GET "http://localhost:8000/api/companies?search=Energy"

# With all parameters
curl -X GET "http://localhost:8000/api/companies?active_only=true&search=Oil&limit=20&offset=0"
```

### Database Schema Reference

The endpoint queries the `companies` table in the `cait_dev` schema:

```sql
SELECT 
  company_id,
  company_name,
  ticker_symbol,
  company_type,
  status,
  created_at
FROM cait_dev.companies
WHERE (status = ? OR ? IS FALSE)
  AND (? IS NULL OR company_name ILIKE ? OR ticker_symbol ILIKE ?)
ORDER BY company_name ASC
LIMIT ? OFFSET ?;
```

### Configuration

To use a different database schema, update your `.env` file:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
DB_SCHEMA=cait_dev
```

