# DTMG Posts + Weather Block

A custom WordPress plugin that registers a single Gutenberg block displaying two selected posts plus a cached OpenWeatherMap weather snippet.

The block is dynamic (server-rendered), responsive (container-query layout), and accessible (landmark semantics, description-list weather, decorative-image handling). Weather data is cached for one hour per latitude/longitude. Configuration lives under **Settings → DTMG Posts + Weather**.

## Quick start

This directory is the plugin itself. It does **not** ship with a Docker harness or a local WordPress — drop it into any existing WordPress install at `wp-content/plugins/dtmg-posts-weather-block/` and run:

```bash
composer install   # PSR-4 autoloader + WPCS dev deps
npm install        # @wordpress/scripts (build pipeline only)
npm run build      # compiles block/ → build/
```

Then activate **DTMG Posts + Weather Block** in *Plugins → Installed Plugins*. Requirements: WordPress ≥ 6.5, PHP ≥ 8.1.

For local development with a fresh wp-env-driven WordPress, the test-harness scaffolding (a sibling `package.json` with `wp-env start`, `plugin:install`, `plugin:build` orchestration scripts) lives one level above this directory in the test repo and is **not** part of the plugin's git history. If you have that scaffolding, see its top-level `README.md`; otherwise the three commands above are enough.

## Configuration

After activating the plugin, go to **Settings → DTMG Posts + Weather** and paste your OpenWeatherMap API key. The stored value is masked on every subsequent page load; submitting an empty value preserves the existing key.

## Usage

In the block editor:

1. Insert the **Posts + Weather** block.
2. In the sidebar, pick two posts via the **Posts** panel.
3. Enter a latitude and longitude under **Weather location**.
4. Pick metric or imperial units, and a sunrise/sunset time format, under **Display**.
5. Toggle which weather fields appear under **Weather fields**.

The editor canvas shows a server-side preview that matches the front-end output exactly.

### Units

The OpenWeatherMap request always asks for metric data (°C, m/s); the cache key is `lat|lon` only and never includes a unit. Imperial output (°F, mph) is computed at render time from the cached metric values, so flipping units never costs a second API call and never doubles the cache footprint. Pressure (hPa) and humidity (%) are unit-agnostic and rendered as-is.

### Time format

The **Time format** control affects sunrise and sunset only:

- **Automatic — visitor's locale.** Server renders the site-wide *Settings → General → Time format* as a no-JS fallback, and a small inline script (and a matching `MutationObserver` in the editor) rewrites the `<time>` text using the visitor's `toLocaleTimeString` so a US visitor sees `4:58 AM` while a German visitor sees `04:58`. This is the original behaviour and the default.
- **12-hour (1:30 PM)** and **24-hour (13:30)** are explicit overrides. The PHP partial renders `g:i A` or `H:i` respectively and skips the `data-pwb-localtime` marker entirely, so the client-side localizer never touches the rendered text. No-JS clients see the same output as everyone else.

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
├── index.js / edit.js                # editor entry + Edit component
├── render.php + partials/            # SSR markup
├── style.scss / editor.scss
└── components/{PostPicker,WeatherInspector}.js
```

There is no separate `view.js`: the only front-end JS is a ~10-line localizer for `<time data-pwb-localtime>` that ships inline inside `partials/weather-aside.php`. The editor runs an equivalent `MutationObserver` inside `edit.js` because `<ServerSideRender>` injects markup via `innerHTML`, and `<script>` nodes set that way never execute.

The four-class weather pipeline isolates HTTP (`Client`), normalization (`DTO`), caching (`Service`), and transport (`Controller`). The Service is the only place that knows about transients, so the WP-CLI command and REST controller share one cache layer.

## Typography and assets

Body typography is **inherited from the active theme** — the plugin does not bundle or load any webfont. The Figma reference uses Archivo / Archivo Narrow, but bundling a webfont would override the host site's font choice and pull a third-party CDN into every page that uses the block; sizes, weights, and weight-relative sizing from the design are preserved without imposing a typeface.

The plugin does load **one external stylesheet**: the [Lucide](https://lucide.dev/) icon font (`https://unpkg.com/lucide-static@latest/font/lucide.css`), used for the weather condition icon on the gradient tile and the row glyphs (humidity, pressure, wind, sunrise, sunset). The `@latest` URL is intentional for the test deliverable; pin a version in production. The handle (`dtmg-pwb-lucide`) is enqueued lazily — only on pages where the block actually renders, plus inside the editor iframe — so other pages never hit the CDN.

