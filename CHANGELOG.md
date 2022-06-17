# Compress Changelog

## 4.0.1 - 2022-06-17
### Added
- Added "Default Volume Subdirectory" settings to control where assets are stored.
- Added ability to specify output archive name

### Changed
- Archives created in volumes without public URLs will now be proxied through the server to be fulfilled
- Files are now streamed into archives instead of being copied to temporary files

## 4.0.0 - 2022-06-16
### Change
- Compress now requires Craft 4
- Compress now requires PHP 8.0.1
- Hashing mechanism now accounts for dateUpdated and sorts records. All zip files will need to be regenerated.

## 1.0.3 - 2019-11-22
### Fixed
- Improved compatibility with PHP 7.0.x (again-again)

## 1.0.2 - 2019-11-21
### Fixed
- Improved compatibility with PHP 7.0.x (again)

## 1.0.1 - 2019-11-20
### Fixed
- Improved compatibility with PHP 7.0.x

## 1.0.0 - 2019-11-1
### Added
- Initial release
