# Company Loading Strategy

## Overview

The system now supports a two-phase approach for loading company data:

1. **Phase 1: Bulk Load (Minimal Info)** - Load ALL companies from SEC with basic information
2. **Phase 2: On-Demand Extraction** - Fetch full details when user requests a specific company

## Database Schema

The `companies_v1` table has been updated to make detail fields nullable:

- `sic_code` - **NULL** until extracted
- `sic_description` - **NULL** until extracted  
- `entity_type` - **NULL** until extracted
- `extraction_flag` - Always `false` by default (not used for extraction status)

**Status Indicator**: A company has been extracted if `sic_code` is NOT NULL.

## Loading All Companies (Minimal Info)

### Command
```bash
php artisan companies:load-from-sec
```

### What It Does
- Fetches ALL companies from SEC API
- Loads only basic information:
  - `company_name`
  - `ticker_symbol`
  - `sec_cik_number`
- `sic_code`, `sic_description`, `entity_type` remain **NULL**
- Fast operation - no metadata fetching

### Example
```bash
# Load all companies (no limit)
php artisan companies:load-from-sec

# Load first 100 companies (for testing)
php artisan companies:load-from-sec --limit=100
```

## Loading Oil & Gas Companies (Full Details)

### Command
```bash
php artisan companies:load-from-sec --oil-gas-only
```

### What It Does
- Fetches ALL companies from SEC
- Filters to find only oil & gas companies (by SIC code)
- Loads with FULL details:
  - All basic info
  - `sic_code`
  - `sic_description`
  - `entity_type`
- Skips companies already in database (with `sic_code` populated)

### Example
```bash
# Load all oil & gas companies
php artisan companies:load-from-sec --oil-gas-only

# Load first 5 oil & gas companies (for testing)
php artisan companies:load-from-sec --oil-gas-only --limit=5
```

## On-Demand Extraction (User Request)

### API Endpoint
```
GET /api/companies/{id}
```

### What It Does
1. Checks if company exists in database
2. If `sic_code` is NULL:
   - Fetches full details from SEC API
   - Checks if it's an oil & gas company
   - If YES: Updates company with full details
   - If NO: Returns alert message (doesn't save details)
3. If `sic_code` is NOT NULL:
   - Returns cached data from database

### Flow
```
User searches → Finds company → Clicks "Request Details"
  ↓
API: GET /api/companies/{id}
  ↓
Check if sic_code is NULL
  ├─ YES → Fetch from SEC → Check oil & gas → Update DB → Return
  └─ NO → Return from DB (cached)
```

## API Response Format

### Search Endpoint (Minimal Info)
```json
{
  "meta": {
    "limit": 20,
    "offset": 0,
    "total": 10142
  },
  "data": [
    {
      "company_id": 1,
      "company_name": "EXXON MOBIL CORP",
      "ticker_symbol": "XOM",
      "sec_cik_number": "0000034088",
      "sic_description": null,
      "has_details": false  // sic_code is NULL
    }
  ]
}
```

### Details Endpoint (Full Info)
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
    "extraction_flag": false
  },
  "cached": false
}
```

## Benefits

1. **Fast Initial Load**: Load all companies quickly with minimal info
2. **On-Demand Details**: Fetch full details only when needed
3. **Efficient Storage**: Don't store unnecessary data upfront
4. **User-Driven**: Details are fetched based on user interest
5. **Oil & Gas Filtering**: Still supports bulk loading of oil & gas companies

## Migration

The migration `make_sic_code_fields_nullable_in_companies_v1_table` has been applied to:
- Make `sic_code` nullable
- Make `sic_description` nullable
- Make `entity_type` nullable

This allows companies to exist with only basic information.

