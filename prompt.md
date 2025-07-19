# Video Downloader API - AI Agent Setup Instructions

## 📋 Project Overview for AI Agent

Create a Video Downloader API system using Laravel 12 + Filament 3. The system allows multiple APPs to use the API through API Keys with billing and rate limiting features.

Reference: Follow Filament demo patterns from https://github.com/filamentphp/demo

## 🗄️ Database Migrations to Create

### 1. create_api_keys_table.php
**Purpose**: Manage API keys for client applications
```php
Schema::create('api_keys', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name'); // APP/Client name
    $table->string('key_hash')->unique(); // Hashed API key
    $table->string('key_prefix', 10)->default('vd_live_'); // Key prefix
    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
    
    // Pricing & Limits
    $table->decimal('price_per_request', 10, 4)->default(0.0500); // Price per request in VND
    $table->integer('daily_limit')->default(1000);
    $table->integer('monthly_limit')->default(30000);
    
    // Usage tracking
    $table->integer('daily_usage')->default(0);
    $table->integer('monthly_usage')->default(0);
    $table->bigInteger('total_usage')->default(0);
    $table->date('last_reset_daily')->useCurrent();
    $table->date('last_reset_monthly')->useCurrent();
    
    // Contact & Billing info
    $table->string('contact_email');
    $table->string('billing_email')->nullable();
    $table->string('company_name')->nullable();
    $table->string('webhook_url', 500)->nullable();
    
    // Permissions (JSON fields)
    $table->json('allowed_platforms')->nullable(); // ["youtube", "tiktok", "instagram", "facebook"]
    $table->json('allowed_qualities')->nullable(); // ["144p", "360p", "720p", "1080p"]
    $table->json('allowed_formats')->nullable();   // ["mp4", "mp3", "webm"]
    
    $table->timestamps();
});
```

### 2. create_api_requests_table.php
**Purpose**: Log all API requests for billing and analytics
```php
Schema::create('api_requests', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');
    
    // Request details
    $table->string('endpoint');
    $table->string('method', 10);
    $table->ipAddress('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    
    // Video details
    $table->string('original_url', 1000)->nullable();
    $table->string('platform', 50)->nullable();
    $table->string('video_title', 500)->nullable();
    $table->string('requested_quality', 10)->nullable();
    $table->string('requested_format', 10)->nullable();
    
    // Response details
    $table->integer('status_code');
    $table->integer('response_time')->nullable(); // milliseconds
    $table->bigInteger('file_size')->nullable();
    $table->string('download_url', 1000)->nullable();
    
    // Billing
    $table->decimal('cost', 10, 4)->default(0); // Cost for this request
    $table->boolean('billed')->default(false);
    
    $table->timestamps();
    $table->index(['api_key_id', 'created_at']);
});
```

### 3. create_monthly_billings_table.php
**Purpose**: Monthly billing summaries
```php
Schema::create('monthly_billings', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');
    
    $table->date('billing_month'); // First day of month
    $table->integer('total_requests')->default(0);
    $table->decimal('total_cost', 12, 2)->default(0.00);
    
    // Platform breakdown
    $table->integer('youtube_requests')->default(0);
    $table->integer('tiktok_requests')->default(0);
    $table->integer('instagram_requests')->default(0);
    $table->integer('facebook_requests')->default(0);
    
    // Payment tracking
    $table->boolean('invoice_sent')->default(false);
    $table->timestamp('invoice_sent_at')->nullable();
    $table->boolean('paid')->default(false);
    $table->timestamp('paid_at')->nullable();
    $table->string('payment_method', 50)->nullable();
    
    $table->timestamps();
    $table->unique(['api_key_id', 'billing_month']);
});
```

### 4. create_download_sessions_table.php
**Purpose**: Track individual download sessions
```php
Schema::create('download_sessions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');
    
    $table->string('original_url', 1000);
    $table->enum('platform', ['youtube', 'facebook', 'instagram', 'tiktok']);
    $table->string('video_id', 255)->nullable();
    $table->string('title', 500)->nullable();
    $table->string('thumbnail_url', 1000)->nullable();
    $table->integer('duration')->nullable(); // seconds
    $table->string('quality', 10);
    $table->string('format', 10);
    $table->bigInteger('file_size')->nullable();
    $table->string('download_url', 1000)->nullable();
    $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
    $table->text('error_message')->nullable();
    $table->timestamp('expires_at')->nullable();
    
    $table->timestamps();
    $table->index(['api_key_id', 'status']);
    $table->index(['status', 'created_at']);
});
```

## 📝 Eloquent Models to Create

