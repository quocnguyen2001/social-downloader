<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Settings\PaymentGatewaySettings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestBankTransferCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:bank-transfer
                            {--create-test-data : Create test transaction data}
                            {--cleanup : Remove test data}';

    /**
     * The console command description.
     */
    protected $description = 'Test bank transfer checking functionality';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Bank Transfer Command Functionality');
        $this->line('');

        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        if ($this->option('create-test-data')) {
            return $this->createTestData();
        }

        // Test configuration
        $this->testConfiguration();

        // Test command execution
        $this->testCommandExecution();

        return 0;
    }

    /**
     * Test configuration validation.
     */
    private function testConfiguration(): void
    {
        $this->info('Testing Configuration...');

        $settings = app(PaymentGatewaySettings::class);

        $this->line('Bank Transfer Enabled: '.($settings->bank_transfer_enabled ? 'Yes' : 'No'));
        $this->line('API Endpoint: '.($settings->api_transactions_api ?? 'Not configured'));
        $this->line('Template: '.($settings->money_transfer_content_template ?? 'Not configured'));

        if (! $settings->bank_transfer_enabled) {
            $this->warn('⚠ Bank transfer is not enabled');
        }

        if (empty($settings->api_transactions_api)) {
            $this->warn('⚠ API endpoint is not configured');
        }

        if (empty($settings->money_transfer_content_template)) {
            $this->warn('⚠ Template is not configured');
        }

        $this->line('');
    }

    /**
     * Test command execution.
     */
    private function testCommandExecution(): void
    {
        $this->info('Testing Command Execution...');

        // Check for pending transactions
        $pendingCount = Transaction::byPaymentMethod('bank_transfer')->pending()->count();
        $this->line("Pending bank transfer transactions: {$pendingCount}");

        if ($pendingCount === 0) {
            $this->warn('No pending bank transfer transactions found.');
            $this->line('Use --create-test-data to create test transactions.');
        } else {
            $this->info('Running bank transfer check in dry-run mode...');
            $this->call('bank-transfer:check', ['--dry-run' => true, '--batch-size' => 5]);
        }

        $this->line('');
    }

    /**
     * Create test transaction data.
     */
    private function createTestData(): int
    {
        $this->info('Creating test transaction data...');

        // Get or create a test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create test transactions
        $transactions = [];
        for ($i = 1; $i <= 3; $i++) {
            $chargeId = 'TEST-'.Str::random(8);
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'charge_id' => $chargeId,
                'order_id' => 'ORDER-'.$i,
                'payment_method' => 'bank_transfer',
                'currency' => 'VND',
                'amount' => 99000 + ($i * 1000), // 99000, 100000, 101000
                'status' => 'pending',
                'payment_logs' => [
                    [
                        'timestamp' => now()->toISOString(),
                        'event' => 'created',
                        'note' => 'Test transaction created',
                    ],
                ],
            ]);

            $transactions[] = $transaction;
            $this->line("Created transaction: {$transaction->id} (Charge ID: {$chargeId})");
        }

        $this->info('✓ Created '.count($transactions).' test transactions');
        $this->line('');
        $this->info('Test transaction details:');

        foreach ($transactions as $transaction) {
            $this->line("  ID: {$transaction->id}");
            $this->line("  Charge ID: {$transaction->charge_id}");
            $this->line('  Amount: '.number_format((float) $transaction->amount, 0)." {$transaction->currency}");
            $this->line("  Status: {$transaction->status}");
            $this->line('');
        }

        $this->info('You can now test the bank transfer command with these transactions.');
        $this->line('Run: php artisan bank-transfer:check --dry-run');

        return 0;
    }

    /**
     * Clean up test data.
     */
    private function cleanup(): int
    {
        $this->info('Cleaning up test data...');

        // Remove test transactions
        $deleted = Transaction::where('charge_id', 'like', 'TEST-%')->delete();
        $this->line("Deleted {$deleted} test transactions");

        // Remove test user if no other data
        $testUser = User::where('email', 'test@example.com')->first();
        if ($testUser) {
            $remainingTransactions = Transaction::where('user_id', $testUser->id)->count();
            if ($remainingTransactions === 0) {
                $testUser->delete();
                $this->line('Deleted test user');
            } else {
                $this->line("Kept test user (has {$remainingTransactions} remaining transactions)");
            }
        }

        $this->info('✓ Cleanup completed');

        return 0;
    }
}
