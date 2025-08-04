# Lottery Ticket Scraping & Analysis System

A comprehensive Laravel-based system for scraping, analyzing, and ranking lottery tickets from multiple state lottery websites with advanced filtering and export capabilities.

## 🎯 Features

### Core Functionality

-   **Multi-State Lottery Scraping**: Supports DC Lottery, Maryland Lottery, Missouri Lottery, Virginia Lottery
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

### URL Management Endpoints

#### Get Configured States

```
GET /api/states
```

Returns all configured states with their URLs and statistics.

#### Get Games List URLs

```
GET /api/states/urls
```

Returns games list URLs for all active states.

#### Validate URL

```
POST /api/validate-url
```

Validates if a URL belongs to any configured state.

**Request Body:**

```json
{
    "url": "https://www.molottery.com/games/scratch-off-games/123"
}
```

**Response:**

```json
{
    "valid": true,
    "state": {
        "name": "Missouri Lottery",
        "key": "missouri",
        "domains": ["molottery.com", "www.molottery.com"]
    }
}
```

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

## 📚 Module Guide

### 🎯 Controllers

#### ScrapController (`app/Http/Controllers/ScrapController.php`)

**Purpose**: Main orchestration controller for scraping, processing, and API responses.

**Key Methods**:

-   `getMultipleData()`: Scrapes all lottery sites and returns comprehensive ticket data
-   `scrapeSingleSite()`: Scrapes individual ticket URL with validation
-   `calculateTicketMetrics()`: Processes raw data into calculated metrics (ROI, scores, etc.)
-   `processAllTickets()`: Applies ranking algorithms and assigns rankings
-   `exportTickets()`: Triggers Excel export with multiple sheets

**Features**:

-   Expired ticket filtering using end dates
-   3-level grand prize ranking system
-   Comprehensive error handling and logging
-   Support for multiple lottery sites

### 🔧 Services

#### UniversalScrapingService (`app/Services/UniversalScrapingService.php`)

**Purpose**: Handles HTTP requests with robust error handling and retry logic.

**Key Features**:

-   Parallel request processing for multiple URLs
-   Automatic retry mechanism (3 attempts with 1s delay)
-   30-second timeout for each request
-   User-Agent headers for better compatibility
-   Exception handling for connection issues

**Methods**:

-   `scrapeMultipleSites()`: Parallel scraping of multiple URLs
-   `scrapeSingleSite()`: Single URL scraping with error handling

#### LotteryUrlService (`app/Services/LotteryUrlService.php`)

**Purpose**: Manages lottery URLs for different states with configuration-based approach.

**Key Features**:

-   State-based URL configuration
-   Domain validation and state detection
-   URL building and validation
-   Active/inactive state management
-   Statistics and reporting

**Methods**:

-   `getActiveStates()`: Get all active states
-   `getStateByDomain()`: Find state by domain
-   `buildGameDetailUrl()`: Build game detail URL
-   `validateUrl()`: Validate URL and get state info
-   `getStateStats()`: Get state statistics

#### ScraperFactory (`app/Services/ScraperFactory.php`)

**Purpose**: Factory pattern for creating appropriate scrapers based on URL.

**Features**:

-   Automatic scraper selection based on URL pattern
-   Extensible design for adding new lottery sites
-   Centralized scraper management

#### BaseScraper (`app/Services/Scrapers/BaseScraper.php`)

**Purpose**: Abstract base class providing common scraping functionality.

**Key Methods**:

-   `extractPrizes()`: Extracts prize information from HTML
-   `extractTicketInfo()`: Extracts basic ticket details
-   `getFirstTable()`: Ensures only first table is scraped (prevents duplicate data)

**Features**:

-   DOM traversal using Symfony Crawler
-   CSS selector-based data extraction
-   Error handling for malformed HTML

#### DCLotteryScraper (`app/Services/Scrapers/DCLotteryScraper.php`)

**Purpose**: DC Lottery specific scraping implementation.

**Key Features**:

-   DC Lottery website structure handling
-   End date extraction for expired ticket filtering
-   Prize table parsing with first-table-only logic
-   Game number and ticket details extraction

**Data Extracted**:

-   Ticket title, price, game number
-   Start and end dates
-   Prize structure (top, initial, current grand prizes)
-   Grand prize remaining percentage
-   Ticket images and URLs

#### MissouriLotteryScraper (`app/Services/Scrapers/MissouriLotteryScraper.php`)

**Purpose**: Missouri Lottery specific scraping implementation.

**Key Features**:

