<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use Throwable;

class AuditGeoIpLocator
{
    /**
     * @return array{location_city: ?string, location_region: ?string, location_country: ?string, location_country_code: ?string, location_source: ?string, location_latitude: null, location_longitude: null, location_accuracy_meters: null}
     */
    public function locate(?string $ipAddress): array
    {
        $empty = [
            'location_city' => null,
            'location_region' => null,
            'location_country' => null,
            'location_country_code' => null,
            'location_source' => null,
            'location_latitude' => null,
            'location_longitude' => null,
            'location_accuracy_meters' => null,
        ];

        if (! $this->isPublicIp($ipAddress)) {
            return $empty;
        }

        $database = config('audit.geoip_database');

        if (! is_string($database) || $database === '' || ! is_readable($database)) {
            return $empty;
        }

        try {
            $reader = new Reader($database);

            try {
                $record = $reader->city($ipAddress);

                $location = [
                    'location_city' => $this->limit($record->city->name),
                    'location_region' => $this->limit($record->mostSpecificSubdivision->name),
                    'location_country' => $this->limit($record->country->name),
                    'location_country_code' => $this->countryCode($record->country->isoCode),
                    'location_source' => null,
                    'location_latitude' => null,
                    'location_longitude' => null,
                    'location_accuracy_meters' => null,
                ];

                $location['location_source'] = array_filter(array_intersect_key(
                    $location,
                    array_flip(['location_city', 'location_region', 'location_country', 'location_country_code']),
                )) === [] ? null : 'ip';

                return $location;
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            // Missing IPs and invalid/outdated databases should not block audits.
            return $empty;
        }
    }

    private function isPublicIp(?string $ipAddress): bool
    {
        return is_string($ipAddress)
            && filter_var(
                $ipAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;
    }

    private function limit(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function countryCode(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{2}$/', $value) === 1 ? $value : null;
    }
}
