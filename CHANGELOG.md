# One-Time Secret GLPI Plugin CHANGELOG
<!-- changelog-format: 2 -->

## [4.0.3]
### Bugs
- A passphrase shorter than 4 characters is rejected with a clear message instead of a generic server error
- The passphrase field is no longer filled in by the browser and requires at least 4 characters
- The API key is re-encrypted when the GLPI encryption key is rotated
- A server saved with `https://` or a trailing slash is corrected automatically when upgrading

## [4.0.2]
### Bugs
- Fixed a stray "1" shown below the Send button in the "Send secret" form of the ticket answer area

## [4.0.1]
### Bugs
- Fixed a configuration page error that prevented the plugin from loading
- The API key is no longer exposed through the legacy REST API
- Fixed the passphrase still being stored in the database, despite this being announced as fixed in 4.0.0
- Secret creation no longer buffers an unbounded response from the One-Time Secret server in memory
- Changing the server without re-entering the API key no longer silently reuses the old key

## 4.0.0
### Features
- GLPI 12 support (GLPI 11 is no longer supported by this branch)
- Classes moved to `src/` with the `GlpiPlugin\Onetimesecret` namespace
- One-Time Secret configuration requires re-authentication, like the GLPI configuration
- Requests to the One-Time Secret server use the GLPI HTTP client. A self-hosted server in a private network must be allowed with `GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS` (see README)

### Security
- Sending a secret checks the plugin right, the ticket access and the right to add followups to the ticket (any user could add followups to any ticket before)
- The passphrase is no longer stored in the database
- Plugin tables can no longer be read through the generic GLPI pages
- The server must be a host name, and the secret link returned by the server is validated
- Redirects from the One-Time Secret server are not followed (they could send the secret to another host)
- Sending a secret as a requester no longer forces the ticket status to "Processing (assigned)"

### Bugfixes
- Secrets and passphrases containing HTML entities (like `&amp;`) were altered
- The followup is written in the requester language
- The One-Time Secret button is only shown to users who can add followups to the ticket
- The plugin no longer creates the `notifications_push` setting in the GLPI configuration

## 3.3.1
### Features
- A server saved with `https://` or a trailing slash is corrected automatically when upgrading.
- New option "Server on an internal network" for self-hosted servers with a private address or an internal name.
### Bugs
- Any user could add a followup to any ticket of any entity by sending a secret.
- Users allowed to see followups could read, add and delete the sent secret links through the API.
- The passphrase of each secret was stored in the database; the ones stored by earlier versions are deleted on upgrade.
- The plugin followed redirects of the One-Time Secret server, which could send the secret to another site.
- The server setting accepted values other than a host name.
- Texts received from the One-Time Secret server or from translations were not escaped.
- Sending a secret as a requester moved the ticket to "Processing (assigned)".
- The API returned the encrypted API key.
- An invalid expiration or a missing passphrase field caused an error.
- Secrets containing text such as `&amp;` were altered before being sent.
- The followup was not written in the requester's language.
- The proxy port configured in GLPI was ignored.
- The One-Time Secret button was shown to users who cannot add followups to the ticket.
- The plugin profile list showed an error page.
- The profile tab could be edited with the configuration right instead of the profile right.
- The "check the configuration" link did not work when GLPI is installed in a subfolder.
- Installing the plugin created an unrelated GLPI setting.
- The passphrase was shown in clear text while typing it.
- A passphrase shorter than 4 characters was rejected by the server with a generic error.
- The release package included the `.github` folder.
- The server setting accepted local and private addresses, and changing it sent the saved API key to the new server.
- The API key was not re-encrypted when the GLPI encryption key is rotated.

## 3.3.0 - 2026-09-09
### Changed
- Changed auth: now uses API username instead of email
### Fixed
- Issue when creating new secrets
- Expiring delays beyond 7 days not working

## 3.2.0 - 2026-06-25
### Features
- Added translations workflow
- Deletes tables upon uninstall #55
- Add new expiration delay #58

## 3.1.0 - 2026-05-26
### Features
- Compatibility with API V2

## 3.0.0 - 2026-01-27
### Features
- GLPI 11 support

## 2.1.3 - 2024-05-16
### Bugfixes
- Fix double encryption of apikey #22315

## 2.1.2 - 2024-04-08
### Bugfixes
- Fix scaped characters in secret and passphrase #21465

## 2.1.1 - 2023-10-18
### Bugfixes
- Fix unused field that generates warning php logs #17969

## 2.1.0 - 2023-10-05
### Features
- Fix form for GLPI 10.0.10  #17725

## 2.0.3 - 2023-07-12
- Fix obsolete and unused functions #16303
- Update styles and form template

## 2.0.2 - 2022-11-25
### Bugfixes
- Decode HTML special chars from secret and passphrase #11964
### Features
- New languages: nl_NL and zh_HK #12404

## 2.0.1 - 2022-07-29
### Bugfixes
- Only create secrets if password field is not empty GLPI 10 #10698

## 2.0.0
### Features
- GLPI 10 compatibility #10086
- it_IT language added #10085
- Proxy management #8311

## 1.1.3 - 2022-07-29
### Bugfixes
- Only create secrets if password field is not empty  #10698

## 1.1.2
### Features
- New lifetime dropdown with same options as the one on the oficial page
### Bugfixes
- Changed some strings about password lifetime

## 1.1.1
### Features

### Bugfixes
- Copyright year replaced

## 1.1.0
### Features
- Added French and Arabic translations
### Bugfixes
- Fixed errors at secrets with special characters

## 1.0.1
### Features
- Open link in new window or tab
### Bugfixes
- Tweak Localazy integration to drop deprecated translations
- Update icon
- Fix plugins.glpi-project.org XML screenshots
- PR to Master
## 1.0.0
### Features
- First public release
- Server selection (Default to official)
- One-Time Secret API setup
- Default expiration time
- Multilanguage message
- FollowUp language changed according to First Requester Language
