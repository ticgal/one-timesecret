# One-Time Secret GLPI Plugin — Dev changelog
<!-- changelog-format: 1 -->

## 3.3.1-beta.1 - 2026-10-07
Security backport for the GLPI 11 branch (`bugfix/securityIssue`, from `develop` 3.3.0), plus the hardening already done
on the GLPI 12 branch, ported to the `inc/` classes.

### Security
- `front/link.form.php` checked no right: any authenticated user (self-service included, any entity) could add a
  followup to any not-solved ticket, and make the plugin call the One-Time Secret API with the organisation's
  credentials. Now: `Session::checkRight(plugin_onetimesecret_send, READ)`; `tickets_id` cast to int → 404 if unknown;
  `ITILFollowup::can(-1, CREATE, {Ticket, id})` (core checks `Ticket::can(id, READ)` + `canAddFollowups()`) and
  `PluginOnetimesecretLink::canSendTo()` (right, not solved, `canAddFollowups()`) → 403; `add`/`tickets_id` → 400.
- `PluginOnetimesecretLink` (`$rightname = 'followup'`, no `entities_id`) was served by core to anyone with `followup`
  READ, self-service included: legacy REST API (GET returned `secret` + `passphrase`, POST created rows, DELETE purged
  them) and `LegacyItemtypeRouteListener` → `GenericFormController` (`/plugins/onetimesecret/front/Link.form.php`, any
  case not matching a real file: add/purge). `canView/canCreate/canUpdate/canDelete/canPurge()` now return `false`;
  `CommonDBTM::add()` checks no right, so `PluginOnetimesecretSecret::addFollowup()` still writes the row, and core
  `answer.html.twig` calls `showForm()` directly, so the timeline form is unaffected.
- The passphrase is no longer written to `glpi_plugin_onetimesecret_links.passphrase`. Rows written by ≤ 3.3.0 are
  kept as they are (decision: no purge on upgrade).
- `server` was concatenated into `https://<server>/api/v2/...` unvalidated (path/host injection with the Basic
  credentials) and curl followed up to 10 redirects with `CUSTOMREQUEST POST` (a 307 to another host re-posted the
  plaintext secret). Now `PluginOnetimesecretConfig::isValidServer()` (host name + optional port) on add/update (trimmed,
  error message) and again in `createSecret()`; `CURLOPT_PROTOCOLS = HTTPS`, `CURLOPT_FOLLOWLOCATION = false`,
  `CURLOPT_MAXFILESIZE` 1 MB (+ length check), connect timeout 10 s, HTTP 200 required.
- The server-returned identifier went raw into the followup `href` (`<script>`/attribute break-out stored, neutralised
  only by core `safe_html`): now `^[a-z0-9]+$`i or the send fails. Every translated string and the URL go through
  `htmlescape()`; the `<b>` of "A secret link <b>only works once</b>…" is restored after escaping (no msgid change).
- Flash messages are rendered with `|raw` by core (`messages_after_redirect_toasts.html.twig`): plugin strings are now
  `htmlescape()`d, and the configuration link uses `$CFG_GLPI['root_doc']` (translations are external input).
- `addFollowup()` forced `_status = ASSIGNED` when the sender was a requester, applied by core `ParentStatus` through
  `$parentitem->update()`, bypassing the reopen rules: removed.
- `PluginOnetimesecretConfig::$undisclosedFields = ['apikey']`: the GLPIKey ciphertext was returned by the REST API.

### Fixed
- `lifetime` is checked against `PluginOnetimesecretConfig::getLifetimes()` (`isValidLifetime()`): negative values
  reached the API and `abc` gave a 500 (`TypeError` in `hoursToSeconds(int)`) → 400. Config form ignores other values.
- Missing `passphrase` field logged `Undefined array key`: `?? ''`.
- `html_entity_decode()` was applied twice (front + `createSecret()`), altering secrets with literal entities; GLPI 11
  stores input raw, so it is removed.
- The followup content was built before switching to the requester language: now built after the switch, inside a
  `try/finally` that restores the session language. Requester lookup uses `CommonITILActor::REQUESTER`.
- `CURLOPT_PROXYPORT` was never set (curl default 1080 instead of the GLPI proxy port).
- `timelineAction()` read `glpi_profilerights` directly and ignored followup rights: now `canSendTo()`, so the button
  is hidden from users who cannot add followups (they got a 403 on send).
- `PluginOnetimesecretProfile` extended `Profile` (a `CommonDBTM` without table): generic `front/profile.php` and
  `Profile.form.php` crashed with `glpi_plugin_onetimesecret_profiles doesn't exist`. Now extends `CommonGLPI`,
  `canView()` false, `RIGHT_SEND` constant; the tab decides editability with `profile` UPDATE (was `config`), like the
  core form it posts to. `install/uninstall(Migration)` create/delete the right and grant it to the active profile.
- `PluginOnetimesecretSecret` no longer extends `CommonDBTM` (no table).
- Upgrade: a `server` saved with `https://` or a trailing `/` (which worked by concatenation) is normalized, otherwise
  the new validation would stop sending.
- `hook.php` no longer creates `core.notifications_push` (copied from another plugin; existing values are not
  deleted); the no-op `deleteConfigurationValues()` calls are removed.
- `templates/link.html.twig`: unused hidden fields and `enctype` removed, `data-submit-once`, `autocomplete="off"` on
  the passphrase, TwigCS spacing; `front/config.form.php` final newline.
- `localazy.yml`: `actions/checkout@v4`; `github.event.pusher.*` passed through `env` instead of being interpolated into
  the shell script.
- README: server as a bare host name; the link is a public followup, use a passphrase for sensitive secrets.

### Pending
- New msgid "Invalid server: use a host name like eu.onetimesecret.com" reaches the `.pot` through the Localazy
  workflow on `develop`.
- es_ES translation of "A secret link <b>only works once</b>…" uses `</b>` instead of `<b>`: fix it in Localazy.