-   Missouri Lottery website structure handling
-   Flexible CSS selector system for different page layouts
-   Multiple fallback strategies for data extraction
-   Support for various Missouri Lottery domains

**Data Extracted**:

-   Ticket title, price, game number
-   Start and end dates (with claim deadline)
-   Prize structure with remaining prizes
-   Odds information (overall and top prize odds)
-   Ticket images with absolute URL conversion

### 📊 Exports

#### TicketsExport (`app/Exports/TicketsExport.php`)

**Purpose**: Main Excel export orchestrator with multiple sheets.

**Sheets Generated**:

-   Overall tickets sheet
-   Ticket details sheet
-   Grand prize rankings sheet
-   Individual price-based sheets

#### OverallTicketsSheet (`app/Exports/OverallTicketsSheet.php`)

**Purpose**: Complete ticket listing with all metrics and rankings.

**Columns**:

-   Rankings (ROI, Newly Released, Grand Prize)
-   Ticket information (title, price, dates)
-   Financial metrics (ROI, scores)
-   Prize structure and availability

#### TicketDetailSheet (`app/Exports/TicketDetailSheet.php`)

**Purpose**: Detailed breakdown of individual ticket information.

**Features**:

-   Comprehensive ticket data
-   All calculated metrics
-   Ranking information
-   End date for expiration tracking

#### GrandPrizeSheet (`app/Exports/GrandPrizeSheet.php`)

**Purpose**: Dedicated sheet for grand prize tickets with 3-level ranking.

**Features**:

-   Only grand prize tickets included
-   Sorted by 3-level ranking system
-   Grand prize specific metrics
-   Clear ranking display

#### TicketsByPriceSheet (`app/Exports/TicketsByPriceSheet.php`)

**Purpose**: Individual sheets for each ticket price point.

**Features**:

-   Price-based grouping
-   Detailed analysis per price point
-   Comprehensive metrics for each group

### 🗄️ Models

#### User (`app/Models/User.php`)

**Purpose**: Laravel's default user model for authentication.

### 🔧 Configuration

#### Scrapers Config (`config/scrapers.php`)

**Purpose**: Configuration for different lottery sites and their settings.

**Features**:

-   Site-specific configurations
-   URL patterns for scraper selection
-   Timeout and retry settings

#### Services Config (`config/services.php`)

**Purpose**: External service configurations.

#### Lottery URLs Config (`config/lottery_urls.php`)

**Purpose**: Configuration for different lottery states and their URLs.

**Features**:

-   State-based URL configuration
-   Domain mapping for each state
-   URL patterns for games and details
-   Active/inactive state management
-   Global scraping settings

### 🛣️ Routes

#### Web Routes (`routes/web.php`)

**Purpose**: Web interface routes (if any).

#### API Routes (`routes/api.php`)

**Purpose**: API endpoints for ticket data and exports.

**Endpoints**:

-   `GET /api/tickets`: Get all tickets with rankings
-   `GET /api/ticket/{url}`: Get single ticket details
-   `GET /api/export`: Download Excel export

### 📁 Directory Structure

```
app/
├── Http/Controllers/
│   └── ScrapController.php          # Main orchestration
├── Services/
│   ├── UniversalScrapingService.php # HTTP client & retry logic
│   ├── ScraperFactory.php          # Factory pattern
│   ├── LotteryUrlService.php       # URL management service
│   └── Scrapers/
│       ├── BaseScraper.php         # Abstract base class
│       ├── DCLotteryScraper.php    # DC Lottery specific
│       ├── MarylandLotteryScraper.php
│       ├── MissouriLotteryScraper.php # Missouri Lottery specific
│       └── VirginiaLotteryScraper.php
├── Exports/
│   ├── TicketsExport.php           # Main export orchestrator
│   ├── OverallTicketsSheet.php     # Complete ticket listing
│   ├── TicketDetailSheet.php       # Detailed breakdown
│   ├── GrandPrizeSheet.php         # Grand prize rankings
│   └── TicketsByPriceSheet.php     # Price-based sheets
└── Models/
    └── User.php                    # User authentication
```

### 🔄 Data Flow

1. **Input**: Lottery site URLs
2. **Scraping**: UniversalScrapingService → ScraperFactory → DCLotteryScraper
3. **Processing**: ScrapController → calculateTicketMetrics → processAllTickets
4. **Ranking**: Apply 3-level sorting algorithms
5. **Export**: TicketsExport → Multiple Excel sheets
6. **Output**: API responses and Excel files

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
