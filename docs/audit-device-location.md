# Audit Device and Location Context

HIMS records a readable device, operating system, browser, and available
location snapshot with each new application audit event. Raw IP and user-agent
values remain available only on the restricted event-detail screen.

On the first authenticated page after sign-in, HIMS asks the browser for
location. The browser's native permission prompt is the consent boundary; HIMS
receives nothing unless the user allows it. Coordinates are rounded to four
decimal places, kept in the signed-in session for up to 15 minutes, and labeled
as device-reported because browser coordinates may be unavailable or spoofed.
Declining location does not block system access or trigger repeated prompts on
each page in the same browser tab.

When browser location is not shared, HIMS can fall back to a local IP lookup. It
does not send staff IP addresses to a third-party geolocation API.

## One-time GeoLite2 City setup

1. Create or sign in to a [MaxMind account](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data/), accept the current GeoLite2 terms, and generate a download license key. Treat that key like a password and never commit it or paste it into tickets or chat.
2. Download the **GeoLite2 City** database in binary `.mmdb` format. The CSV
   package is not used by HIMS.
3. Extract the archive and copy only `GeoLite2-City.mmdb` to:

   ```text
   storage/app/geoip/GeoLite2-City.mmdb
   ```

   The directory and `.mmdb` file are intentionally ignored by Git.
4. If the deployment stores the file elsewhere, add its absolute path to the
   server's `.env`:

   ```dotenv
   AUDIT_GEOIP_DATABASE_PATH=D:/secure-data/geoip/GeoLite2-City.mmdb
   ```

   Use forward slashes in Windows `.env` paths. Leave this variable empty when
   using the default location.
5. Make the file readable by the account running PHP. It does not need write
   permission from the web application.
6. If Laravel configuration is cached, refresh it after changing the path:

   ```shell
   php artisan config:cache
   ```

## Verification

1. Sign in to HIMS using a normal browser and perform an auditable action.
2. Open **Audit Trail**, then open the new event's details.
3. Confirm that **Device** contains the detected device type, operating system,
   and browser.
4. Confirm that **Approximate location** contains rounded coordinates and is
   labeled **Device-reported (browser permission)**. When browser location was
   not shared and local GeoLite2 is configured, city, region, and country may be
   shown instead.

Old audit rows are never modified or backfilled because the trail is
append-only. Device and location snapshots apply to events recorded after this
feature and its schema migration are active.

## Updates

Keep GeoLite2 current under its applicable license. MaxMind recommends its
[GeoIP Update program](https://dev.maxmind.com/geoip/updating-databases/) for
automatic binary-database updates. Configure that updater at the infrastructure
level so it replaces the MMDB file without exposing the account ID or license
key to the application or repository.

Without a readable database—or for localhost, private, reserved, VPN, or proxy
addresses—the event is still recorded and its location is shown as unavailable.
GeoIP results are approximate and must not be treated as a physical-address or
identity verification control.
