# Lottery Ticket Scraping & Analysis System

A comprehensive Laravel-based system for scraping, analyzing, and ranking lottery tickets from multiple state lottery websites with advanced filtering and export capabilities.

## 🎯 Features

### Core Functionality

-   **Multi-State Lottery Scraping**: Supports DC Lottery, Maryland Lottery, Virginia Lottery
-   **Real-time Data Extraction**: Ticket prices, prizes, dates, ROI calculations
-   **Advanced Ranking System**: Multiple ranking criteria with tie-breaking logic
-   **Excel Export**: Multi-sheet exports with detailed ticket information
-   **Expired Ticket Filtering**: Automatically excludes tickets past their claim date

### Ranking Algorithms

#### 🥇 Top 10 ROI Rankings

-   Sorts tickets by current ROI (highest to lowest)
-   Real-time profitability analysis

#### 🆕 Newly Released Tickets

-   Filters tickets released in current month
-   Sorted by current ROI for best new opportunities

#### 💰 Grand Prize Rankings (3-Level Sorting)

1. **Primary**: Top grand prize (highest to lowest)
2. **Secondary**: Ticket cost (highest to lowest) - for same grand prizes
3. **Tertiary**: Grand prizes remaining % (highest to lowest) - for same grand prizes and costs

### Data Extraction Capabilities

-   **Ticket Information**: Title, price, game number, start/end dates
-   **Prize Structure**: Top grand prize, initial grand prize, current grand prize
-   **Financial Metrics**: Initial ROI, current ROI, score calculations
-   **Availability**: Grand prize remaining percentage
-   **Visual Assets**: Ticket images and URLs

## 🚀 API Endpoints

### Get All Tickets Data

```
GET /api/tickets
```

Returns comprehensive ticket data with all rankings and metrics.

### Get Single Ticket

```
GET /api/ticket/{url}
```

Returns detailed information for a specific ticket URL.

### Export Excel File

```
GET /api/export
```

Downloads multi-sheet Excel file with:

-   Overall tickets sheet
-   Ticket details sheet
-   Grand prize rankings sheet
-   Tickets by price sheets

## 📊 Excel Export Sheets

### 1. Overall Tickets Sheet

Complete ticket listing with all metrics and rankings.

### 2. Ticket Details Sheet

Detailed breakdown of individual ticket information.

### 3. Grand Prize Rankings Sheet

Dedicated sheet showing only grand prize tickets ranked by the 3-level sorting system.

### 4. Tickets by Price Sheets

Individual sheets for each ticket price point with detailed analysis.

## 🔧 Technical Architecture

### Services

-   **UniversalScrapingService**: Handles HTTP requests with retry logic and timeouts
-   **ScraperFactory**: Factory pattern for different lottery site scrapers
-   **BaseScraper**: Abstract base class for scraper implementations
-   **DCLotteryScraper**: DC Lottery specific scraping logic

### Key Components

-   **HTTP Client**: Robust request handling with 30s timeout, 3 retries
-   **DOM Crawler**: Symfony DomCrawler for HTML parsing
-   **Date Handling**: Carbon for date parsing and comparisons
-   **Collection Processing**: Laravel Collections for data manipulation
-   **Error Handling**: Comprehensive exception handling and logging

### Data Processing Pipeline

1. **Scraping**: Parallel HTTP requests to lottery sites
2. **Parsing**: HTML extraction using CSS selectors
3. **Calculation**: ROI, scores, and financial metrics
4. **Filtering**: Remove expired tickets and invalid data
5. **Ranking**: Apply multi-level sorting algorithms
6. **Export**: Generate Excel files with multiple sheets

## 🛠 Installation & Setup

### Prerequisites

-   PHP 8.1+
-   Laravel 10+
-   Composer
-   Node.js (for Vite)

### Installation Steps

```bash
# Clone repository
git clone <repository-url>
cd myproject

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

### Environment Configuration

```env
APP_NAME="Lottery Scraping System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

## 📈 Recent Updates

### Latest Features (v2.0)

-   ✅ **3-Level Grand Prize Ranking**: Enhanced sorting with tie-breaking logic
-   ✅ **Expired Ticket Filtering**: Automatic exclusion of past-due tickets
-   ✅ **Grand Prize Sheet**: Dedicated Excel export for grand prize tickets
-   ✅ **Robust HTTP Client**: Improved error handling and retry mechanisms
-   ✅ **Enhanced Debugging**: Comprehensive logging for troubleshooting
-   ✅ **Table Scraping Fix**: First table only extraction for accurate data

### Technical Improvements

-   **Error Handling**: Fixed `ConnectionException::effectiveUri()` issues
-   **HTTP Stability**: Added timeouts, retries, and User-Agent headers
-   **Data Accuracy**: Improved table extraction and date parsing
-   **Performance**: Optimized collection processing and sorting algorithms

## 🔍 Debugging & Logging

The system includes comprehensive logging for troubleshooting:

```php
// Debug logs available for:
- Grand prize ranking calculations
- Ticket filtering and sorting
- HTTP request responses
- Excel export data
- Collection processing steps
```

Logs are stored in `storage/logs/laravel.log`

## 📋 Usage Examples

### Get All Tickets

```bash
curl http://localhost:8000/api/tickets
```

### Export Excel File

```bash
curl -O -J http://localhost:8000/api/export
```

### Get Single Ticket

```bash
curl http://localhost:8000/api/ticket/https://dclottery.com/dc-scratchers/300x
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For issues and questions:

-   Check the logs in `storage/logs/laravel.log`
-   Review the API documentation above
-   Ensure all dependencies are installed
-   Verify environment configuration

---

**Built with Laravel 10** - A modern PHP framework for web artisans.
