# Changelog

All notable changes to `flick` will be documented in this file

## v1.1.1 - 2026-10-10

### Potentially Breaking Changes

- With `persistToSession` on, a saved file is now kept in the session with the form's other values. A later submit with no file chosen passes with the file the form already has.

#### What's Changed

- Fixed a file field in a multistep form losing its file. `multistepFormData()` now returns the saved file and the review page shows its name.
- Going back to a multistep step with a file field no longer asks for the file again. The field shows the name of the file, and choosing another one replaces it.
- `multistepReviewData()` now returns a file field as the name of the file.
- Fixed the multistep review page printing "Array" for a checkbox group.
- Fixed a multistep field keyed with `[]` coming back empty.
- Added `UploadedFile` and `UploadedFiles` to `messages.php`, for the note shown under a file field.

## v1.1.0 - 2026-10-10

### Potentially Breaking Changes

- Without the Upload service, a validated form with a file field now throws when a file is submitted. The form used to post only the file's name.
- With the Upload service installed, an empty `file` field in a validated form returns `null`. It used to return an empty string.

#### What's Changed

- Added `image`, `images`, `pdf` and `pdfs` field types to the string and array syntax, with field methods of the same names.
- `createAndValidate()` and `renderValidated()` now send a file field to the Upload service method of the same name, rules included.
- A form with a file field adds its own multipart `enctype`.
- Fixed upload rules with a comma list, such as `mime:image/jpeg,image/png`, being cut off after the first item in a form string.
- Fixed an optional file field with an upload rule failing validation when left empty.
- Fixed `Photos|files` in a form string throwing a "view file was not found" error.
- Fixed a file field in a form array throwing on every submit when the Upload service is not installed.
- Fixed `'multipart' => false` still adding the `enctype`.
- Fixed `files()` throwing a TypeError when its attributes were a string.

## v1.0.3 - 2026-10-10

### Potentially Breaking Changes

- When the `onSuccess` callback adds an error, the form now shows the error message and stays on the page. It used to show the success message.

#### What's Changed

- Fixed Tailwind fields not showing a red or green edge when invalid or valid.
- Fixed Bulma select and file fields not showing the invalid colour after a failed validation.
- Fixed Flick's built-in stylesheet showing required fields as errors before anything was typed.

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
