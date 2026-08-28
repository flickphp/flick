# Changelog

All notable changes to `flick` will be documented in this file

## v1.0.2 - 2026-08-28

### Potentially Breaking Changes

- Five default English validation messages dropped their `The ... field` wrapper — `accepted`, `confirmed`, `r`, `required` and `requiredWith` now read `name is required` rather than `The name field is required`.

#### What's Changed

- A value you hand a field yourself is now escaped, the same as one coming back from a submitted form — pre-filling a form from stored data can no longer put raw HTML on the page.

## v1.0.1 - 2026-08-27

### What's Changed

- No breaking changes.
- Fixed the `action` config key being ignored by `open()`, `create()` and `openMultipart()`. `new Flick(['action' => '/contact'])` now posts to `/contact`.
- An action passed directly to `open()` still wins over the config value.

## v1.0.0 - 2026-08-25

- First release.
