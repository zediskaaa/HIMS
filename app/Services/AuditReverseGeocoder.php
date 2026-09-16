<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AuditReverseGeocoder
{
    /**
     * Resolve a friendly place name from geographic coordinates.
     * Uses offline local bounding boxes first for fast zero-latency resolution
     * across Philippine cities, and caches online reverse geocoding when available.
     */
    public static function resolve(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        // Format to 4 decimal places for consistent caching
        $lat = round($latitude, 4);
        $lng = round($longitude, 4);

        $cacheKey = "audit_geo_place_{$lat}_{$lng}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($lat, $lng): string {
            // If running unit tests, rely strictly on local Philippine offline lookup
            if (app()->runningUnitTests()) {
                return self::offlinePhilippineLookup($lat, $lng) ?? "{$lat}, {$lng}";
            }

            // Attempt online reverse geocoding with strict 1.5-second timeout
            try {
                $response = Http::timeout(1.5)
                    ->withHeaders(['User-Agent' => 'HIMS-Hospital-Audit/1.0'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'json',
                        'lat' => $lat,
                        'lon' => $lng,
                        'zoom' => 14,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $address = $data['address'] ?? [];

                    $parts = array_values(array_unique(array_filter([
                        $address['quarter'] ?? $address['suburb'] ?? $address['neighbourhood'] ?? $address['city_district'] ?? null,
                        $address['city'] ?? $address['town'] ?? $address['municipality'] ?? null,
                        $address['region'] ?? $address['state'] ?? $address['province'] ?? null,
                        $address['country'] ?? null,
                    ])));

                    if (! empty($parts)) {
                        return implode(', ', $parts);
                    }
                }
            } catch (Throwable) {
                // Ignore network errors and fall through to offline lookup
            }

            // Secondary attempt: BigDataCloud client reverse geocoding
            try {
                $response = Http::timeout(1.5)->get('https://api.bigdatacloud.net/data/reverse-geocode-client', [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'localityLanguage' => 'en',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $parts = array_values(array_unique(array_filter([
                        $data['locality'] ?? $data['city'] ?? null,
                        $data['principalSubdivision'] ?? null,
                        $data['countryName'] ?? null,
                    ])));

                    if (! empty($parts)) {
                        return implode(', ', $parts);
                    }
                }
            } catch (Throwable) {
                // Ignore network errors and fall through to offline lookup
            }

            // Offline lookup for Philippine coordinates
            return self::offlinePhilippineLookup($lat, $lng) ?? "{$lat}, {$lng}";
        });
    }

    /**
     * Fast offline bounding box lookup for Philippine cities and municipalities.
     */
    public static function offlinePhilippineLookup(float $lat, float $lng): ?string
    {
        // Metro Manila (NCR) Cities
        if ($lat >= 14.5800 && $lat <= 14.7700 && $lng >= 121.0000 && $lng <= 121.1400) {
            return 'Quezon City, Metro Manila, Philippines';
        }

        if ($lat >= 14.5600 && $lat <= 14.6250 && $lng >= 120.9600 && $lng <= 121.0100) {
            return 'City of Manila, Metro Manila, Philippines';
        }

        if ($lat >= 14.5300 && $lat <= 14.5750 && $lng >= 121.0050 && $lng <= 121.0650) {
            return 'Makati City, Metro Manila, Philippines';
        }

        if ($lat >= 14.4950 && $lat <= 14.5550 && $lng >= 121.0300 && $lng <= 121.0850) {
            return 'Taguig City, Metro Manila, Philippines';
        }

        if ($lat >= 14.5500 && $lat <= 14.6000 && $lng >= 121.0650 && $lng <= 121.1250) {
            return 'Pasig City, Metro Manila, Philippines';
        }

        if ($lat >= 14.5700 && $lat <= 14.6000 && $lng >= 121.0200 && $lng <= 121.0550) {
            return 'Mandaluyong City, Metro Manila, Philippines';
        }

        if ($lat >= 14.5950 && $lat <= 14.6150 && $lng >= 121.0200 && $lng <= 121.0450) {
            return 'San Juan City, Metro Manila, Philippines';
        }

        if (($lat >= 14.6400 && $lat <= 14.6650 && $lng >= 120.9650 && $lng <= 121.0050)
            || ($lat >= 14.7200 && $lat <= 14.7850 && $lng >= 121.0100 && $lng <= 121.0700)) {
            return 'Caloocan City, Metro Manila, Philippines';
        }

        if ($lat >= 14.6250 && $lat <= 14.6750 && $lng >= 121.0900 && $lng <= 121.1400) {
            return 'Marikina City, Metro Manila, Philippines';
        }

        if ($lat >= 14.5150 && $lat <= 14.5550 && $lng >= 120.9800 && $lng <= 121.0200) {
            return 'Pasay City, Metro Manila, Philippines';
        }

        if ($lat >= 14.4600 && $lat <= 14.5200 && $lng >= 120.9800 && $lng <= 121.0450) {
            return 'Parañaque City, Metro Manila, Philippines';
        }

        if ($lat >= 14.4200 && $lat <= 14.4750 && $lng >= 120.9700 && $lng <= 121.0250) {
            return 'Las Piñas City, Metro Manila, Philippines';
        }

        if ($lat >= 14.3700 && $lat <= 14.4450 && $lng >= 121.0200 && $lng <= 121.0650) {
            return 'Muntinlupa City, Metro Manila, Philippines';
        }

        if ($lat >= 14.6700 && $lat <= 14.7350 && $lng >= 120.9600 && $lng <= 121.0150) {
            return 'Valenzuela City, Metro Manila, Philippines';
        }

        if ($lat >= 14.6500 && $lat <= 14.6800 && $lng >= 120.9400 && $lng <= 120.9750) {
            return 'Malabon City, Metro Manila, Philippines';
        }

        if ($lat >= 14.6400 && $lat <= 14.6750 && $lng >= 120.9300 && $lng <= 120.9600) {
            return 'Navotas City, Metro Manila, Philippines';
        }

        // Metro Manila general boundary
        if ($lat >= 14.3500 && $lat <= 14.8000 && $lng >= 120.9000 && $lng <= 121.1600) {
            return 'Metro Manila, Philippines';
        }

        // Surrounding provinces
        if ($lat >= 14.5700 && $lat <= 14.6500 && $lng >= 121.1400 && $lng <= 121.2200) {
            return 'Antipolo, Rizal, Philippines';
        }

        if ($lat >= 14.2500 && $lat <= 14.5000 && $lng >= 120.8500 && $lng <= 121.0000) {
            return 'Cavite, Philippines';
        }

        if ($lat >= 14.1500 && $lat <= 14.4000 && $lng >= 121.0300 && $lng <= 121.4500) {
            return 'Laguna, Philippines';
        }

        if ($lat >= 14.7000 && $lat <= 15.1000 && $lng >= 120.7000 && $lng <= 121.1500) {
            return 'Bulacan, Philippines';
        }

        // Major Philippine regional cities
        if ($lat >= 10.2500 && $lat <= 10.4000 && $lng >= 123.8200 && $lng <= 124.0000) {
            return 'Cebu City, Cebu, Philippines';
        }

        if ($lat >= 6.9500 && $lat <= 7.2000 && $lng >= 125.4500 && $lng <= 125.7000) {
            return 'Davao City, Davao del Sur, Philippines';
        }

        if ($lat >= 16.3500 && $lat <= 16.4500 && $lng >= 120.5500 && $lng <= 120.6500) {
            return 'Baguio City, Benguet, Philippines';
        }

        if ($lat >= 10.6500 && $lat <= 10.7500 && $lng >= 122.5000 && $lng <= 122.6000) {
            return 'Iloilo City, Iloilo, Philippines';
        }

        if ($lat >= 10.6200 && $lat <= 10.7200 && $lng >= 122.9200 && $lng <= 123.0000) {
            return 'Bacolod City, Negros Occidental, Philippines';
        }

        if ($lat >= 8.4500 && $lat <= 8.5200 && $lng >= 124.6000 && $lng <= 124.7000) {
            return 'Cagayan de Oro, Misamis Oriental, Philippines';
        }

        // General Philippines bounding box
        if ($lat >= 4.5000 && $lat <= 21.5000 && $lng >= 116.5000 && $lng <= 127.0000) {
            return 'Philippines';
        }

        return null;
    }
}
