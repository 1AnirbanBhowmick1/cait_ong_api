# CAITong API v1

<p align="center">
<img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
</p>

<p align="center">
<strong>Corporate Analytics Intelligence Tool (CAITong) - Energy Sector Metrics API</strong>
</p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About CAITong API

CAITong (Corporate Analytics Intelligence Tool) is a comprehensive Laravel-based API system designed for tracking and analyzing energy sector company metrics. The platform provides structured access to corporate data including company information, financial metrics, operational KPIs, and confidence scoring for data extraction processes.

### Key Features

- **Company Management**: Track energy sector companies with detailed metadata
- **Metrics Tracking**: Comprehensive metric definitions and value tracking
- **Data Confidence Scoring**: AI-powered confidence scoring for extracted data
- **Multi-dimensional Filtering**: Filter by company, period, basin, segment, and more
- **RESTful API Design**: Clean, consistent API endpoints with proper error handling
- **Performance Optimization**: Built-in caching, database indexing, and query optimization
- **Comprehensive Testing**: Full test coverage with factories and feature tests

## API Endpoints

The CAITong API provides the following endpoints for accessing energy sector data:

### Companies API
- **GET** `/api/companies` - Retrieve list of tracked companies
  - Parameters: `active_only`, `search`, `limit`, `offset`
  - Returns: Company metadata with ticker symbols and company types

### Metrics API
- **GET** `/api/metrics` - Get available metric definitions
  - Returns: Metric categories, names, units, and groupings

### Summary API
- **GET** `/api/summary` - Get aggregated summary data
  - Returns: High-level metrics and KPIs across companies

### Metric Detail API
- **GET** `/api/metric/{id}` - Get detailed metric information
  - Returns: Specific metric values with extraction details

### Confidence API
- **GET** `/api/confidence` - Get data confidence scores
  - Returns: Confidence metrics for data extraction processes

## Quick Start

### Prerequisites

- PHP 8.2 or higher
- PostgreSQL database
- Composer
- Node.js & NPM (for frontend assets)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd caitong_v1
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Database configuration**
   Update your `.env` file with PostgreSQL credentials:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=your_database_name
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   DB_SCHEMA=cait_dev
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Start the development server**
   ```bash
   php artisan serve
   ```

The API will be available at `http://localhost:8000/api/`

### Testing the API

Test the companies endpoint:
```bash
curl http://localhost:8000/api/companies
```

Search for companies:
```bash
curl "http://localhost:8000/api/companies?search=Energy&limit=10"
```

Run the test suite:
```bash
php artisan test
```

## Project Structure

```
caitong_v1/
├── app/
│   ├── Http/Controllers/          # API Controllers
│   │   ├── CompanyController.php   # Companies API
│   │   ├── MetricsController.php   # Metrics API
│   │   ├── SummaryController.php   # Summary API
│   │   ├── MetricDetailController.php # Metric Detail API
│   │   └── ConfidenceController.php # Confidence API
│   ├── Models/                     # Eloquent Models
│   │   ├── Company.php             # Company model
│   │   ├── MetricDefinition.php    # Metric definitions
│   │   ├── MetricValue.php         # Metric values
│   │   └── SourceDocument.php      # Source documents
│   └── Services/                   # Business Logic
│       └── UnitConverterService.php # Unit conversion utilities
├── database/
│   ├── migrations/                 # Database migrations
│   ├── factories/                  # Model factories for testing
│   └── seeders/                    # Database seeders
├── tests/
│   ├── Feature/                    # Feature tests
│   └── Unit/                       # Unit tests
├── routes/
│   └── api.php                     # API route definitions
└── Documentation/
    ├── API_DOCUMENTATION.md        # Complete API documentation
    ├── COMPANIES_API_README.md     # Companies API guide
    ├── IMPLEMENTATION_SUMMARY.md   # Implementation details
    ├── DEPLOYMENT_CHECKLIST.md     # Deployment guide
    └── QUICK_REFERENCE.md         # Quick reference card
```

## Data Models

### Company
- **Purpose**: Track energy sector companies
- **Key Fields**: `company_id`, `company_name`, `ticker_symbol`, `company_type`, `status`
- **Types**: upstream, integrated, midstream, downstream

### MetricDefinition
- **Purpose**: Define available metrics and their properties
- **Key Fields**: `metric_id`, `metric_category`, `metric_name_display`, `metric_unit`, `metric_group`

### MetricValue
- **Purpose**: Store actual metric values with extraction metadata
- **Key Fields**: `company_id`, `metric_id`, `extracted_metric_value`, `extraction_confidence_score`, `period_start_date`, `period_end_date`

## Performance Features

- **Caching**: 5-minute result caching for frequently accessed data
- **Database Indexing**: Optimized indexes on filter and sort columns
- **Query Optimization**: Efficient Eloquent queries with proper relationships
- **Pagination**: Built-in pagination support for large datasets

## Documentation

- **[API Documentation](API_DOCUMENTATION.md)** - Complete API reference
- **[Implementation Summary](IMPLEMENTATION_SUMMARY.md)** - Technical implementation details
- **[Deployment Checklist](DEPLOYMENT_CHECKLIST.md)** - Production deployment guide
- **[Quick Reference](QUICK_REFERENCE.md)** - Quick reference card for developers

## Testing

The project includes comprehensive test coverage:

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --filter CompanyApiTest
php artisan test --filter MetricsApiTest
php artisan test --filter SummaryApiTest
```

## Development Commands

```bash
# Start development server
php artisan serve

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Run migrations
php artisan migrate

# Generate application key
php artisan key:generate

# List all routes
php artisan route:list
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

For support and questions:
- Check the documentation files in the project root
- Review the API documentation for endpoint details
- Check Laravel logs at `storage/logs/laravel.log` for errors

---

**Built with Laravel 12** - A powerful PHP framework for building robust web applications.
