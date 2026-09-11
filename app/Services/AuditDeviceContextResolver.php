<?php

namespace App\Services;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AuditDeviceContextResolver
{
    /**
     * @return array{device_type: ?string, device_name: ?string, operating_system: ?string, browser: ?string}
     */
    public function resolve(Request $request): array
    {
        $empty = [
            'device_type' => null,
            'device_name' => null,
            'operating_system' => null,
            'browser' => null,
        ];
        $userAgent = trim((string) $request->userAgent());

        if ($userAgent === '') {
            return $empty;
        }

        try {
            $detector = new DeviceDetector(
                $userAgent,
                ClientHints::factory($request->server->all()),
            );
            $detector->parse();

            if ($detector->isBot()) {
                $bot = $detector->getBot();

                return [
                    ...$empty,
                    'device_type' => 'Bot',
                    'device_name' => $this->clean($bot['name'] ?? null),
                ];
            }

            $brandAndModel = implode(' ', array_filter([
                $this->clean($detector->getBrandName()),
                $this->clean($detector->getModel()),
            ]));

            return [
                'device_type' => $this->headline($detector->getDeviceName()),
                'device_name' => $brandAndModel !== '' ? $brandAndModel : null,
                'operating_system' => $this->nameAndVersion(
                    $detector->getOs('name'),
                    $detector->getOs('version'),
                ),
                'browser' => $this->nameAndVersion(
                    $detector->getClient('name'),
                    $detector->getClient('version'),
                ),
            ];
        } catch (Throwable) {
            // Context enrichment must never prevent the authoritative event.
            return $empty;
        }
    }

    private function nameAndVersion(mixed $name, mixed $version): ?string
    {
        $value = implode(' ', array_filter([
            $this->clean($name),
            $this->clean($version),
        ]));

        return $value !== '' ? $value : null;
    }

    private function headline(mixed $value): ?string
    {
        $value = $this->clean($value);

        return $value === null ? null : Str::headline($value);
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' || $value === 'UNK' ? null : Str::limit($value, 120, '...');
    }
}
