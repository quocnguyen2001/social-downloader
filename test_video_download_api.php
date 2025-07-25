<?php

/**
 * Simple test script for the Video Download API
 *
 * This script tests the complete workflow:
 * 1. Trigger download
 * 2. Check status
 * 3. Monitor progress
 */

require_once 'vendor/autoload.php';

use Illuminate\Http\Client\Factory as HttpClient;

// Configuration
$baseUrl = 'http://localhost:8000/api/v1';
$downloadOptionId = '01983def-5e2a-7334-a71a-8eb2fc0390de'; // Replace with actual ID
$apiKey = 'your-api-key'; // Replace with actual API key

$http = new HttpClient;

echo "=== Video Download API Test ===\n\n";

// Test 1: Check initial status
echo "1. Checking initial status...\n";
try {
    $response = $http->post("{$baseUrl}/download/status", [
        'download_option_id' => $downloadOptionId,
    ], [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-API-Key' => $apiKey,
    ]);

    echo 'Status Code: '.$response->status()."\n";
    echo 'Response: '.$response->body()."\n\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n\n";
}

// Test 2: Trigger download
echo "2. Triggering download...\n";
try {
    $response = $http->post("{$baseUrl}/download/trigger", [
        'download_option_id' => $downloadOptionId,
    ], [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-API-Key' => $apiKey,
    ]);

    echo 'Status Code: '.$response->status()."\n";
    echo 'Response: '.$response->body()."\n\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n\n";
}

// Test 3: Monitor status changes
echo "3. Monitoring status changes...\n";
$maxAttempts = 10;
$attempt = 0;

while ($attempt < $maxAttempts) {
    $attempt++;
    echo "Attempt {$attempt}: ";

    try {
        $response = $http->post("{$baseUrl}/download/status", [
            'download_option_id' => $downloadOptionId,
        ], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-Key' => $apiKey,
        ]);

        $data = $response->json();
        $status = $data['data']['status'] ?? 'unknown';
        echo "Status: {$status}\n";

        if ($status === 'downloaded') {
            echo 'Download completed! URL: '.($data['data']['download_url'] ?? 'N/A')."\n";
            break;
        } elseif ($status === 'failed') {
            echo "Download failed!\n";
            break;
        }

        sleep(5); // Wait 5 seconds before next check
    } catch (Exception $e) {
        echo 'Error: '.$e->getMessage()."\n";
        break;
    }
}

echo "\n=== Test Complete ===\n";
