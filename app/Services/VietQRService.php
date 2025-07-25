<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * VietQR API Service.
 *
 * This service handles integration with VietQR API to fetch
 * Vietnamese bank list for payment gateway configuration.
 */
class VietQRService
{
    /**
     * VietQR API endpoint for bank list.
     * Note: This endpoint currently returns HTML instead of JSON.
     * Using fallback bank list until proper API endpoint is found.
     */
    private const API_ENDPOINT = 'https://api.vietqr.io/v2/banks';

    /**
     * Cache key for bank list.
     */
    private const CACHE_KEY = 'vietqr_bank_list';

    /**
     * Cache duration in seconds (24 hours).
     */
    private const CACHE_DURATION = 86400;

    /**
     * Request timeout in seconds.
     */
    private const REQUEST_TIMEOUT = 30;

    /**
     * Get Vietnamese bank list from VietQR API.
     *
     * @return array<string, string> Array of bank codes and names
     *
     * @throws \Exception When API request fails
     */
    public function getBankList(): array
    {
        try {
            return Cache::remember(
                self::CACHE_KEY,
                self::CACHE_DURATION,
                fn (): array => $this->fetchBankListFromApi()
            );
        } catch (\Exception $e) {
            Log::error('Failed to get bank list from VietQR API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return cached data if available, otherwise return fallback
            return Cache::get(self::CACHE_KEY, $this->getFallbackBankList());
        }
    }

    /**
     * Fetch bank list from VietQR API.
     *
     * @return array<string, string>
     *
     * @throws \Exception
     */
    private function fetchBankListFromApi(): array
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->retry(3, 1000)
                ->get(self::API_ENDPOINT);

            if (! $response->successful()) {
                throw new RequestException($response);
            }

            $data = $response->json();

            // Handle different possible response formats
            if (isset($data['data']) && is_array($data['data'])) {
                return $this->formatBankList($data['data']);
            } elseif (is_array($data)) {
                return $this->formatBankList($data);
            } else {
                throw new \Exception('Invalid API response format - expected array or object with data property');
            }

        } catch (ConnectionException $e) {
            throw new \Exception('Failed to connect to VietQR API: '.$e->getMessage());
        } catch (RequestException $e) {
            throw new \Exception('VietQR API request failed: '.$e->getMessage());
        }
    }

    /**
     * Format bank list data from API response.
     *
     * @return array<string, string>
     */
    private function formatBankList(array $bankData): array
    {
        $formattedList = [];

        foreach ($bankData as $bank) {
            if (! isset($bank['code'])) {
                continue;
            }

            $code = trim($bank['code']);

            // Use shortName if available, otherwise use name
            $name = trim($bank['shortName'] ?? $bank['name'] ?? '');

            if (empty($code) || empty($name)) {
                continue;
            }

            // Format: "Bank Code - Bank Name"
            $formattedList[$code] = "{$code} - {$name}";
        }

        // Sort by bank name
        asort($formattedList);

        return $formattedList;
    }

    /**
     * Get fallback bank list when API is unavailable.
     *
     * @return array<string, string>
     */
    private function getFallbackBankList(): array
    {
        return [
            'VCB' => 'VCB - Vietcombank',
            'TCB' => 'TCB - Techcombank',
            'BIDV' => 'BIDV - Bank for Investment and Development of Vietnam',
            'VTB' => 'VTB - VietinBank',
            'TPB' => 'TPB - TPBank',
            'STB' => 'STB - Sacombank',
            'HDB' => 'HDB - HDBank',
            'VPB' => 'VPB - VPBank',
            'ACB' => 'ACB - Asia Commercial Bank',
            'MSB' => 'MSB - Maritime Bank',
            'MBB' => 'MBB - Military Bank',
            'SHB' => 'SHB - Saigon Hanoi Bank',
            'EIB' => 'EIB - Eximbank',
            'OCB' => 'OCB - Orient Commercial Bank',
            'NAB' => 'NAB - Nam A Bank',
            'VAB' => 'VAB - Vietnam Asia Bank',
            'NCB' => 'NCB - National Citizen Bank',
            'SEAB' => 'SEAB - Southeast Asia Bank',
            'VIB' => 'VIB - Vietnam International Bank',
            'LPB' => 'LPB - Lien Viet Post Bank',
        ];
    }

    /**
     * Clear cached bank list.
     */
    public function clearCache(): bool
    {
        return Cache::forget(self::CACHE_KEY);
    }

    /**
     * Check if bank list is cached.
     */
    public function isCached(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    /**
     * Get cache expiration time.
     */
    public function getCacheExpiration(): ?\Carbon\Carbon
    {
        if (! $this->isCached()) {
            return null;
        }

        $store = Cache::getStore();
        if (method_exists($store, 'getExpiration')) {
            return $store->getExpiration(self::CACHE_KEY);
        }

        return null;
    }

    /**
     * Refresh bank list cache.
     *
     * @return array<string, string>
     *
     * @throws \Exception
     */
    public function refreshCache(): array
    {
        $this->clearCache();

        return $this->getBankList();
    }

    /**
     * Get bank name by code.
     */
    public function getBankName(string $bankCode): ?string
    {
        $bankList = $this->getBankList();

        return $bankList[$bankCode] ?? null;
    }

    /**
     * Validate bank code.
     */
    public function isValidBankCode(string $bankCode): bool
    {
        $bankList = $this->getBankList();

        return array_key_exists($bankCode, $bankList);
    }

    /**
     * Search banks by name.
     *
     * @return array<string, string>
     */
    public function searchBanks(string $searchTerm): array
    {
        $bankList = $this->getBankList();
        $searchTerm = strtolower(trim($searchTerm));

        if (empty($searchTerm)) {
            return $bankList;
        }

        return array_filter(
            $bankList,
            fn (string $bankName): bool => str_contains(strtolower($bankName), $searchTerm)
        );
    }
}