### 1. ApiKey.php
```php
// Key features:
- UUID primary key
- Casts for JSON fields (allowed_platforms, allowed_qualities, allowed_formats)
- Relationships: hasMany(ApiRequest), hasMany(MonthlyBilling), hasMany(DownloadSession)
- Methods: canMakeRequest(), incrementUsage(), resetUsageIfNeeded(), generateKey()
- Accessors/Mutators for usage limits and costs
```

### 2. ApiRequest.php
```php
// Key features:
- UUID primary key
- belongsTo(ApiKey)
- Scopes for filtering by date, platform, status
- Accessors for formatted cost, response time
```

### 3. MonthlyBilling.php
```php
// Key features:
- UUID primary key
- belongsTo(ApiKey)
- hasMany(ApiRequest) for the billing period
- Methods: calculateTotal(), markAsPaid(), sendInvoice()
```

### 4. DownloadSession.php
```php
// Key features:
- UUID primary key
- belongsTo(ApiKey)
- Enum casts for platform and status
- Methods: markAsCompleted(), markAsFailed(), isExpired()
```

## 🎛️ Filament Resources to Create

### 1. ApiKeyResource.php
**Features needed:**
- Table with columns: name, company_name, status, daily_usage/daily_limit, monthly_usage/monthly_limit, total_usage
- Filters: status, created date range
- Actions: activate/deactivate, reset usage, generate new key
- Form fields: name, company_name, contact_email, billing_email, limits, pricing, permissions
- Relation managers: ApiRequestsRelationManager, MonthlyBillingsRelationManager

### 2. ApiRequestResource.php
**Features needed:**
- Table with columns: api_key.name, endpoint, platform, status_code, cost, created_at
- Filters: api_key, platform, status_code, date range
- Global search: endpoint, video_title, original_url
- No create/edit (read-only logs)

### 3. MonthlyBillingResource.php
**Features needed:**
- Table with columns: api_key.name, billing_month, total_requests, total_cost, paid status
- Filters: billing_month, paid status, api_key
- Actions: mark as paid, send invoice, download invoice PDF
- Widgets: revenue summary, top clients

### 4. DownloadSessionResource.php
**Features needed:**
- Table with columns: api_key.name, platform, title, quality, format, status, created_at
- Filters: platform, status, quality, api_key
- Actions: retry failed downloads, cleanup expired sessions

## 📊 Filament Widgets to Create

### 1. StatsOverviewWidget
```php
// Cards showing:
- Total API Keys (active/inactive breakdown)
- Today's Requests
- This Month's Revenue
- Active Download Sessions
```

### 2. ApiUsageChart
```php
// Line chart showing:
- Daily API requests over last 30 days
- Revenue trend
- Platform usage breakdown
```

### 3. TopClientsWidget
```php
// Table widget showing:
- Top 10 API keys by usage this month
- Their revenue contribution
- Usage percentage of limits
```

## 🔧 Seeder to Create

### DatabaseSeeder.php
```php
// Create sample data:
- 5 API keys with different statuses and limits
- 100+ API requests across different platforms
- Monthly billing records for last 3 months
- Various download sessions with different statuses
```

## 📋 Task Checklist for AI Agent

### Phase 1: Database Setup
- [ ] Create all 4 migration files
- [ ] Run migrations to create tables
- [ ] Create factory files for each model
- [ ] Create comprehensive seeder with realistic data

### Phase 2: Models
- [ ] Create ApiKey model with all methods and relationships
- [ ] Create ApiRequest model with scopes and accessors
- [ ] Create MonthlyBilling model with calculation methods
- [ ] Create DownloadSession model with status management

### Phase 3: Filament Resources
- [ ] Create ApiKeyResource with full CRUD and relation managers
- [ ] Create ApiRequestResource (read-only with advanced filters)
- [ ] Create MonthlyBillingResource with billing actions
- [ ] Create DownloadSessionResource with session management

### Phase 4: Filament Widgets
- [ ] Create StatsOverviewWidget with key metrics
- [ ] Create ApiUsageChart with usage trends
- [ ] Create TopClientsWidget showing revenue leaders

### Phase 5: Testing
- [ ] Seed database with comprehensive test data
- [ ] Verify all Filament resources work correctly
- [ ] Test all relationships and filters
- [ ] Ensure widgets display accurate data

## 🎯 Expected Output

After completion, the admin panel should have:
- Complete API key management system
- Real-time usage monitoring
- Billing and revenue tracking
- Comprehensive analytics and reporting
- Professional admin interface following Filament best practices

The system should be ready for API endpoint development in the next phase.
