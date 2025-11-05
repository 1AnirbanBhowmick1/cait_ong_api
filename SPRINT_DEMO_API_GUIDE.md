# Sprint Demo: API Endpoints Guide

## Overview

This document provides a comprehensive guide for demonstrating all API endpoints in the Caitong v1 application. Each endpoint is documented with its use case, request/response examples, and practical demo scenarios.

---

## Table of Contents

1. [Company Management Endpoints](#company-management-endpoints)
2. [Metrics Endpoints](#metrics-endpoints)
3. [Summary/Aggregation Endpoints](#summaryaggregation-endpoints)
4. [Data Quality & Confidence Endpoints](#data-quality--confidence-endpoints)
5. [Demo Scenarios](#demo-scenarios)

---

## Company Management Endpoints

### 1. Search Companies
**Endpoint:** `GET /api/companies`

**Use Case:** Search and browse companies in the local database. Perfect for dropdowns, search bars, and company selection interfaces.

**Request Parameters:**
- `search` (optional): Search by company name or ticker symbol (case-insensitive)
- `limit` (optional): Number of results per page (default: 50, max: 100)
- `offset` (optional): Pagination offset (default: 0)

**Example Request:**
```bash
# Basic search - get all companies
GET /api/companies

# Search for specific company
GET /api/companies?search=exxon

# Search with pagination
GET /api/companies?search=energy&limit=20&offset=0
```

**Example Response:**
```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 12
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

**Demo Tips:**
- Show basic search functionality
- Demonstrate fuzzy search (e.g., "exxon" finds "EXXON MOBIL CORP")
- Show pagination with large result sets

---

### 2. Get Company Details
**Endpoint:** `GET /api/companies/{id}`

**Use Case:** Get detailed information about a specific company. Automatically fetches from SEC API if details haven't been extracted yet, and validates if it's an oil & gas company.

**Request Parameters:**
- `id` (path): Company ID

**Example Request:**
```bash
GET /api/companies/13
```

**Example Response (Oil & Gas Company):**
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

**Example Response (Non-Oil & Gas Company):**
```json
{
  "success": false,
  "is_oil_gas_company": false,
  "message": "This company is not an oil & gas company",
  "company_name": "APPLE INC",
  "ticker_symbol": "AAPL"
}
```

**Demo Tips:**
- Show how it automatically fetches from SEC if not already extracted
- Demonstrate oil & gas validation
- Show the difference between cached and fresh data

---

### 3. Request Approval
**Endpoint:** `POST /api/companies/{id}/request-approval`

**Use Case:** User workflow - request approval for a company to be tracked. Sets the company's approval status to "PENDING" for admin review.

**Request Parameters:**
- `id` (path): Company ID

**Example Request:**
```bash
POST /api/companies/13/request-approval
```

**Example Response:**
```json
{
  "success": true,
  "message": "Approval request submitted successfully",
  "is_oil_gas_company": true,
  "data": {
    "company_id": 13,
    "company_name": "EXXON MOBIL CORP",
    "ticker_symbol": "XOM",
    "sec_cik_number": "0000034088",
    "sic_code": "2911",
    "sic_description": "Petroleum Refining",
    "admin_approval_flag": "PENDING"
  }
}
```

**Requirements:**
- Company must exist
- Company must be an oil & gas company
- If details not extracted, automatically fetches and validates

**Demo Tips:**
- Demonstrate the approval workflow
- Show what happens with non-oil & gas companies
- Show status transition from NULL → PENDING

---

### 4. Approve Company (Admin)
**Endpoint:** `POST /api/companies/{id}/approve`

**Use Case:** Admin workflow - approve a company that's been requested. Changes status from "PENDING" to "APPROVED".

**Request Parameters:**
- `id` (path): Company ID

**Example Request:**
```bash
POST /api/companies/13/approve
```

**Example Response:**
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
- Company must be in "PENDING" status

**Demo Tips:**
- Show admin approval flow
- Demonstrate error handling (trying to approve non-pending company)

---

### 5. Reject Company (Admin)
**Endpoint:** `POST /api/companies/{id}/reject`

**Use Case:** Admin workflow - reject a company that's been requested. Changes status from "PENDING" to "REJECTED".

**Request Parameters:**
- `id` (path): Company ID

**Example Request:**
```bash
POST /api/companies/13/reject
```

**Example Response:**
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
- Company must be in "PENDING" status

**Demo Tips:**
- Show rejection workflow
- Demonstrate status management

---

### 6. Lookup by Ticker (Legacy)
**Endpoint:** `GET /api/companies/lookup/{ticker}`

**Use Case:** Direct SEC lookup by ticker symbol. Legacy endpoint for quick lookups.

**Request Parameters:**
- `ticker` (path): Ticker symbol (e.g., "XOM")

**Example Request:**
```bash
GET /api/companies/lookup/XOM
```

**Demo Tips:**
- Quick lookup demonstration
- Show SEC API integration

---

### 7. Get All Companies from SEC (Admin/Tool)
**Endpoint:** `GET /api/companies/sec/all`

**Use Case:** Bulk loading tool - fetch all companies from SEC API. Can filter to oil & gas companies only.

**Request Parameters:**
- `oil_gas_only` (optional): Filter to only oil & gas companies (default: false)
- `limit` (optional): Limit number of results (max: 1000)
- `offset` (optional): Pagination offset (default: 0)

**Example Request:**
```bash
# Get all companies (limited)
GET /api/companies/sec/all?limit=20

# Get only oil & gas companies
GET /api/companies/sec/all?oil_gas_only=true&limit=50
```

**⚠️ Performance Note:** This endpoint can be slow due to SEC API rate limits. Use for bulk loading operations only.

**Demo Tips:**
- Show bulk loading capability
- Demonstrate oil & gas filtering
- Note performance considerations

---

## Metrics Endpoints

### 8. Get Metrics
**Endpoint:** `GET /api/metrics`

**Use Case:** Retrieve detailed metric values for a company with advanced filtering. Supports unit conversion, confidence filtering, and multi-dimensional filtering (period, basin, segment, etc.).

**Request Parameters:**
- `company_id` (required): Company ID
- `period_end_date` (optional): Filter by period end date (format: YYYY-MM-DD)
- `period` (optional): Filter by quarter (format: YYYY-Q[1-4], e.g., "2024-Q4")
- `metric` (optional): Comma-separated metric names (e.g., "boe_production,oil_production")
- `basin` (optional): Filter by basin name
- `segment` (optional): Filter by segment name
- `confidence_min` (optional): Minimum confidence score (0-1)
- `limit` (optional): Number of results (default: 50, max: 1000)
- `offset` (optional): Pagination offset (default: 0)
- `sort_by` (optional): Sort field and direction (format: "field:asc|desc", default: "period_end_date:desc")

**Example Request:**
```bash
# Get all metrics for a company
GET /api/metrics?company_id=13

# Get metrics for specific period
GET /api/metrics?company_id=13&period=2024-Q4

# Get specific metrics with confidence filter
GET /api/metrics?company_id=13&metric=boe_production,oil_production&confidence_min=0.8

# Filter by basin and segment
GET /api/metrics?company_id=13&basin=Permian&segment=Upstream

# Advanced: Multiple filters with sorting
GET /api/metrics?company_id=13&period_end_date=2024-12-31&metric=boe_production&confidence_min=0.7&sort_by=extraction_confidence_score:desc
```

**Example Response:**
```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 25
  },
  "data": [
    {
      "metric_value_id": 123,
      "company_id": 13,
      "company_name": "EXXON MOBIL CORP",
      "metric_name_internal": "boe_production",
      "metric_name_display": "BOE Production",
      "original_value": 500000,
      "original_unit": "bbl/d",
      "normalized_value": 500000,
      "normalized_unit": "bbl/d",
      "period_start_date": "2024-10-01",
      "period_end_date": "2024-12-31",
      "basin_name": "Permian",
      "segment_name": "Upstream",
      "extraction_confidence_score": 0.95,
      "extraction_method": "xbrl",
      "source_document_id": 456,
      "source_url": "https://sec.gov/...",
      "created_at": "2024-01-15T10:30:00+00:00"
    }
  ]
}
```

**Key Features:**
- **Unit Conversion:** Automatically converts values to normalized units
- **Multi-dimensional Filtering:** Filter by period, metric type, basin, segment, confidence
- **Flexible Sorting:** Sort by any field in ascending or descending order
- **Pagination:** Handle large result sets efficiently

**Demo Tips:**
- Show filtering capabilities (period, metric, basin, segment)
- Demonstrate unit conversion (show original vs normalized)
- Show confidence filtering for data quality
- Demonstrate sorting options

---

## Summary/Aggregation Endpoints

### 9. Get Summary (Aggregated KPIs)
**Endpoint:** `GET /api/summary`

**Use Case:** Get aggregated KPIs per company, basin, or segment for a given period. Perfect for dashboards and comparative analysis.

**Request Parameters:**
- `period_end_date` (required if period not provided): Filter by period end date (format: YYYY-MM-DD)
- `period` (required if period_end_date not provided): Filter by quarter (format: YYYY-Q[1-4])
- `metrics` (optional): Comma-separated metric names (default: "boe_production,oil_production,gas_production")
- `group_by` (optional): Group results by "company", "basin", or "segment" (default: "company")
- `company_ids` (optional): Comma-separated company IDs to filter
- `confidence_min` (optional): Minimum confidence score (0-1)

**Example Request:**
```bash
# Get summary by company for Q4 2024
GET /api/summary?period=2024-Q4

# Get summary grouped by basin
GET /api/summary?period=2024-Q4&group_by=basin

# Get summary for specific companies and metrics
GET /api/summary?period=2024-Q4&company_ids=13,14,15&metrics=boe_production,oil_production

# Get summary with confidence filter
GET /api/summary?period_end_date=2024-12-31&confidence_min=0.8
```

**Example Response (Grouped by Company):**
```json
[
  {
    "company_id": 13,
    "company_name": "EXXON MOBIL CORP",
    "basin_name": null,
    "segment_name": null,
    "metric_name_internal": "boe_production",
    "metric_name_display": "BOE Production",
    "aggregated_normalized_value": 1500000,
    "aggregated_normalized_unit": "bbl/d",
    "avg_confidence": 0.92,
    "record_count": 5
  },
  {
    "company_id": 13,
    "company_name": "EXXON MOBIL CORP",
    "basin_name": null,
    "segment_name": null,
    "metric_name_internal": "oil_production",
    "metric_name_display": "Oil Production",
    "aggregated_normalized_value": 800000,
    "aggregated_normalized_unit": "bbl/d",
    "avg_confidence": 0.94,
    "record_count": 5
  }
]
```

**Example Response (Grouped by Basin):**
```json
[
  {
    "company_id": null,
    "company_name": null,
    "basin_name": "Permian",
    "segment_name": null,
    "metric_name_internal": "boe_production",
    "metric_name_display": "BOE Production",
    "aggregated_normalized_value": 2500000,
    "aggregated_normalized_unit": "bbl/d",
    "avg_confidence": 0.91,
    "record_count": 12
  }
]
```

**Key Features:**
- **Aggregation:** Sums values across multiple records
- **Grouping:** Group by company, basin, or segment
- **Unit Normalization:** All values converted to standard units
- **Confidence Averaging:** Provides average confidence score
- **Record Counting:** Shows how many records were aggregated

**Demo Tips:**
- Show different grouping options (company vs basin vs segment)
- Demonstrate aggregation across multiple records
- Show how it's useful for dashboard KPIs
- Compare single company vs multi-company summaries

---

## Data Quality & Confidence Endpoints

### 10. Get Metric Detail
**Endpoint:** `GET /api/metric/{id}`

**Use Case:** Get complete metadata for a single metric value, including derived quality checks and data validation.

**Request Parameters:**
- `id` (path): Metric Value ID

**Example Request:**
```bash
GET /api/metric/123
```

**Example Response:**
```json
{
  "metric_value_id": 123,
  "company_id": 13,
  "company_name": "EXXON MOBIL CORP",
  "metric_id": 5,
  "metric_name_internal": "boe_production",
  "metric_name_display": "BOE Production",
  "metric_definition_unit": "bbl/d",
  "original_value": 500000,
  "original_unit": "bbl/d",
  "normalized_value": 500000,
  "normalized_unit": "bbl/d",
  "period_start_date": "2024-10-01",
  "period_end_date": "2024-12-31",
  "basin_name": "Permian",
  "segment_name": "Upstream",
  "extraction_confidence_score": 0.95,
  "extraction_method": "xbrl",
  "source_document_id": 456,
  "source_url": "https://sec.gov/...",
  "filing_date": "2024-02-15",
  "source_type": "10-Q",
  "created_at": "2024-01-15T10:30:00+00:00",
  "derived_checks": [
    {
      "name": "Confidence score check",
      "status": "ok",
      "reason": "Confidence score 0.95 meets quality threshold"
    },
    {
      "name": "Period duration check",
      "status": "ok",
      "reason": "Period duration of 92 days is within normal range"
    },
    {
      "name": "Value sanity check",
      "status": "ok",
      "reason": "Value is within expected range"
    },
    {
      "name": "BOE consistency check",
      "status": "ok",
      "reason": "BOE calculation matches within 2.5% tolerance"
    }
  ]
}
```

**Key Features:**
- **Complete Metadata:** All available information about a metric value
- **Derived Checks:** Automatic data quality validation
  - Confidence score threshold check
  - BOE consistency check (cross-validates oil, gas, NGL, and total BOE)
  - Period duration validation
  - Value sanity checks
- **Source Information:** Full source document details including filing date

**Demo Tips:**
- Show derived checks for data quality
- Demonstrate BOE consistency validation
- Show source document information
- Explain how this helps with data validation

---

### 11. Get Low Confidence Metrics
**Endpoint:** `GET /api/confidence`

**Use Case:** Identify metrics below a confidence threshold for review and triage. Critical for data quality management.

**Request Parameters:**
- `threshold` (optional): Confidence threshold (default: 1.0, meaning all metrics below 1.0)
- `company_id` (optional): Filter by company ID
- `period_end_date` (optional): Filter by period end date (format: YYYY-MM-DD)
- `metric` (optional): Comma-separated metric names
- `limit` (optional): Number of results (default: 50, max: 1000)
- `offset` (optional): Pagination offset (default: 0)
- `sort_by` (optional): Sort field and direction (default: "confidence:asc")

**Example Request:**
```bash
# Get all metrics below 0.8 confidence
GET /api/confidence?threshold=0.8

# Get low confidence metrics for specific company
GET /api/confidence?threshold=0.7&company_id=13

# Get low confidence metrics for specific period
GET /api/confidence?threshold=0.75&period_end_date=2024-12-31

# Get low confidence metrics with sorting
GET /api/confidence?threshold=0.8&sort_by=confidence:asc&limit=100
```

**Example Response:**
```json
{
  "meta": {
    "limit": 50,
    "offset": 0,
    "total": 15,
    "threshold": 0.8
  },
  "data": [
    {
      "metric_value_id": 789,
      "company_id": 13,
      "company_name": "EXXON MOBIL CORP",
      "metric_name_display": "BOE Production",
      "original_value": 450000,
      "original_unit": "bbl/d",
      "normalized_value": 450000,
      "normalized_unit": "bbl/d",
      "extraction_confidence_score": 0.65,
      "period_end_date": "2024-12-31",
      "basin_name": "Permian",
      "segment_name": "Upstream",
      "source_document_id": 789,
      "source_url": "https://sec.gov/...",
      "extraction_method": "ocr",
      "review_hint": "High priority (confidence: 0.65); extracted from image/PDF via OCR - may contain recognition errors"
    }
  ]
}
```

**Key Features:**
- **Confidence Filtering:** Find metrics below quality threshold
- **Review Hints:** Automatic suggestions for why metrics need review
- **Priority Classification:** Severity levels (Critical, High, Medium) based on confidence
- **Method-Specific Hints:** Context-aware hints based on extraction method
- **Unit Conversion:** Normalized values for comparison

**Review Hint Categories:**
- **Severity Levels:** Critical (<0.5), High (0.5-0.7), Medium (0.7-0.8)
- **Extraction Method Hints:**
  - OCR: May contain recognition errors
  - HTML/Table: Verify structure parsing
  - XBRL/EDGAR: Check tag mapping
  - LLM/AI: Requires manual verification
  - Manual: Verify source accuracy
- **Additional Checks:** Zero/null values, missing source URLs

**Demo Tips:**
- Show how to identify data quality issues
- Demonstrate review hints and their usefulness
- Show filtering by company, period, or metric
- Explain the priority classification system
- Show how this supports data quality workflows

---

## Demo Scenarios

### Scenario 1: Company Onboarding Workflow
**Demonstrates:** Company search, details retrieval, and approval workflow

1. **Search for Company:**
   ```bash
   GET /api/companies?search=exxon
   ```

2. **Get Company Details:**
   ```bash
   GET /api/companies/13
   ```
   - Show automatic SEC API integration
   - Show oil & gas validation

3. **Request Approval:**
   ```bash
   POST /api/companies/13/request-approval
   ```
   - Show status change to PENDING

4. **Admin Approves:**
   ```bash
   POST /api/companies/13/approve
   ```
   - Show status change to APPROVED

---

### Scenario 2: Metrics Analysis
**Demonstrates:** Metric retrieval, filtering, and unit conversion

1. **Get All Metrics for Company:**
   ```bash
   GET /api/metrics?company_id=13
   ```

2. **Filter by Period:**
   ```bash
   GET /api/metrics?company_id=13&period=2024-Q4
   ```

3. **Filter by Metric Type and Confidence:**
   ```bash
   GET /api/metrics?company_id=13&metric=boe_production,oil_production&confidence_min=0.8
   ```

4. **Get Detailed View:**
   ```bash
   GET /api/metric/123
   ```
   - Show derived checks
   - Show source information

---

### Scenario 3: Dashboard KPIs
**Demonstrates:** Aggregated summaries for dashboards

1. **Company-Level Summary:**
   ```bash
   GET /api/summary?period=2024-Q4&group_by=company
   ```

2. **Basin-Level Summary:**
   ```bash
   GET /api/summary?period=2024-Q4&group_by=basin
   ```

3. **Multi-Company Comparison:**
   ```bash
   GET /api/summary?period=2024-Q4&company_ids=13,14,15
   ```

---

### Scenario 4: Data Quality Management
**Demonstrates:** Identifying and reviewing low-confidence data

1. **Find Low Confidence Metrics:**
   ```bash
   GET /api/confidence?threshold=0.8
   ```

2. **Filter by Company:**
   ```bash
   GET /api/confidence?threshold=0.7&company_id=13
   ```

3. **Review Specific Metric:**
   ```bash
   GET /api/metric/789
   ```
   - Show derived checks
   - Show review hints

---

## Quick Reference Table

| Endpoint | Method | Use Case | Key Feature |
|----------|--------|----------|-------------|
| `/api/companies` | GET | Search companies | Fuzzy search, pagination |
| `/api/companies/{id}` | GET | Get company details | Auto-fetch from SEC, O&G validation |
| `/api/companies/{id}/request-approval` | POST | Request approval | User workflow |
| `/api/companies/{id}/approve` | POST | Approve company | Admin workflow |
| `/api/companies/{id}/reject` | POST | Reject company | Admin workflow |
| `/api/companies/lookup/{ticker}` | GET | Direct SEC lookup | Legacy endpoint |
| `/api/companies/sec/all` | GET | Bulk load companies | Bulk operations |
| `/api/metrics` | GET | Get metrics | Advanced filtering, unit conversion |
| `/api/summary` | GET | Aggregated KPIs | Grouping, aggregation |
| `/api/metric/{id}` | GET | Metric detail | Quality checks, source info |
| `/api/confidence` | GET | Low confidence metrics | Data quality triage |

---

## Testing the APIs

### Using cURL
```bash
# Search companies
curl -X GET "http://localhost:8000/api/companies?search=exxon"

# Get company details
curl -X GET "http://localhost:8000/api/companies/13"

# Get metrics
curl -X GET "http://localhost:8000/api/metrics?company_id=13&period=2024-Q4"

# Get summary
curl -X GET "http://localhost:8000/api/summary?period=2024-Q4"

# Get low confidence metrics
curl -X GET "http://localhost:8000/api/confidence?threshold=0.8"
```

### Using Postman
Postman collections are available:
- `postman_collection.json` - Main collection
- `postman_summary_collection.json` - Summary endpoints
- `postman_metrics_collection.json` - Metrics endpoints
- `postman_metric_detail_collection.json` - Metric detail
- `postman_confidence_collection.json` - Confidence endpoints

---

## Notes for Demo

1. **Base URL:** All endpoints are prefixed with `/api`
2. **Response Format:** All responses are JSON
3. **Error Handling:** All endpoints return appropriate HTTP status codes (400, 404, 500, 503)
4. **Unit Conversion:** Metrics endpoints automatically convert to normalized units
5. **Data Quality:** Confidence scores range from 0.0 to 1.0
6. **Approval Status:** Values are NULL, "PENDING", "APPROVED", or "REJECTED" (uppercase)

---

## Common Use Cases

### Frontend Integration
- **Company Selector:** Use `/api/companies` with search
- **Company Details Page:** Use `/api/companies/{id}` for details
- **Metrics Table:** Use `/api/metrics` with filters
- **Dashboard:** Use `/api/summary` for KPIs
- **Data Quality Panel:** Use `/api/confidence` for review queue

### Data Management
- **Bulk Loading:** Use `/api/companies/sec/all` for initial load
- **Quality Review:** Use `/api/confidence` to find issues
- **Validation:** Use `/api/metric/{id}` to inspect specific records

---

## End of Document

For additional information, refer to:
- `API_DOCUMENTATION.md` - Detailed API documentation
- `API_ENDPOINTS_SUMMARY.md` - Quick endpoint reference
- `COMPANIES_API_README.md` - Company-specific documentation

