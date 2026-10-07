# One-Time Secret GLPI Plugin CHANGELOG
<!-- changelog-format: 1 -->

## [Unreleased]
Working pre-release: 3.3.1-beta.1

### Features
- A server saved with `https://` or a trailing slash is corrected automatically when upgrading.

### Bugs
- Any user could add a followup to any ticket of any entity by sending a secret.
- Users allowed to see followups could read, add and delete the sent secret links through the API.
- The passphrase of each secret was stored in the database.
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
