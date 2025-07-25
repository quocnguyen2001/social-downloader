<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyApiSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:verify-setup {--fix : Attempt to fix issues automatically}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that the API authentication system is properly configured';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Verifying API Authentication Setup...');
        $this->newLine();

        $checks = [
            'Database Connection' => $this->checkDatabase(),
            'Sanctum Configuration' => $this->checkSanctum(),
            'User Model Setup' => $this->checkUserModel(),
            'Middleware Configuration' => $this->checkMiddleware(),
            'Rate Limiting Setup' => $this->checkRateLimiting(),
            'CORS Configuration' => $this->checkCors(),
            'Security Headers' => $this->checkSecurityHeaders(),
            'Email Configuration' => $this->checkEmailConfig(),
            'Filament Integration' => $this->checkFilamentIntegration(),
            'API Routes' => $this->checkApiRoutes(),
        ];

        $passed = 0;
        $total = count($checks);

        foreach ($checks as $name => $result) {
            if ($result) {
                $this->info("✅ {$name}");
                $passed++;
            } else {
                $this->error("❌ {$name}");
            }
        }

        $this->newLine();
        $this->info("Results: {$passed}/{$total} checks passed");

        if ($passed === $total) {
            $this->info('🎉 All checks passed! Your API authentication system is ready.');

            return Command::SUCCESS;
        } else {
            $this->warn('⚠️  Some checks failed. Please review the issues above.');

            if ($this->option('fix')) {
                $this->info('🔧 Attempting to fix issues...');
                $this->attemptFixes();
            }

            return Command::FAILURE;
        }
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            // Check if required tables exist
            $requiredTables = ['users', 'personal_access_tokens'];
            foreach ($requiredTables as $table) {
                if (! Schema::hasTable($table)) {
                    $this->warn("  Missing table: {$table}");

                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->warn("  Database connection failed: {$e->getMessage()}");

            return false;
        }
    }

    private function checkSanctum(): bool
    {
        $configExists = config('sanctum') !== null;
        $guardConfigured = in_array('sanctum', array_keys(config('auth.guards', [])));

        if (! $configExists) {
            $this->warn('  Sanctum config not published');

            return false;
        }

        if (! $guardConfigured) {
            $this->warn('  Sanctum guard not configured');

            return false;
        }

        return true;
    }

    private function checkUserModel(): bool
    {
        try {
            $user = new User;
            $traits = class_uses_recursive($user);

            if (! in_array('Laravel\Sanctum\HasApiTokens', $traits)) {
                $this->warn('  User model missing HasApiTokens trait');

                return false;
            }

            // Check if last_login_at column exists
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $this->warn('  Missing last_login_at column in users table');

                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->warn("  User model check failed: {$e->getMessage()}");

            return false;
        }
    }

    private function checkMiddleware(): bool
    {
        $middlewareClasses = [
            'App\Http\Middleware\SecurityHeaders',
            'App\Http\Middleware\ValidateApiRequest',
            'App\Http\Middleware\SanitizeInput',
        ];

        foreach ($middlewareClasses as $class) {
            if (! class_exists($class)) {
                $this->warn("  Missing middleware: {$class}");

                return false;
            }
        }

        return true;
    }

    private function checkRateLimiting(): bool
    {
        $configExists = config('api_rate_limits') !== null;
        $serviceExists = class_exists('App\Services\RateLimitService');

        if (! $configExists) {
            $this->warn('  Rate limiting config missing');

            return false;
        }

        if (! $serviceExists) {
            $this->warn('  RateLimitService class missing');

            return false;
        }

        // Test cache connection for rate limiting
        try {
            Cache::put('test_rate_limit', 'test', 1);
            Cache::forget('test_rate_limit');

            return true;
        } catch (\Exception $e) {
            $this->warn("  Cache connection failed: {$e->getMessage()}");

            return false;
        }
    }

    private function checkCors(): bool
    {
        $corsConfig = config('cors');

        if (! $corsConfig) {
            $this->warn('  CORS config missing');

            return false;
        }

        $requiredPaths = ['api/*', 'sanctum/csrf-cookie'];
        $configuredPaths = $corsConfig['paths'] ?? [];

        foreach ($requiredPaths as $path) {
            if (! in_array($path, $configuredPaths)) {
                $this->warn("  CORS path not configured: {$path}");

                return false;
            }
        }

        return true;
    }

    private function checkSecurityHeaders(): bool
    {
        $securityConfig = config('api_security.security_headers');

        if (! $securityConfig) {
            $this->warn('  Security headers config missing');

            return false;
        }

        $requiredHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
        ];

        $configuredHeaders = array_keys($securityConfig['headers'] ?? []);

        foreach ($requiredHeaders as $header) {
            if (! in_array($header, $configuredHeaders)) {
                $this->warn("  Security header not configured: {$header}");

                return false;
            }
        }

        return true;
    }

    private function checkEmailConfig(): bool
    {
        $mailConfig = config('mail');

        if (! $mailConfig) {
            $this->warn('  Mail config missing');

            return false;
        }

        // Check if mail classes exist
        $mailClasses = [
            'App\Mail\WelcomeEmail',
            'App\Mail\PasswordResetEmail',
            'App\Mail\PasswordChangedEmail',
        ];

        foreach ($mailClasses as $class) {
            if (! class_exists($class)) {
                $this->warn("  Missing mail class: {$class}");

                return false;
            }
        }

        return true;
    }

    private function checkFilamentIntegration(): bool
    {
        $userResourceExists = class_exists('App\Filament\Resources\UserResource');
        $widgetsExist = class_exists('App\Filament\Widgets\TokenStatsWidget');

        if (! $userResourceExists) {
            $this->warn('  UserResource not found');

            return false;
        }

        if (! $widgetsExist) {
            $this->warn('  Token widgets not found');

            return false;
        }

        return true;
    }

    private function checkApiRoutes(): bool
    {
        $requiredRoutes = [
            'api/auth/register',
            'api/auth/login',
            'api/auth/logout',
            'api/auth/user',
            'api/auth/forgot-password',
            'api/auth/reset-password',
        ];

        try {
            $routes = collect(\Route::getRoutes())->map(function ($route) {
                return $route->uri();
            });

            foreach ($requiredRoutes as $route) {
                if (! $routes->contains($route)) {
                    $this->warn("  Missing route: {$route}");

                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->warn("  Route check failed: {$e->getMessage()}");

            return false;
        }
    }

    private function attemptFixes(): void
    {
        $this->info('Running migrations...');
        Artisan::call('migrate', ['--force' => true]);

        $this->info('Clearing caches...');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('cache:clear');

        $this->info('Publishing Sanctum assets...');
        Artisan::call('vendor:publish', ['--provider' => 'Laravel\Sanctum\SanctumServiceProvider']);

        $this->info('✅ Basic fixes applied. Please re-run the verification.');
    }
}
