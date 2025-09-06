<?php

namespace App\Providers;

use App\Console\Commands\ProcessScheduledFileDeletions;
use App\Console\Commands\TestApiKeyAuthentication;
use App\Console\Commands\TestAuthenticatedApiKeySingleton;
use App\Console\Commands\TestVideoExtraction;
use App\Events\ExtractionCompleted;
use App\Events\ExtractionFailed;
use App\Events\VideoExtractionRequested;
use App\Listeners\HandleExtractionRequest;
use App\Listeners\UpdateExtractionStatistics;
use App\Services\VideoExtraction\Factory\DriverFactory;
use App\Services\VideoExtraction\Registry\DriverRegistry;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Log;

class VideoExtractionServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        VideoExtractionRequested::class => [
            HandleExtractionRequest::class,
        ],
        ExtractionCompleted::class => [
            UpdateExtractionStatistics::class.'@handleCompleted',
        ],
        ExtractionFailed::class => [
            UpdateExtractionStatistics::class.'@handleFailed',
        ],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/video-extraction.php',
            'video-extraction'
        );

        $this->app->singleton(DriverRegistry::class, function ($app) {
            return new DriverRegistry;
        });

        $this->app->singleton(DriverFactory::class, function ($app) {
            $registry = $app->make(DriverRegistry::class);

            return new DriverFactory($registry);
        });

        $this->app->alias(DriverFactory::class, 'video-extraction.factory');
        $this->app->alias(DriverRegistry::class, 'video-extraction.registry');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Boot parent to register event listeners
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/video-extraction.php' => config_path('video-extraction.php'),
            ], 'video-extraction-config');
        }

        $this->createTempDirectory();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        $this->validateYtDlpInstallation();
    }

    private function createTempDirectory(): void
    {
        $tempDir = config('video-extraction.temp.directory');

        if (! file_exists($tempDir)) {
            try {
                mkdir($tempDir, 0755, true);
            } catch (\Exception $e) {
                Log::warning('Failed to create video extraction temp directory', [
                    'directory' => $tempDir,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Register console commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestVideoExtraction::class,
                TestApiKeyAuthentication::class,
                TestAuthenticatedApiKeySingleton::class,
                ProcessScheduledFileDeletions::class,
            ]);
        }
    }

    private function validateYtDlpInstallation(): void
    {
        $binaryPath = config('video-extraction.yt_dlp.binary_path');

        if (! file_exists($binaryPath) || ! is_executable($binaryPath)) {
            Log::warning('yt-dlp binary not found or not executable', [
                'binary_path' => $binaryPath,
                'exists' => file_exists($binaryPath),
                'executable' => file_exists($binaryPath) && is_executable($binaryPath),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            DriverFactory::class,
            DriverRegistry::class,
            'video-extraction.factory',
            'video-extraction.registry',
        ];
    }
}
