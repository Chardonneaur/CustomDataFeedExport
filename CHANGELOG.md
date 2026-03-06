# Changelog

All notable changes to CustomDataFeedExport are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/); versioning follows [Semantic Versioning](https://semver.org/).

## [1.3.0] - 2026-03-06

### Changed
- Plugin renamed from `DataFeedExport` to `CustomDataFeedExport`
- Updated all namespaces, class names, API module identifiers, and view paths accordingly

### Security
- Fixed XSS: `data-name` HTML attribute was built with jQuery `.text().html()` which does not encode `"`, allowing attribute breakout; replaced with a dedicated `escHtml()` that explicitly encodes `&`, `<`, `>`, and `"`
- Fixed XSS: same gap in the `escHtml` helper used for filter value `<input>` attributes; rewritten with explicit `.replace()` chain
- Fixed regex delimiter bypass: `isSafeRegexPattern` used a one-level negative lookbehind to detect unescaped `/`, which was fooled by `\\/` (escaped backslash + slash); switched to `~` as the PCRE delimiter throughout and updated the validator accordingly

## [1.2.0] - 2025-06-01

### Added
- Token-authenticated public endpoint (`datafeed.php`) for external tool integration
- Per-feed token (256-bit, `random_bytes`); tokens visible only to feed owner or site admin
- Runtime re-validation of stored dimensions and filters (defence-in-depth)
- Brute-force delay on invalid token in public endpoint

### Changed
- Feed tokens stored with UNIQUE constraint; backfilled for existing rows on upgrade

## [1.1.0] - 2025-01-26

### Added
- Dimension selection order tracking with visual numbered badges
- Reorder panel with drag-and-drop and ▲/▼ arrows
- Dimension search bar
- Auto-fill feed name button
- Advanced filter system with 10 operators (equals, contains, regex, is empty, …)
- Searchable dropdown for filter dimension selection
- Multiple filters with AND logic
- Delete All Feeds button (double confirmation)
- `filters` column in the database table

### Fixed
- CSV download now sends correct HTTP headers
- Entry page and exit page data correctly extracted from visit action details
- Output buffering resolved for clean file downloads

### Changed
- Controller extends `PluginController` for reliable download handling
- UTF-8 BOM added to CSV output for Excel compatibility

## [1.0.0] - 2025-01-14

### Added
- Initial release
- Create and manage data feed configurations
- Select from 50+ available dimensions
- Export visit log data as CSV
- Date range and period selection
- Row limit configuration