## Coding standards

```bash
composer phpcs                # WPCS + PHPCompatibility
composer phpcs:fix            # auto-fix what can be auto-fixed
npm run lint:js               # @wordpress/eslint-plugin
npm run lint:css              # @wordpress/stylelint-config
npm run format                # prettier-style format pass for editor JS/SCSS
```

## Releases

```bash
npm run release:zip
```

Produces `dist/dtmg-posts-weather-block-<version>.zip`, ready to upload via *Plugins → Add New → Upload Plugin* on any WordPress site, or to attach to a GitHub Release. The version is read from the `Version:` field of the plugin header file, so update it there only.

Internally the script (`bin/release.sh`):

1. Rebuilds `vendor/` with `composer install --no-dev --optimize-autoloader` (strips PHPCS / PHPCompatibility, builds a classmap autoloader).
2. Reinstalls `node_modules/` deterministically with `npm ci`.
3. Runs `npm run build` to compile `block/` → `build/`.
4. Stages the runtime payload under `dist/dtmg-posts-weather-block/` with explicit excludes (no `node_modules/`, no `block/` source, no `package.json`, no `composer.lock`, no editor configs).
5. Zips with the slug folder as the archive root — required by WordPress's "Upload Plugin" extractor, which uses the inner directory name as the installed plugin's path.
6. Restores `vendor/` to its dev state so PHPCS continues to work locally afterwards.

To attach the zip to a GitHub Release:

```bash
gh release create v1.0.0 dist/dtmg-posts-weather-block-1.0.0.zip \
    --title "v1.0.0" --notes "Initial public release."
```

`phpcs.xml.dist` excludes `WordPress.Files.FileName.NotHyphenatedLowercase` for `src/` because PSR-4 mandates PascalCase filenames; it also excludes the short-array-syntax rule because the plugin targets PHP 8.1+. All other WPCS rules are enforced.

## Limitations / what was deliberately not done

- **No automated tests** — interview deliverable; the focus is architecture, escaping, accessibility, and WPCS conformance.
- **No CI workflow files.**
- **REST, not `admin-ajax.php`.** The task brief asked for "a WordPress AJAX endpoint." This implementation registers a REST route (`GET /wp-json/dtmg/v1/weather`), which is the modern WordPress idiom and satisfies every functional sub-bullet (lat/lon args, public, JSON, 1h cache, key not exposed). A literal reading of the brief would expect `wp_ajax_*` / `wp_ajax_nopriv_*` actions on `admin-ajax.php`; adding a thin admin-ajax wrapper that delegates to `WeatherService::get()` is a small follow-up if a strict interpretation is required.
- **Coordinates are rounded to two decimals** before caching (~1.1 km granularity) to bound key cardinality. The rounded value is also what gets sent to OpenWeatherMap, so two requests within the same ~1 km cell receive byte-identical responses by design. Tweak in `WeatherService::normalize_coords()` if finer precision is needed.
- **Lucide is loaded from a CDN at `@latest`.** Reproducible builds should pin a version; full self-hosting is straightforward (drop the CSS + woff2 files into the plugin and re-target the `wp_register_style` URL).
- **Body typography is theme-defined**, deliberately. The Figma uses Archivo; the plugin does not load any webfont so the block blends with the host theme. Reintroducing the design typeface is a one-line `wp_register_style` if a project requires a literal Figma match.
- **No rate-limiting** on the REST endpoint — suitable for the test task; production deployment would add a per-IP transient guard.

## License

GPL-2.0-or-later.
