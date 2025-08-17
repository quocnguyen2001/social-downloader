<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Settings\PaymentGatewaySettings;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckBankTransferCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bank-transfer:check
                            {--batch-size=10 : Number of transactions to process in one batch}
                            {--dry-run : Show what would be matched without actually updating database}
                            {--force : Skip confirmation prompts for automated execution}';

    /**
     * The console command description.
     */
    protected $description = 'Check and match pending bank transfer transactions with external bank API';

    /**
     * Payment gateway settings instance.
     */
    private PaymentGatewaySettings $settings;

    /**
     * Minimum score required for automatic transaction completion.
     */
    private const MIN_AUTO_COMPLETE_SCORE = 70;

    /**
     * Minimum score for manual review consideration.
     */
    private const MIN_REVIEW_SCORE = 50;

    /**
     * API response cache duration in seconds.
     */
    private const CACHE_DURATION = 30;

    /**
     * Execute the console command.
     */
    public function handle(PaymentGatewaySettings $settings): int
    {
        $this->settings = $settings;

        try {
            // Load and validate configuration
            if (! $this->validateConfiguration()) {
                return 1;
            }

            $batchSize = (int) $this->option('batch-size');
            $dryRun = $this->option('dry-run');
            $force = $this->option('force');

            if ($dryRun) {
                $this->warn('DRY RUN MODE - No transactions will be updated');
            }

            // Fetch pending transactions
            $pendingTransactions = $this->fetchPendingTransactions($batchSize);

            if ($pendingTransactions->isEmpty()) {
                $this->info('No pending bank transfer transactions found.');

                return 0;
            }

            $this->info("Found {$pendingTransactions->count()} pending transaction(s) to process.");

            // Fetch bank transactions from API
            $bankTransactions = $this->fetchBankTransactions();

            if (empty($bankTransactions)) {
                $this->warn('No bank transactions retrieved from API.');

                return 0;
            }

            $this->info('Retrieved '.count($bankTransactions).' bank transaction(s) from API.');

            // Process matching
            $results = $this->processMatching($pendingTransactions, $bankTransactions, $dryRun, $force);

            // Display results
            $this->displayResults($results);

            return $results['failed'] > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('CheckBankTransferCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Validate configuration settings.
     */
    private function validateConfiguration(): bool
    {
        if (! $this->settings->bank_transfer_enabled) {
            $this->error('Bank transfer is not enabled in payment gateway settings.');

            return false;
        }

        if (empty($this->settings->api_transactions_api)) {
            $this->error('Bank transactions API endpoint is not configured.');

            return false;
        }

        if (empty($this->settings->money_transfer_content_template)) {
            $this->error('Money transfer content template is not configured.');

            return false;
        }

        return true;
    }

    /**
     * Fetch pending bank transfer transactions.
     */
    private function fetchPendingTransactions(int $limit): \Illuminate\Database\Eloquent\Collection
    {
        return Transaction::byPaymentMethod('bank_transfer')
            ->pending()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Fetch bank transactions from external API.
     */
    private function fetchBankTransactions(): array
    {
        $cacheKey = 'bank_transactions_'.date('Y-m-d_H-i-s');

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () {
            try {
                $response = Http::timeout(30)
                    ->retry(3, 1000)
                    ->get($this->settings->api_transactions_api);

                if (! $response->successful()) {
                    Log::warning('Bank API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return [];
                }

                $data = $response->json();

                if (! $this->validateApiResponse($data)) {
                    return [];
                }

                // Filter for incoming transactions only
                return array_filter($data['transactions'], function ($transaction) {
                    return isset($transaction['type']) && $transaction['type'] === 'IN';
                });

            } catch (\Exception $e) {
                Log::error('Failed to fetch bank transactions', [
                    'error' => $e->getMessage(),
                    'api_endpoint' => $this->settings->api_transactions_api,
                ]);

                return [];
            }
        });
    }

    /**
     * Validate API response structure.
     */
    private function validateApiResponse(array $data): bool
    {
        if (! isset($data['status']) || $data['status'] !== 'success') {
            Log::warning('Bank API returned non-success status', ['response' => $data]);

            return false;
        }

        if (! isset($data['transactions']) || ! is_array($data['transactions'])) {
            Log::warning('Bank API response missing transactions array', ['response' => $data]);

            return false;
        }

        return true;
    }

    /**
     * Process matching between pending transactions and bank transactions.
     */
    private function processMatching(
        \Illuminate\Database\Eloquent\Collection $pendingTransactions,
        array $bankTransactions,
        bool $dryRun,
        bool $force
    ): array {
        $results = [
            'processed' => 0,
            'matched' => 0,
            'review_needed' => 0,
            'failed' => 0,
            'matches' => [],
            'reviews' => [],
            'errors' => [],
        ];

        foreach ($pendingTransactions as $transaction) {
            $results['processed']++;

            try {
                $match = $this->findBestMatch($transaction, $bankTransactions);

                if ($match && $match['score'] >= self::MIN_AUTO_COMPLETE_SCORE) {
                    // Auto-complete match
                    if (! $dryRun) {
                        if ($force || $this->confirmMatch($transaction, $match)) {
                            $this->updateTransactionStatus($transaction, $match);
                        }
                    }
                    $results['matched']++;
                    $results['matches'][] = [
                        'transaction' => $transaction,
                        'match' => $match,
                    ];

                } elseif ($match && $match['score'] >= self::MIN_REVIEW_SCORE) {
                    // Needs manual review
                    $results['review_needed']++;
                    $results['reviews'][] = [
                        'transaction' => $transaction,
                        'match' => $match,
                    ];

                    Log::info('Bank transfer match needs review', [
                        'transaction_id' => $transaction->id,
                        'charge_id' => $transaction->charge_id,
                        'score' => $match['score'],
                        'bank_transaction' => $match['bank_transaction'],
                    ]);
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process transaction matching', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Find the best matching bank transaction for a pending transaction.
     */
    private function findBestMatch(Transaction $transaction, array $bankTransactions): ?array
    {
        $expectedDescription = $this->generateExpectedDescription($transaction);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($bankTransactions as $bankTransaction) {
            $score = $this->calculateMatchScore($transaction, $bankTransaction, $expectedDescription);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [
                    'score' => $score,
                    'bank_transaction' => $bankTransaction,
                    'expected_description' => $expectedDescription,
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate match score between a transaction and bank transaction.
     */
    private function calculateMatchScore(
        Transaction $transaction,
        array $bankTransaction,
        string $expectedDescription
    ): int {
        $score = 0;

        // Description matching (50 points max)
        if ($this->isDescriptionMatch($transaction->charge_id, $bankTransaction['description'] ?? '')) {
            $score += 50;
        }

        // Amount matching (30 points max)
        if ($this->isAmountMatch((float) $transaction->amount, (float) ($bankTransaction['amount'] ?? 0))) {
            $score += 30;
        }

        // Date proximity (20 points max)
        $score += $this->calculateDateScore($transaction, $bankTransaction);

        return $score;
    }

    /**
     * Check if description contains the charge ID.
     */
    private function isDescriptionMatch(string $chargeId, string $description): bool
    {
        return stripos($description, $chargeId) !== false;
    }

    /**
     * Check if amounts match within tolerance.
     */
    private function isAmountMatch(float $expectedAmount, float $bankAmount): bool
    {
        $tolerance = max(
            $expectedAmount * 0.01, // 1% tolerance
            1000 // Minimum 1000 VND tolerance
        );

        return abs($expectedAmount - $bankAmount) <= $tolerance;
    }

    /**
     * Calculate date proximity score.
     */
    private function calculateDateScore(Transaction $transaction, array $bankTransaction): int
    {
        if (! isset($bankTransaction['transactionDate'])) {
            return 0;
        }

        try {
            $bankDate = Carbon::createFromFormat('d/m/Y', $bankTransaction['transactionDate']);
            $transactionDate = $transaction->created_at;
            $daysDiff = abs($transactionDate->diffInDays($bankDate));

            return match (true) {
                $daysDiff === 0 => 20, // Same day
                $daysDiff <= 1 => 15,  // Within 1 day
                $daysDiff <= 3 => 10,  // Within 3 days
                $daysDiff <= 7 => 5,   // Within 1 week
                default => 0           // Too old
            };
        } catch (\Exception $e) {
            Log::warning('Failed to parse bank transaction date', [
                'date' => $bankTransaction['transactionDate'],
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Generate expected description from template.
     */
    private function generateExpectedDescription(Transaction $transaction): string
    {
        $template = $this->settings->money_transfer_content_template;

        $placeholders = [
            '{code}' => $transaction->charge_id,
            '{order_id}' => $transaction->order_id,
            '{user_name}' => $transaction->user?->name ?? 'Unknown',
            '{amount}' => number_format((float)$transaction->amount, 0),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Confirm match with user (if not in force mode).
     */
    private function confirmMatch(Transaction $transaction, array $match): bool
    {
        $this->info('Potential match found:');
        $this->line("  Transaction ID: {$transaction->id}");
        $this->line("  Charge ID: {$transaction->charge_id}");
        $this->line('  Amount: '.number_format((float)$transaction->amount, 0)." {$transaction->currency}");
        $this->line('  Bank Amount: '.number_format($match['bank_transaction']['amount'], 0));
        $this->line("  Match Score: {$match['score']}");

        return $this->confirm('Mark this transaction as completed?');
    }

    /**
     * Update transaction status to completed.
     */
    private function updateTransactionStatus(Transaction $transaction, array $match): void
    {
        DB::transaction(function () use ($transaction, $match) {
            $transaction->markAsCompleted();
            $transaction->addPaymentLog('bank_transfer_matched', [
                'bank_transaction_id' => $match['bank_transaction']['transactionID'] ?? null,
                'matched_amount' => $match['bank_transaction']['amount'] ?? null,
                'match_score' => $match['score'],
                'bank_description' => $match['bank_transaction']['description'] ?? null,
                'expected_description' => $match['expected_description'],
                'matched_at' => now()->toISOString(),
            ]);
        });

        Log::info('Bank transfer transaction matched and completed', [
            'transaction_id' => $transaction->id,
            'charge_id' => $transaction->charge_id,
            'match_score' => $match['score'],
        ]);
    }

    /**
     * Display processing results.
     */
    private function displayResults(array $results): void
    {
        $this->info('Processing completed:');
        $this->line("  Processed: {$results['processed']}");
        $this->line("  Matched: {$results['matched']}");
        $this->line("  Need Review: {$results['review_needed']}");
        $this->line("  Failed: {$results['failed']}");

        if ($results['matched'] > 0) {
            $this->info("✓ Successfully matched {$results['matched']} transaction(s)");
        }

        if ($results['review_needed'] > 0) {
            $this->warn("⚠ {$results['review_needed']} transaction(s) need manual review");
        }

        if ($results['failed'] > 0) {
            $this->error("✗ {$results['failed']} transaction(s) failed to process");
            foreach ($results['errors'] as $error) {
                $this->error("  Transaction {$error['transaction_id']}: {$error['error']}");
            }
        }
    }
}
