# Changelog

All notable changes to `flick` will be documented in this file

## v1.0.1 - 2026-08-27

### What's Changed
- No breaking changes.
- Fixed the `action` config key being ignored by `open()`, `create()` and `openMultipart()`. `new Flick(['action' => '/contact'])` now posts to `/contact`.
- An action passed directly to `open()` still wins over the config value.

## v1.0.0 - 2026-08-25

- First release.
