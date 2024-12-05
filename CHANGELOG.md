# Changelog

## [1.3.0] - 2024-10-22
### Changed
- jpg fallback markup
    - remove unnecessary attributes from img tag
- code refactoring

### Fixed
- missing Lazysizes' parent-fit plugin implementation
- missing `data-sizes="auto"` attribute on lazyloaded images
- incorrect `data-aspectratio` attribute on lazyloaded images (container ratio instead of image ratio)
- missing default $options parametr in `$image->thumbWebp()` helper method


## [1.2.0] - 2024-10-14
### Added
- LQIP support


## [1.1.0] - 2024-08-19
### Added
- support for Asset
- $file can be used as first argument without a the 'file:' key in the latte macro
- AspectRatio helper class
- helper methods `srcsetWebp` and `thumbWebp` for assets
- option to use modern `aspect-ratio` CSS property instead of legacy `--aspect-ratio` custom property (legacy is still default. This will change in v2.0)
- $object_fit property (replaces $crop which is deprecated and will be removed in v2.0)

### Changed
- complete code overhaul
    - Picture class
    - image snippet is now written in vanilla php
- Sizes class
    - removed `$file` parameter
    - `$ratio` parameter now expects standard `width / height` ratio not percentage

### Fixed
- object-position calculation
- missing `lazyload` class

### Removed
- kirby-barista dependency


## [1.0.1] - 2024-03-09
### Added
- .gitignore


## [1.0.0] - 2023-12-15
### Added
- Initial release
