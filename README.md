# DTMG Posts + Weather Block

A custom WordPress plugin that registers a single Gutenberg block displaying two selected posts plus a cached OpenWeatherMap weather snippet.

The block is dynamic (server-rendered), responsive (container-query layout), and accessible (landmark semantics, description-list weather, decorative-image handling). Weather data is cached for one hour per latitude/longitude. Configuration lives under **Settings → DTMG Posts + Weather**.

## Quick start

```bash
composer install
npm install
npm run env:start    # boots WordPress in Docker (~2 min on first run)
npm run build
```

The site is served at http://localhost:8888 (admin: `admin` / `password`).

## Configuration

After activating the plugin, go to **Settings → DTMG Posts + Weather** and paste your OpenWeatherMap API key. The stored value is masked on every subsequent page load; submitting an empty value preserves the existing key.

## Usage

In the block editor:

1. Insert the **Posts + Weather** block.
2. In the sidebar, pick two posts via the **Posts** panel.
3. Enter a latitude and longitude under **Weather location**.
4. Toggle which weather fields appear under **Weather fields**.

The editor canvas shows a server-side preview that matches the front-end output exactly.

## REST endpoint

`GET /wp-json/dtmg/v1/weather?lat=<lat>&lon=<lon>` returns normalized weather JSON. The endpoint is public and never exposes the API key.

## WP-CLI

```bash
wp dtmg-weather flush                          # flush every cached entry
wp dtmg-weather flush --lat=50.45 --lon=30.52  # flush a single entry
```

## Architecture

```
src/                                # PHP, PSR-4: DTMG\PostsWeatherBlock\
├── Plugin.php                      # bootstrap singleton; registers all hooks
├── Block/PostsWeatherBlock.php     # registers block from block.json metadata
├── Weather/
│   ├── WeatherClient.php           # HTTP only (wp_remote_get)
│   ├── WeatherService.php          # transient cache + key index
│   └── WeatherDTO.php              # readonly value object
├── REST/WeatherController.php      # GET /wp-json/dtmg/v1/weather
├── Settings/SettingsPage.php       # admin Settings API page
├── Admin/AdminNotices.php          # missing-key admin notice
└── CLI/FlushCacheCommand.php       # wp dtmg-weather flush
block/                              # editor + view sources
├── block.json
├── index.js / edit.js / view.js
├── render.php + partials/
├── style.scss / editor.scss
└── components/{PostPicker,WeatherInspector}.js
```

The four-class weather pipeline isolates HTTP (`Client`), normalization (`DTO`), caching (`Service`), and transport (`Controller`). The Service is the only place that knows about transients, so the WP-CLI command and REST controller share one cache layer.

## Coding standards

```bash
composer phpcs                # WPCS + PHPCompatibility
composer phpcs:fix            # auto-fix what can be auto-fixed
npm run lint:js               # @wordpress/eslint-plugin
npm run lint:css              # @wordpress/stylelint-config
npm run format                # prettier-style format pass for editor JS/SCSS
```

`phpcs.xml.dist` excludes `WordPress.Files.FileName.NotHyphenatedLowercase` for `src/` because PSR-4 mandates PascalCase filenames; it also excludes the short-array-syntax rule because the plugin targets PHP 8.1+. All other WPCS rules are enforced.

## Limitations / what was deliberately not done

- **No automated tests** — interview deliverable; the focus is architecture, escaping, accessibility, and WPCS conformance.
- **No CI workflow files**.
- **Coordinates are rounded to two decimals** before caching (~1.1 km granularity) to bound key cardinality; tweak in `WeatherService::normalize_coords()` if finer precision is needed.
- **Weather icons are not rendered** — only condition labels. Adding an inline SVG set is a small, isolated change.
- **No rate-limiting** on the REST endpoint — suitable for the test task; production deployment would add a per-IP transient guard.

## License

GPL-2.0-or-later.
