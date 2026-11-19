# ImageBoss transformer for Imager X Changelog

## [3.1.0] - 2026-11-19

### Added
- Map Craft volume handles to ImageBoss profiles with `volumeProfiles`, including for Control Panel thumbnails and native transforms. Per-transform profiles still take precedence.

### Fixed
- Encode asset, filesystem and volume path segments before signing. Empty cloud subfolders no longer introduce extra slashes.
- Use `cdn` for transforms that change only format or effects. Unsupported stretch/crop-only modes and non-asset sources now produce an actionable Imager error.
- Preserve focal-point precision, handle partial letterbox options, and select quality for the requested output format rather than the source file.
- Handle missing asset dimensions without dividing by zero or inventing a square size. Unknown dimensions are reported as zero.
- Declare the required Imager X dependency (5 or 6) and PHP 8.2 minimum for Craft 5.


## 3.0.1 - 2024-06-16

### Fixed
- Fixed an issue that would throw an error if width or height for source image was `null` (fixes #1)

## 3.0.0 - 2024-02-10

### Added
- Added support for Craft 5


## 2.0.0 - 2022-05-03

### Added
- Added support for Craft 4


## 1.0.0 - 2021-08-22

### Added
- Initial release
