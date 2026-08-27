# Changelog

All notable changes to `flick` will be documented in this file

## v1.0.1 - 2026-08-27

- Fixed the `action` config key being ignored when rendering the form tag.
  `new Flick(['action' => '/contact'])` is documented as setting the form's
  submission URL, and was already honoured by multistep step links and by
  service config, but `open()`, `create()` and `openMultipart()` all filled the
  action from the request path without consulting it. An action passed directly
  to `open()` still wins, and a form with no configured action still posts back
  to the current path with the query string dropped.

## v1.0.0 - 2026-08-25

- First release.
