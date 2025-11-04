# Company Loading Approach - Implementation Guide

## Overview

This document describes the new approach for loading and managing companies from SEC:

1. **Bulk Load**: Load all companies from SEC into `companies_v1` table with only basic info (company_name, ticker_symbol, sec_cik_number)
2. **Search**: Frontend searches the local database (fast, no API calls)
3. **Details on Click**: When user clicks a company, fetch details from SEC, check if oil & gas, and save to DB

## Step 1: Load Companies into Database

Run the artisan command to load all companies from SEC:

```bash
# Load all companies (this may take a while)
php artisan companies:load-from-sec

# Load with batch size (default: 100)
php artisan companies:load-from-sec --batch-size=200

# Load limited number for testing
php artisan companies:load-from-sec --limit=1000
```

**What it does:**
- Fetches all companies from SEC tickers JSON
- Inserts only: `company_name`, `ticker_symbol`, `sec_cik_number`
- Sets `extraction_flag = false` (details not extracted yet)
- Skips duplicates (updates existing records)

## Step 2: Frontend Search (Fast - Database Query)

The frontend can now search companies from the local database:

```bash
# Search by company name or ticker
GET /api/companies?search=exxon

# With pagination
GET /api/companies?search=oil&limit=20&offset=0
```

**Response:**
```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 150
  },
  "data": [
    {
      "company_id": 1,
      "company_name": "EXXON MOBIL CORP",
      "ticker_symbol": "XOM",
      "sec_cik_number": "0000034088",
      "has_details": false
    }
  ]
}
```

**Note:** `has_details: false` means details haven't been extracted yet.

## Step 3: Get Company Details (On Click)

When user clicks on a company, fetch details:

```bash
GET /api/companies/{id}
```

**Response if NOT oil & gas company:**
```json
{
  "success": false,
  "is_oil_gas_company": false,
  "message": "This company is not an oil & gas company",
  "company_name": "APPLE INC",
  "ticker_symbol": "AAPL"
}
```

**Response if IS oil & gas company:**
```json
{
  "success": true,
  "is_oil_gas_company": true,
  "data": {
    "company_id": 1,
    "company_name": "EXXON MOBIL CORP",
    "ticker_symbol": "XOM",
    "sec_cik_number": "0000034088",
    "sic_code": "2911",
    "sic_description": "Petroleum Refining",
    "entity_type": "operating",
    "is_oil_gas_company": true,
    "extraction_flag": true
  }
}
```

**What happens:**
1. Checks if company details already extracted (from DB)
2. If not, fetches from SEC API
3. Checks if it's an oil & gas company (by SIC code)
4. If NOT oil & gas: Returns alert message (doesn't save)
5. If IS oil & gas: Saves details to DB and returns data

## Database Schema

The `companies_v1` table structure:

```sql
- company_id (primary key)
- company_name (varchar 255)
- ticker_symbol (varchar 10)
- sec_cik_number (varchar 20)
- sic_code (varchar 20) - NULL until extracted
- sic_description (text) - NULL until extracted
- entity_type (varchar 100) - NULL until extracted
- extraction_flag (boolean) - false until details extracted
- admin_approval_flag (varchar 100)
- created_at
- updated_at
```

## Oil & Gas Detection

Companies are identified as oil & gas based on SIC codes:
- `1311` - Crude Petroleum and Natural Gas
- `1381` - Drilling Oil and Gas Wells
- `1382` - Oil and Gas Field Exploration Services
- `1389` - Oil and Gas Field Services
- `2911` - Petroleum Refining
- `4612` - Crude Petroleum Pipelines
- `4613` - Refined Petroleum Pipelines
- `4922-4925` - Natural Gas Transmission/Distribution

## API Endpoints Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/companies` | GET | Search companies in database (with `?search=...`) |
| `/api/companies/{id}` | GET | Get detailed company info (extracts from SEC if needed) |
| `/api/companies/lookup/{ticker}` | GET | Direct SEC lookup by ticker (legacy) |
| `/api/companies/sec/all` | GET | Get all companies from SEC API (for bulk loading) |

## Frontend Workflow

1. **Initial Load**: User searches for companies
   ```
   GET /api/companies?search=exxon
   ```

2. **Display Results**: Show list of companies with basic info

3. **User Clicks Company**: 
   ```
   GET /api/companies/123
   ```

4. **Handle Response**:
   - If `success: false` and `is_oil_gas_company: false` → Show alert
   - If `success: true` and `is_oil_gas_company: true` → Show details

## Performance Benefits

- **Fast Search**: Database queries are fast (no SEC API calls)
- **Lazy Loading**: Details only fetched when needed
- **Caching**: Once extracted, details are cached in DB
- **Rate Limit Friendly**: Only calls SEC API when user clicks

## Maintenance

### Update Company List

To refresh the company list from SEC:

```bash
php artisan companies:load-from-sec
```

This will:
- Update existing companies with latest names/tickers
- Add new companies
- Skip duplicates

### Check Extraction Status

```sql
-- Companies with details extracted
SELECT COUNT(*) FROM companies_v1 WHERE extraction_flag = true;

-- Companies without details
SELECT COUNT(*) FROM companies_v1 WHERE extraction_flag = false;
```

## Troubleshooting

### No companies in database
Run the load command:
```bash
php artisan companies:load-from-sec
```

### Search returns empty
Check if companies are loaded:
```sql
SELECT COUNT(*) FROM companies_v1;
```

### Company details not saving
Check if company is oil & gas. Non-oil & gas companies won't be saved (by design).

