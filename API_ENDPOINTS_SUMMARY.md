# API Endpoints Summary

## Companies Endpoints

### 1. Search Companies
**Endpoint:** `GET /api/companies`

**Description:** Search companies from local database by company name or ticker symbol.

**Query Parameters:**
- `search` (optional): Search term for company name or ticker
- `limit` (optional): Number of results per page (default: 50, max: 100)
- `offset` (optional): Pagination offset (default: 0)

**Example:**
```bash
GET /api/companies?search=exxon&limit=20&offset=0
```

**Response:**
```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 1
  },
  "data": [
    {
      "company_id": 13,
      "company_name": "EXXON MOBIL CORP",
      "ticker_symbol": "XOM",
      "sec_cik_number": "0000034088",
      "has_details": true
    }
  ]
}
```

---

### 2. Get Company Details
**Endpoint:** `GET /api/companies/{id}`

**Description:** Get detailed company information. If not extracted, fetches from SEC API and checks if oil & gas.

**Parameters:**
- `id` (path): Company ID

**Example:**
```bash
GET /api/companies/13
```

**Response (if oil & gas company):**
```json
{
  "success": true,
  "is_oil_gas_company": true,
  "data": {
    "company_id": 13,
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

**Response (if NOT oil & gas company):**
```json
{
  "success": false,
  "is_oil_gas_company": false,
  "message": "This company is not an oil & gas company",
  "company_name": "APPLE INC",
  "ticker_symbol": "AAPL"
}
```

---

### 3. Request Approval
**Endpoint:** `POST /api/companies/{id}/request-approval`

**Description:** User requests approval for a company. Sets `admin_approval_flag` to `"PENDING"`.

**Parameters:**
- `id` (path): Company ID

**Example:**
```bash
POST /api/companies/13/request-approval
```

**Response:**
```json
{
  "success": true,
  "message": "Approval request submitted successfully",
  "data": {
    "company_id": 13,
    "company_name": "EXXON MOBIL CORP",
    "ticker_symbol": "XOM",
    "admin_approval_flag": "PENDING"
  }
}
```

**Requirements:**
- Company must exist
- Company must have details extracted (`extraction_flag = true`)
- Company must be an oil & gas company

---

### 4. Approve Company (Admin)
**Endpoint:** `POST /api/companies/{id}/approve`

**Description:** Admin approves a company. Changes `admin_approval_flag` from `"PENDING"` to `"APPROVED"`.

**Parameters:**
- `id` (path): Company ID

**Example:**
```bash
POST /api/companies/13/approve
```

**Response:**
```json
{
  "success": true,
  "message": "Company approved successfully",
  "data": {
    "company_id": 13,
    "company_name": "EXXON MOBIL CORP",
    "ticker_symbol": "XOM",
    "admin_approval_flag": "APPROVED"
  }
}
```

**Requirements:**
- Company must be in `"PENDING"` status

---

### 5. Reject Company (Admin)
**Endpoint:** `POST /api/companies/{id}/reject`

**Description:** Admin rejects a company. Changes `admin_approval_flag` from `"PENDING"` to `"REJECTED"`.

**Parameters:**
- `id` (path): Company ID

**Example:**
```bash
POST /api/companies/13/reject
```

**Response:**
```json
{
  "success": true,
  "message": "Company rejected successfully",
  "data": {
    "company_id": 13,
    "company_name": "EXXON MOBIL CORP",
    "ticker_symbol": "XOM",
    "admin_approval_flag": "REJECTED"
  }
}
```

**Requirements:**
- Company must be in `"PENDING"` status

---

### 6. Lookup by Ticker (Legacy)
**Endpoint:** `GET /api/companies/lookup/{ticker}`

**Description:** Direct SEC lookup by ticker symbol (legacy endpoint).

**Parameters:**
- `ticker` (path): Ticker symbol (e.g., "XOM")

**Example:**
```bash
GET /api/companies/lookup/XOM
```

---

### 7. Get All Companies from SEC (Admin/Tool)
**Endpoint:** `GET /api/companies/sec/all`

**Description:** Get all companies from SEC API (for bulk loading tool).

**Query Parameters:**
- `oil_gas_only` (optional): Filter to only oil & gas companies (default: false)
- `limit` (optional): Limit number of results (max: 1000)
- `offset` (optional): Pagination offset (default: 0)

**Example:**
```bash
GET /api/companies/sec/all?oil_gas_only=true&limit=20
```

**Note:** This endpoint is slow and should be used for bulk loading only.

---

## Other Endpoints

### Metrics
**Endpoint:** `GET /api/metrics`
**Description:** Get metrics data

### Summary
**Endpoint:** `GET /api/summary`
**Description:** Get summary data

### Metric Detail
**Endpoint:** `GET /api/metric/{id}`
**Description:** Get detailed metric information

### Confidence
**Endpoint:** `GET /api/confidence`
**Description:** Get confidence data

---

## Endpoint Summary Table

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/api/companies` | Search companies | No |
| GET | `/api/companies/{id}` | Get company details | No |
| POST | `/api/companies/{id}/request-approval` | Request approval | No |
| POST | `/api/companies/{id}/approve` | Approve company | No (Admin) |
| POST | `/api/companies/{id}/reject` | Reject company | No (Admin) |
| GET | `/api/companies/lookup/{ticker}` | Lookup by ticker | No |
| GET | `/api/companies/sec/all` | Get all from SEC | No |
| GET | `/api/metrics` | Get metrics | No |
| GET | `/api/summary` | Get summary | No |
| GET | `/api/metric/{id}` | Get metric detail | No |
| GET | `/api/confidence` | Get confidence | No |

---

## Workflow Examples

### User Workflow
1. **Search**: `GET /api/companies?search=exxon`
2. **View Details**: `GET /api/companies/13`
3. **Request Approval**: `POST /api/companies/13/request-approval`

### Admin Workflow
1. **View Pending**: Filter companies with `admin_approval_flag = "PENDING"`
2. **Approve**: `POST /api/companies/13/approve`
3. **Or Reject**: `POST /api/companies/13/reject`

---

## Notes

- All endpoints return JSON
- Error responses include appropriate HTTP status codes (400, 404, 500, 503)
- `admin_approval_flag` values: `NULL`, `"PENDING"`, `"APPROVED"`, `"REJECTED"` (must be uppercase)
- Oil & gas detection is based on SIC codes

