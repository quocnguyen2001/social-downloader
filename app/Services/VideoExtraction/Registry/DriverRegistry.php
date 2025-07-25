<?php

namespace App\Services\VideoExtraction\Registry;

use App\Enums\Platform;
use App\Services\VideoExtraction\Contracts\DriverInterface;
use App\Services\VideoExtraction\Exceptions\UnsupportedPlatformException;
use Illuminate\Support\Facades\Log;

/**
 * Registry for managing video extraction drivers.
 *
 * This registry maintains a collection of available drivers
 * and provides methods for registration, discovery, and instantiation.
 */
class DriverRegistry
{
    /**
     * Registered driver classes.
     *
     * @var array<string, string> Platform value => Driver class name
     */
    private array $drivers = [];

    /**
     * Driver instances cache.
     *
     * @var array<string, DriverInterface> Platform value => Driver instance
     */
    private array $instances = [];

    /**
     * Driver configurations.
     *
     * @var array<string, array> Platform value => Configuration array
     */
    private array $configurations = [];

    /**
     * Register a driver for a platform.
     *
     * @param  Platform  $platform  The platform this driver handles
     * @param  string  $driverClass  The driver class name
     * @param  array  $config  Optional default configuration for this driver
     *
     * @throws \InvalidArgumentException If the driver class doesn't implement DriverInterface
     */
    public function register(Platform $platform, string $driverClass, array $config = []): self
    {
        if (! class_exists($driverClass)) {
            throw new \InvalidArgumentException("Driver class {$driverClass} does not exist");
        }

        if (! is_subclass_of($driverClass, DriverInterface::class)) {
            throw new \InvalidArgumentException(
                "Driver class {$driverClass} must implement ".DriverInterface::class
            );
        }

        $this->drivers[$platform->value] = $driverClass;
        $this->configurations[$platform->value] = $config;

        // Clear cached instance if it exists
        unset($this->instances[$platform->value]);

        Log::info('Driver registered', [
            'platform' => $platform->value,
            'driver_class' => $driverClass,
        ]);

        return $this;
    }

    /**
     * Get a driver instance for a platform.
     *
     * @param  Platform  $platform  The platform to get a driver for
     * @param  array  $config  Optional configuration to override defaults
     * @return DriverInterface The driver instance
     *
     * @throws UnsupportedPlatformException If no driver is registered for the platform
     */
    public function get(Platform $platform, array $config = []): DriverInterface
    {
        if (! $this->has($platform)) {
            throw new UnsupportedPlatformException(
                url: '',
                detectedPlatform: $platform->value
            );
        }

        $platformValue = $platform->value;
        $configKey = $platformValue.'_'.md5(serialize($config));

        // Return cached instance if available and no custom config
        if (empty($config) && isset($this->instances[$platformValue])) {
            return $this->instances[$platformValue];
        }

        $driverClass = $this->drivers[$platformValue];
        $defaultConfig = $this->configurations[$platformValue] ?? [];
        $finalConfig = array_merge($defaultConfig, $config);

        $driver = new $driverClass($finalConfig);

        // Cache the instance only if no custom config was provided
        if (empty($config)) {
            $this->instances[$platformValue] = $driver;
        }

        Log::debug('Driver instance created', [
            'platform' => $platform->value,
            'driver_class' => $driverClass,
            'config_provided' => ! empty($config),
        ]);

        return $driver;
    }

    /**
     * Check if a driver is registered for a platform.
     *
     * @param  Platform  $platform  The platform to check
     * @return bool True if a driver is registered
     */
    public function has(Platform $platform): bool
    {
        return isset($this->drivers[$platform->value]);
    }

    /**
     * Unregister a driver for a platform.
     *
     * @param  Platform  $platform  The platform to unregister
     */
    public function unregister(Platform $platform): self
    {
        $platformValue = $platform->value;

        unset($this->drivers[$platformValue]);
        unset($this->instances[$platformValue]);
        unset($this->configurations[$platformValue]);

        Log::info('Driver unregistered', ['platform' => $platform->value]);

        return $this;
    }

    /**
     * Get all registered driver classes.
     *
     * @return array<Platform, string> Array of platform => driver class mappings
     */
    public function getAllDriverClasses(): array
    {
        $result = [];

        foreach ($this->drivers as $platformValue => $driverClass) {
            $platform = Platform::from($platformValue);
            $result[$platform] = $driverClass;
        }

        return $result;
    }

    /**
     * Get all supported platforms.
     *
     * @return array<Platform> Array of supported platforms
     */
    public function getSupportedPlatforms(): array
    {
        return array_map(
            fn ($platformValue) => Platform::from($platformValue),
            array_keys($this->drivers)
        );
    }

    /**
     * Get all driver instances (creating them if necessary).
     *
     * @return array<Platform, DriverInterface> Array of platform => driver instance mappings
     */
    public function getAllDrivers(): array
    {
        $result = [];

        foreach ($this->getSupportedPlatforms() as $platform) {
            $result[$platform] = $this->get($platform);
        }

        return $result;
    }

    /**
     * Clear all cached driver instances.
     */
    public function clearCache(): self
    {
        $this->instances = [];

        Log::info('Driver registry cache cleared');

        return $this;
    }

    /**
     * Get registry statistics.
     *
     * @return array Registry statistics
     */
    public function getStatistics(): array
    {
        return [
            'registered_drivers' => count($this->drivers),
            'cached_instances' => count($this->instances),
            'supported_platforms' => array_keys($this->drivers),
            'driver_classes' => array_values($this->drivers),
        ];
    }

    /**
     * Validate all registered drivers.
     *
     * @return array Validation results
     */
    public function validateDrivers(): array
    {
        $results = [];

        foreach ($this->drivers as $platformValue => $driverClass) {
            $platform = Platform::from($platformValue);
            $result = [
                'platform' => $platformValue,
                'driver_class' => $driverClass,
                'valid' => true,
                'errors' => [],
            ];

            try {
                // Check if class exists
                if (! class_exists($driverClass)) {
                    $result['valid'] = false;
                    $result['errors'][] = 'Class does not exist';
                }

                // Check if class implements DriverInterface
                if (! is_subclass_of($driverClass, DriverInterface::class)) {
                    $result['valid'] = false;
                    $result['errors'][] = 'Class does not implement DriverInterface';
                }

                // Try to instantiate the driver
                $driver = $this->get($platform);

                // Check if the driver returns the correct platform
                if ($driver->getPlatform() !== $platform) {
                    $result['valid'] = false;
                    $result['errors'][] = 'Driver returns incorrect platform';
                }

            } catch (\Exception $e) {
                $result['valid'] = false;
                $result['errors'][] = $e->getMessage();
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Set default configuration for a platform.
     *
     * @param  Platform  $platform  The platform
     * @param  array  $config  The configuration array
     */
    public function setDefaultConfig(Platform $platform, array $config): self
    {
        $this->configurations[$platform->value] = $config;

        // Clear cached instance to force recreation with new config
        unset($this->instances[$platform->value]);

        return $this;
    }

    /**
     * Get default configuration for a platform.
     *
     * @param  Platform  $platform  The platform
     * @return array The configuration array
     */
    public function getDefaultConfig(Platform $platform): array
    {
        return $this->configurations[$platform->value] ?? [];
    }
}
