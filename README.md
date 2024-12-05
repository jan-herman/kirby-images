# Kirby Images

### Roadmap
- [x] $asset support
- [x] fix focus point
- [x] LQIP implementation
- [ ] cache expensive aspect ratio calculations?

#### v2.0
- [ ] calculate object-position in css instead of php?
- [ ] add support for native lazy loadign and other libraries (currently only `lazysizes` is supported)
      - [ ] filters for html markup
- [ ] set legacyAspectRatio to false by default
- [ ] remove `ratioPercentage` file and asset method
- [ ] remove `crop` attribute (replaced by `object_fit`)
- [ ] set object_fit to data-attribute instead of style
- [ ] add width & height attributes to images
- [ ] add slots to image snippet?
- [ ] automatic container & sizes detection from latte AST tree
- [ ] image format options (avif support, option to disable jpeg fallbacks)