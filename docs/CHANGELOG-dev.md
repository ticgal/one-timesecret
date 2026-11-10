# One-Time Secret GLPI Plugin — Dev Changelog
<!-- changelog-format: 2 -->

## [4.0.1-dev] - 2026-11-10
*Working pre-release: 4.0.1-beta.2*

### Bugs
- `src/Config.php`: the GLPI 11→12 migration moved `apikey` protection out of the legacy class without re-adding it to the new `src/` class, so the field was exposed in full through the legacy REST API (`unsetUndisclosedFields()` only hides fields listed in `$undisclosedFields`). Fixed by declaring `public static array $undisclosedFields = ['apikey'];`, overriding `CommonDBTM`'s empty default.
- `src/Config.php`: that override was first declared as `public static $undisclosedFields = ['apikey'];`, without the `array` type hint, while `CommonDBTM` declares it as `public static array $undisclosedFields`. PHP requires an exact type match on an overridden typed static property, so GLPI failed to load the class with `type of GlpiPlugin\Onetimesecret\Config::$undisclosedFields must be an array`. Fixed by adding the `array` type hint. Verified with `php -l` on `Config.php`, `Link.php` and `Secret.php`, and by cross-checking every overridden static property against `CommonDBTM`'s typed declarations (only `$rightname` and `$undisclosedFields` are overridden, both now correctly typed). Not re-executed against a live GLPI instance.
- `src/Link.php`: the 4.0.0 changelog already announced "The passphrase is no longer stored in the database" under Security, but `install()` in the shipped 4.0.0 code (confirmed against the `4.0.0` tag) still created `glpi_plugin_onetimesecret_links` **with** the `passphrase` column — the claimed fix was never actually implemented in the GLPI 12 rewrite. `install()` now creates the table without a `passphrase` column on fresh installs, and drops the column via `Migration::dropField()` + `migrationOneTable()` on upgrades where it still exists.
- `src/Config.php`: `prepareInputForUpdate()` now blanks the stored `apikey` and warns the user via `Session::addMessageAfterRedirect(..., WARNING)` when the `server` field changes without a new key being supplied, so a key is never silently sent to a different, possibly untrusted, host.

### Maintenance
- `src/Secret.php`: `createSecret()` now reads the HTTP response incrementally with `HttpClient::stream()` instead of buffering the whole body with `toArray()`, cancelling the request and logging if it exceeds the new `MAX_RESPONSE_SIZE` (65536 bytes) cap, to bound memory use against an oversized or malicious response.
- `src/Secret.php`: renamed `hoursToSeconds()` to `capLifetime()` to match what it actually does (clamping lifetime in seconds, not converting hours) now that lifetimes are handled in seconds end-to-end (API v2). Call site in `createSecret()` updated; stale comment in `Config.php::install()` corrected to reference `Secret::capLifetime`.
- `src/Secret.php`: `getFollowupContent()` now builds translated strings with `htmlescape()` and restores only the specific `<b>`/`</b>` tags the source string intends via a targeted `str_replace`, instead of leaving translator-controlled text unescaped — avoids both the literal `&amp;`-style corruption bug and a potential XSS vector through the translation catalog.

### Not covered by this pass
- No end-to-end QA was run against a live GLPI 12 instance for the security/quality fixes; verification was limited to `php -l` and static cross-checks. A QA test-request document covering the two security fixes (`apikey` disclosure, plaintext passphrase storage) was produced separately for a clean GLPI 12 environment.
