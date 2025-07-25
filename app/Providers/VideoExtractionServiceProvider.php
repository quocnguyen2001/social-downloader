<?php

namespace App\Providers;

use App\Events\ExtractionCompleted;
use App\Events\ExtractionFailed;
use App\Events\VideoExtractionRequested;
use App\Listeners\HandleExtractionRequest;
use App\Listeners\UpdateExtractionStatistics;
use App\Services\VideoExtraction\Factory\DriverFactory;
use App\Services\VideoExtraction\Registry\DriverRegistry;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Service provider for video extraction functionality.
 *
 * This provider registers all the necessary bindings, events, and listeners
 * for the video extraction system.
 */
class VideoExtractionServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
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
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/video-extraction.php',
            'video-extraction'
        );

        // Register the driver registry as a singleton
        $this->app->singleton(DriverRegistry::class, function ($app) {
            return new DriverRegistry;
        });

        // Register the driver factory as a singleton
        $this->app->singleton(DriverFactory::class, function ($app) {
            $registry = $app->make(DriverRegistry::class);

            return new DriverFactory($registry);
        });

        // Register driver factory alias
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

        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/video-extraction.php' => config_path('video-extraction.php'),
            ], 'video-extraction-config');
        }

        // Create temp directory if it doesn't exist
        $this->createTempDirectory();

        // Register console commands if running in console
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
        }

        // Validate yt-dlp installation
        $this->validateYtDlpInstallation();
    }

    /**
     * Create the temporary directory for video extraction.
     */
    private function createTempDirectory(): void
    {
        $tempDir = config('video-extraction.temp.directory');

        if (! file_exists($tempDir)) {
            try {
                mkdir($tempDir, 0755, true);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to create video extraction temp directory', [
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
                \App\Console\Commands\TestVideoExtraction::class,
                \App\Console\Commands\TestApiKeyAuthentication::class,
                \App\Console\Commands\TestAuthenticatedApiKeySingleton::class,
                \App\Console\Commands\ProcessScheduledFileDeletions::class,
            ]);
        }
    }

    /**
     * Validate that yt-dlp is installed and accessible.
     */
    private function validateYtDlpInstallation(): void
    {
        $binaryPath = config('video-extraction.yt_dlp.binary_path');

        if (! file_exists($binaryPath) || ! is_executable($binaryPath)) {
            \Illuminate\Support\Facades\Log::warning('yt-dlp binary not found or not executable', [
                'binary_path' => $binaryPath,
                'exists' => file_exists($binaryPath),
                'executable' => file_exists($binaryPath) && is_executable($binaryPath),
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
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
