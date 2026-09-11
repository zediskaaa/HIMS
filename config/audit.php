<?php

return [
    /*
    | Store the GeoLite2 City database outside version control. Audit location
    | lookup stays local and gracefully returns no location when unavailable.
    */
    'geoip_database' => env('AUDIT_GEOIP_DATABASE_PATH')
        ?: storage_path('app/geoip/GeoLite2-City.mmdb'),
];
