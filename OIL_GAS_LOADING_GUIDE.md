# Oil & Gas Companies Loading Guide

## Quick Start: Load All Oil & Gas Companies with Full Details

You can now load **all oil & gas companies at once** with complete details using:

```bash
php artisan companies:load-from-sec --oil-gas-only
```

This command will:
1. ✅ Fetch all companies from SEC
2. ✅ Filter only oil & gas companies (by SIC code)
3. ✅ Load ALL columns with full details:
   - `company_name`
   - `ticker_symbol`
   - `sec_cik_number`
   - `sic_code`
   - `sic_description`
   - `entity_type`
   - `extraction_flag = true` (marked as extracted)

## Usage Options

### Load All Oil & Gas Companies (Recommended)
```bash
# Load all oil & gas companies with full details
php artisan companies:load-from-sec --oil-gas-only
```

### Load Limited Number (for testing)
```bash
# Load first 50 oil & gas companies
php artisan companies:load-from-sec --oil-gas-only --limit=50
```

### Load All Companies (Basic Info Only)
```bash
# Load all companies with only basic info (name, ticker, CIK)
php artisan companies:load-from-sec
```

### Custom Batch Size
```bash
# Process in batches of 200
php artisan companies:load-from-sec --oil-gas-only --batch-size=200
```

## Performance

- **Loading all companies (basic)**: ~6-10 seconds for initial fetch (then cached)
- **Loading oil & gas only**: ~0.5-1 second per company checked
  - Example: To find 100 oil & gas companies, it may check ~1000 companies = ~10-15 minutes
  - This is normal due to SEC API rate limits

## What Gets Loaded

### With `--oil-gas-only` flag:
All columns populated:
- ✅ `company_name`
- ✅ `ticker_symbol`
- ✅ `sec_cik_number`
- ✅ `sic_code` (e.g., "2911")
- ✅ `sic_description` (e.g., "Petroleum Refining")
- ✅ `entity_type` (e.g., "operating")
- ✅ `extraction_flag = true`

### Without flag (basic loading):
Only basic columns:
- ✅ `company_name`
- ✅ `ticker_symbol`
- ✅ `sec_cik_number`
- ⚠️ `sic_code = ''` (empty string)
- ⚠️ `sic_description = ''` (empty string)
- ⚠️ `extraction_flag = false`

## Testing Results

✅ **Successfully tested:**
- Loaded 5 oil & gas companies with full details
- Search endpoint working: `GET /api/companies?search=exxon`
- Company details endpoint working: `GET /api/companies/{id}`
- All companies have complete data (sic_code, sic_description, etc.)

## Example Output

```
Loading OIL & GAS companies only with FULL DETAILS...
This will take longer as it fetches details for each company.

Fetching oil & gas companies from SEC...
Found 5 oil & gas companies (checked 127 total companies)

 5/5 [============================] 100%

✓ Loaded: 5 new oil & gas companies
✓ Updated: 0 existing companies

Oil & Gas companies loaded successfully with full details!
```

## Recommended Approach

**For production use:**

1. **Initial Load**: Load all oil & gas companies with full details
   ```bash
   php artisan companies:load-from-sec --oil-gas-only
   ```
   This may take 1-2 hours depending on total companies.

2. **Daily/Weekly Updates**: Re-run the same command to update existing and add new companies
   ```bash
   php artisan companies:load-from-sec --oil-gas-only
   ```

3. **Frontend**: Users can search and view companies immediately (no API calls needed)

## API Endpoints

After loading, all endpoints work:

1. **Search Companies**: `GET /api/companies?search=exxon`
2. **Get Company Details**: `GET /api/companies/{id}`
   - Returns cached data if already extracted
   - No SEC API call needed!

## Database Query Examples

```sql
-- Count oil & gas companies loaded
SELECT COUNT(*) FROM companies_v1 WHERE extraction_flag = true;

-- View all loaded companies
SELECT company_name, ticker_symbol, sic_code, sic_description 
FROM companies_v1 
WHERE extraction_flag = true 
ORDER BY company_name;
```

