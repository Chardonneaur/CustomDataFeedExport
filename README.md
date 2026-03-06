# CustomDataFeedExport — Matomo Plugin

> **Community plugin — not affiliated with or endorsed by InnoCraft or the Matomo core team.**

> **Warning**
>
> This plugin is experimental and was coded using [Claude Code](https://claude.ai).
> It is provided without any warranty regarding quality, stability, or performance.
> This is a community project and is not officially supported by Matomo.

Export Matomo visit-log data to CSV with fully customisable dimension selection, column ordering, and row-level filtering. Feeds are saved configurations that can be re-exported at any time through the Matomo UI or a token-authenticated public endpoint.

---

## Features

- **50+ dimensions** — visitor, referrer, device, browser, location, e-commerce, events, site search, content tracking, and per-action details
- **Ordered columns** — drag-and-drop or arrow-key reordering; the CSV columns appear exactly as configured
- **Advanced filters** — 10 operators (equals, contains, regex, is empty, …) with AND logic; filter values validated and re-checked at export time
- **Token-authenticated public endpoint** — `datafeed.php` at the Matomo root lets external tools pull CSVs without a Matomo session
- **Action-level export** — when action dimensions are selected, one CSV row is emitted per action instead of per visit
- **Excel-safe output** — UTF-8 BOM, RFC 4180 quoting, formula-injection prefix on cells starting with `=`, `+`, `-`, `@`, `|`
- **Per-feed access control** — tokens are only exposed to the feed owner or a site admin

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Matomo | ≥ 5.0.0, < 6.0.0 |
| PHP | ≥ 8.1 |
| MySQL / MariaDB | ≥ 5.7 |

---

## Installation

### From GitHub

```bash
cd /path/to/matomo/plugins
git clone https://github.com/Chardonneaur/CustomDataFeedExport.git
```

Then activate the plugin:

- **UI**: Administration → Plugins → find *CustomDataFeedExport* → Activate
- **CLI**: `php console plugin:activate CustomDataFeedExport`

The plugin creates a `matomo_datafeed` table automatically on first activation.

### Public endpoint (optional)

Copy `datafeed.php` from the repository root into the Matomo root directory:

```bash
cp datafeed.php /path/to/matomo/
```

This file provides token-authenticated CSV access without a Matomo login — useful for cron jobs, BI tools, or spreadsheet integrations.

---

## Usage

### Creating a feed

1. Go to **Administration → Data Feed Export**
2. Click **+ Create New Feed**
3. Enter a name and optional description
4. Check the dimensions you want — numbered badges show the export order
5. Use the **Selected Fields** panel to drag-and-drop or use ▲/▼ arrows to reorder columns
6. Optionally add filters (see below)
7. Click **Save Feed**

### Exporting

1. Click **Export CSV** on any saved feed
2. Choose period (Day / Week / Month / Year / Range) and date
3. Set the maximum number of rows (default 1 000, max 100 000)
4. Click **Download CSV**

### Token-based export (datafeed.php)

```
GET /datafeed.php?token=<feed-token>&period=day&date=today&filter_limit=1000
```

| Parameter | Default | Notes |
|-----------|---------|-------|
| `token` | — | Required. Found in the feed list (owners and admins only) |
| `period` | `day` | `day`, `week`, `month`, `year`, `range` |
| `date` | `today` | `today`, `yesterday`, `YYYY-MM-DD`, or `YYYY-MM-DD,YYYY-MM-DD` for range |
| `filter_limit` | `1000` | 1 – 100 000 |

---

## Available Dimensions

### Visit

| Key | Label |
|-----|-------|
| `idVisit` | Visit ID |
| `visitorId` | Visitor ID |
| `visitIp` | IP Address |
| `userId` | User ID |
| `serverDate` | Server Date |
| `serverTimePretty` | Server Time |
| `visitDuration` | Visit Duration (seconds) |
| `actions` | Number of Actions |
| `referrerType` | Referrer Type |
| `referrerName` | Referrer Name |
| `referrerKeyword` | Referrer Keyword |
| `referrerUrl` | Referrer URL |
| `country` | Country |
| `region` | Region |
| `city` | City |
| `deviceType` | Device Type |
| `browser` | Browser |
| `operatingSystem` | Operating System |
| `entryPageUrl` | Entry Page URL |
| `exitPageUrl` | Exit Page URL |
| … | 30+ more — see UI for the full list |

### Per-action (one row per action)

`actionType`, `actionUrl`, `actionTitle`, `actionTimestamp`, `actionTimeSpent`, `actionPosition`, `eventCategory`, `eventAction`, `eventName`, `eventValue`, `searchKeyword`, `searchCategory`, `downloadUrl`, `outlinkUrl`, `contentName`, `contentPiece`, `contentTarget`, `contentInteraction`

---

## Filters

| Operator | Behaviour |
|----------|-----------|
| `equals` | Exact string match |
| `not_equals` | Not exact match |
| `contains` | Case-insensitive substring |
| `not_contains` | Does not contain substring |
| `starts_with` | Prefix match (case-insensitive) |
| `ends_with` | Suffix match (case-insensitive) |
| `regex` | PCRE regex match (`~pattern~i`) |
| `not_regex` | PCRE regex non-match |
| `is_empty` | Value is empty string |
| `is_not_empty` | Value is non-empty |

Multiple filters are combined with **AND** logic. Regex patterns are validated at save time and re-validated at export time: lookaheads, back-references, and catastrophic-backtracking patterns are rejected.

---

## API Reference

All methods are under the `CustomDataFeedExport` module and require a valid Matomo authentication token or session.

| Method | Access | Description |
|--------|--------|-------------|
| `getAvailableDimensionsList` | view | List all supported dimensions |
| `getFeeds(idSite)` | view | List all feeds for a site |
| `getFeed(idFeed)` | view | Get a single feed (token hidden from non-owners) |
| `createFeed(idSite, name, dimensions, description, filters)` | write | Create a feed |
| `updateFeed(idFeed, name, dimensions, description, filters)` | write (owner or admin) | Update a feed |
| `deleteFeed(idFeed)` | write (owner or admin) | Soft-delete a feed |
| `deleteAllFeeds(idSite)` | write | Delete all feeds for a site owned by the current user (admin deletes all) |
| `exportFeed(idFeed, period, date, segment, filter_limit)` | view | Export as CSV string |

---

## Database Schema

Table: `matomo_datafeed`

| Column | Type | Notes |
|--------|------|-------|
| `idfeed` | INT AUTO_INCREMENT | Primary key |
| `idsite` | INT | Matomo site ID |
| `login` | VARCHAR(100) | Feed creator |
| `token` | VARCHAR(64) UNIQUE | 256-bit random hex token |
| `name` | VARCHAR(255) | Feed name |
| `description` | VARCHAR(500) | Optional description |
| `dimensions` | TEXT | JSON array of dimension keys |
| `filters` | TEXT | JSON array of filter objects |
| `ts_created` | TIMESTAMP | Creation time |
| `deleted` | TINYINT | Soft-delete flag (0/1) |

---

## Security

- All SQL queries use parameterised placeholders
- Dimensions and filter operators are validated against strict whitelists at both save time and export time
- Regex filter values are rejected if they contain unescaped delimiters, lookaheads, back-references, or known catastrophic-backtracking patterns
- Feed tokens (256-bit, `random_bytes`) are only visible to the feed owner or a site admin
- CSV output sanitises formula-injection characters; filenames are stripped to `[a-zA-Z0-9_-]`
- The public endpoint (`datafeed.php`) enforces a 1-second delay on invalid tokens to slow brute-force attempts

---

## License

GPL v3 or later — see [LICENSE](LICENSE).

---

## Support & Contributions

- **Issues**: [GitHub Issues](https://github.com/Chardonneaur/CustomDataFeedExport/issues)
- Pull requests are welcome. Please open an issue first to discuss significant changes.

> This is a community plugin. For Matomo core support visit [forum.matomo.org](https://forum.matomo.org).
