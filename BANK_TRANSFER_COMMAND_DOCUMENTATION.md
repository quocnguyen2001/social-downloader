# Bank Transfer Command Documentation

## Overview

The `CheckBankTransferCommand` automatically verifies and matches pending bank transfer transactions with external bank API responses. This command helps automate the process of confirming bank transfer payments by matching transaction details.

## Features

- **Automatic Matching**: Matches pending transactions with bank API responses using fuzzy matching
- **Configurable Tolerance**: Uses smart amount tolerance (1% or minimum 1000 VND)
- **Batch Processing**: Processes transactions in configurable batches
- **Comprehensive Logging**: Detailed logs for audit trails and debugging
- **Dry Run Mode**: Test matching without updating database
- **Caching**: Reduces API load with intelligent caching
- **Error Handling**: Graceful handling of API failures and edge cases

## Configuration

### Payment Gateway Settings

Configure the following settings in the admin panel under Payment Gateway Configuration:

1. **Bank Transfer Enabled**: Must be enabled
2. **API Transactions API**: External bank API endpoint URL
3. **Money Transfer Content Template**: Template for generating expected descriptions

### Template Placeholders

The template supports the following placeholders:
- `{code}` - Replaced with transaction charge_id
- `{order_id}` - Replaced with order ID
- `{user_name}` - Replaced with customer name
- `{amount}` - Replaced with formatted amount

Example template: `"Payment for order #{code} - {user_name}"`

## Command Usage

### Basic Usage

```bash
# Check pending transactions (default batch size: 10)
php artisan bank-transfer:check

# Dry run mode (no database updates)
php artisan bank-transfer:check --dry-run

# Custom batch size
php artisan bank-transfer:check --batch-size=20

# Force mode (skip confirmations for automation)
php artisan bank-transfer:check --force
```

### Testing

```bash
# Create test transaction data
php artisan test:bank-transfer --create-test-data

# Test the command with test data
php artisan bank-transfer:check --dry-run

# Clean up test data
php artisan test:bank-transfer --cleanup
```

## Scheduling

The command is automatically scheduled to run every 5 seconds:

```php
Schedule::command('bank-transfer:check --force')
    ->cron('*/5 * * * * *')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bank-transfer-check.log'));
```

## Matching Algorithm

### Scoring System

Each potential match is scored based on three criteria:

1. **Description Match (50 points)**: Charge ID found in bank transaction description
2. **Amount Match (30 points)**: Amount within tolerance range
3. **Date Proximity (20 points)**: How recent the bank transaction is

### Score Thresholds

- **≥70 points**: Automatic completion
- **50-69 points**: Manual review required
- **<50 points**: No match

### Amount Tolerance

```php
$tolerance = max(
    $expectedAmount * 0.01, // 1% tolerance
    1000 // Minimum 1000 VND tolerance
);
```

## API Response Format

Expected bank API response structure:

```json
{
    "status": "success",
    "message": "Success message",
    "transactions": [
        {
            "transactionID": "unique_id",
            "amount": 99000,
            "description": "Payment for order #TEST-ABC123",
            "transactionDate": "17/08/2025",
            "type": "IN"
        }
    ]
}
```

## Logging

### Log Levels

- **Info**: Successful matches, processing summaries
- **Warning**: Partial matches needing review, configuration issues
- **Error**: API failures, database errors
- **Debug**: Detailed matching attempts

### Log Files

- **Main Log**: `storage/logs/bank-transfer-check.log`
- **Laravel Log**: `storage/logs/laravel.log`

## Security Considerations

- API credentials are securely handled through settings
- All external data is validated before processing
- Database transactions ensure consistency
- Sensitive financial data is not logged in plain text

## Performance Optimizations

- **Caching**: API responses cached for 30 seconds
- **Batch Processing**: Configurable batch sizes prevent memory issues
- **Database Optimization**: Efficient queries with proper indexing
- **Overlap Prevention**: `withoutOverlapping()` prevents concurrent executions

## Error Handling

### Common Issues

1. **API Connection Failures**
   - Logged as errors
   - Command continues with next batch
   - Exponential backoff for retries

2. **Invalid Response Format**
   - Logged as warnings
   - Malformed data skipped
   - Processing continues

3. **Database Errors**
   - Transactions rolled back
   - Errors logged with details
   - Processing continues with other transactions

### Troubleshooting

1. **No matches found**
   - Check API endpoint configuration
   - Verify template format
   - Review bank transaction descriptions

2. **API timeouts**
   - Check network connectivity
   - Verify API endpoint availability
   - Review timeout settings

3. **Permission errors**
   - Verify database permissions
   - Check file system permissions for logs
   - Ensure proper Laravel configuration

## Monitoring

### Key Metrics

- Processing rate (transactions per minute)
- Match success rate
- API response times
- Error frequencies

### Health Checks

```bash
# Check configuration
php artisan test:bank-transfer

# View recent logs
tail -f storage/logs/bank-transfer-check.log

# Check pending transactions
php artisan tinker
>>> Transaction::byPaymentMethod('bank_transfer')->pending()->count()
```

## Best Practices

1. **Regular Monitoring**: Monitor logs for errors and performance issues
2. **Configuration Validation**: Regularly verify API endpoints and templates
3. **Test Data**: Use test commands to verify functionality
4. **Backup Strategy**: Ensure proper database backups before deployment
5. **Performance Tuning**: Adjust batch sizes based on transaction volume

## Support

For issues or questions:

1. Check the logs for detailed error information
2. Use dry-run mode to test without side effects
3. Verify configuration settings in admin panel
4. Test with sample data using test commands