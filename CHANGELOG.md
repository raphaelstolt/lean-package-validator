# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to
[Semantic Versioning](http://semver.org/).

## [Unreleased]

### Added

- Further PHP preset expansion.

## [v4.1.0] - 2024-11-11

### Added

- New `--keep-readme` and `--keep-glob-pattern` options. Closes [#47](https://github.com/raphaelstolt/lean-package-validator/issues/47).

## [v4.0.5] - 2024-11-06

### Added

- Further PHP preset expansion.

## [v4.0.4] - 2024-09-27

### Added

- Further PHP preset expansion.

### Removed

- Removed laminas/laminas-stdlib dependency.  

## [v4.0.3] - 2024-07-10

### Added

- Attested dist builds.

## [v4.0.2] - 2024-05-13

### Added

- Further PHP preset expansion.

### Fixed

- Ignore global gitignore patterns.

## [v4.0.1] - 2024-05-07

### Added

- Further PHP preset expansion.

## [v4.0.0] - 2024-04-26

### Fixed

- Updated the symfony/console dependency.
- Further PHP preset expansion.

## [v3.3.2] - 2023-12-04

### Fixed

- Expands the PHP preset. 

## [v3.3.1] - 2023-12-01

### Fixed

- The header is written on existent .gitattributes file. Closes [#44](https://github.com/raphaelstolt/lean-package-validator/issues/44).

## [v3.3.0] - 2023-11-28

### Removed

- Removed support for PHP `8.0`.

## [v3.2.0] - 2023-11-08

### Added

- New `--preset` option. Closes [#43](https://github.com/raphaelstolt/lean-package-validator/issues/43).

- New `--report-stale-export-ignores` option. Closes [#41](https://github.com/raphaelstolt/lean-package-validator/issues/41).

## [v3.1.1] - 2023-10-13

### Fixed

- Header in generated or modified `.gitattributes` file is set as a comment.

## [v3.1.0] - 2023-10-10

### Added

- Global .gitignore'd files are excluded from validation. Closes [#36](https://github.com/raphaelstolt/lean-package-validator/issues/36).
- Added `--diff` option to show differences between expected and actual .gitattributes content. Closes [#39](https://github.com/raphaelstolt/lean-package-validator/issues/39).
- Added verbose output. Closes [#37](https://github.com/raphaelstolt/lean-package-validator/issues/37).

### Fixed

- Empty glob pattern is catched as invalid. Closes [#38](https://github.com/raphaelstolt/lean-package-validator/issues/38).

## [v3.0.1] - 2023-09-26

### Removed

- Removed support for PHP `7.4`.

### Added

- Header in generated or modified `.gitattributes` file.

## [v3.0.0] - 2022-04-28

### Removed

- Removed support for PHP `7.3` and `7.2`.

### Added

- Introduced GitHub Actions.

## [v2.1.0] - 2019-12-16

### Removed

- Removed support for PHP `7.1`.

## [v2.0.2] - 2019-09-04

- Added zend-stdlib glob fallback for alpine based systems.

## [v2.0.1] - 2019-09-04

- Upgraded development dependencies
- Fixed appearing static analysis errors

## [v2.0.0] - 2019-01-02

### Removed

- Removed support for PHP `5.6` and `7.0.`. Closes [#29](https://github.com/raphaelstolt/lean-package-validator/issues/29).

## [v1.9.0] - 2018-11-03

### Added

- Enabled distribution via PHAR. Closes [#27](https://github.com/raphaelstolt/lean-package-validator/issues/27).

## [v1.8.1] - 2017-10-18

### Fixed

- Matched directories e.g. `tests` or `specs` are export-ignored only once. Closes [#24](https://github.com/raphaelstolt/lean-package-validator/issues/24).

## [v1.8.0] - 2017-10-12

### Added

- Additional `--align-export-ignores|-a` option to align the export-ignores which improves readability. Closes [#23](https://github.com/raphaelstolt/lean-package-validator/issues/23).
- Additional `--enforce-alignment` option to enforce that all export-ignores are aligned.

## [v1.7.3] - 2017-10-02

### Fixed

- Fix dist file pattern to also match `*.dist` files.

## [v1.7.2] - 2017-05-08

### Fixed

- Fix non existent export ignored artifacts are excluded from validation. Fixes [#22](https://github.com/raphaelstolt/lean-package-validator/issues/22).

## [v1.7.1] - 2017-05-06

### Fixed

- Fix gitignored files with a pre- and postfixed directory separator are excluded from validation. Fixes [#21](https://github.com/raphaelstolt/lean-package-validator/issues/21).

## [v1.7.0] - 2017-03-31

### Added

- Additional artifacts glob pattern expansion to match AppVeyor configuration files.

## [v1.6.0] - 2016-10-08

### Added

- New `init` command to create a `.lpv` file with the default glob patterns. Closes [#18](https://github.com/raphaelstolt/lean-package-validator/issues/18).

## [v1.5.2] - 2016-10-08

### Added

- Internal Composer scripts have a namespace.

### Fixed

- Fix gitignored files are excluded from validation. Fixes [#17](https://github.com/raphaelstolt/lean-package-validator/issues/17).

## [v1.5.1] - 2016-10-05

### Fixed

- Fix missing export-ignore patterns on existing `.gitattributes` file with no export-ignore entries. Fixes [#16](https://github.com/raphaelstolt/lean-package-validator/issues/16).

## [v1.5.0] - 2016-10-04

### Added

- New `--keep-license` option to allow license files in releases. Closes [#15](https://github.com/raphaelstolt/lean-package-validator/issues/15).

## [v1.4.0] - 2016-10-04

### Added

- Additional artifacts glob pattern expansion to match CaptainHook configuration files. Closes [#14](https://github.com/raphaelstolt/lean-package-validator/issues/14).

### Fixed

- Fix missing `.gitattributes export-ignore` in suggested and generated `.gitattributes` file content. Closes [#13](https://github.com/raphaelstolt/lean-package-validator/issues/13).

## [v1.3.1] - 2016-10-04

### Fixed

- Fix dependency constraint.

## [v1.3.0] - 2016-09-30

### Added

- Leading slashes in export-ignore patterns are considered as a smell and raise a warning. Closes [#4](https://github.com/raphaelstolt/lean-package-validator/issues/4).
- A missing text auto configuration is considered as a smell and raises a warning. Closes [#12](https://github.com/raphaelstolt/lean-package-validator/issues/12).

## [v1.2.0] - 2016-09-22

### Added

- New `--glob-pattern-file` option to load custom glob patterns from a file. Closes [#9](https://github.com/raphaelstolt/lean-package-validator/issues/9).

## [v1.1.0] - 2016-09-18

### Added

- Additional artifacts glob pattern expansion to match Phulp files.
- New `--enforce-strict-order` option to enforce a strict order comparison of export-ignores in the .gitattributes file. Closes [#6](https://github.com/raphaelstolt/lean-package-validator/issues/6).

## [v1.0.6] - 2016-09-11

### Fixed

- Fix present, invalid `.gitattributes` files are overwritable. Closes [#8](https://github.com/raphaelstolt/lean-package-validator/issues/8).

## [v1.0.5] - 2016-09-09

### Fixed

- Fix expected and actual `export-ignores` comparison. Related to [#3](https://github.com/raphaelstolt/lean-package-validator/issues/3).

## [v1.0.4] - 2016-09-09

### Added

- Additional artifacts glob pattern expansion to match Vagrant and Box files.

### Fixed

- Fix present `.gitattributes` files are really validated. Closes [#3](https://github.com/raphaelstolt/lean-package-validator/issues/3).

## [v1.0.3] - 2016-09-05

### Fixed

- Fix `directory` argument usage and validation.

## [v1.0.2] - 2016-09-05

### Added

- Additional validation of glob patterns injected via the `--glob-pattern` option. Closes [#2](https://github.com/raphaelstolt/lean-package-validator/issues/2).

## [v1.0.1] - 2016-09-04

### Fixed

- Fix for autoloading in global installations.

## v1.0.0 - 2016-09-04

- Initial release.

[Unreleased]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.1.0...HEAD
[v4.1.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.0.5...v4.1.0
[v4.0.5]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.0.4...v4.0.5
[v4.0.4]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.0.3...v4.0.4
[v4.0.3]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.0.2...v4.0.3
[v4.0.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.0.1...v4.0.2
[v4.0.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v4.0.0...v4.0.1
[v4.0.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.3.2...v4.0.0
[v3.3.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.3.1...v3.3.2
[v3.3.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.3.0...v3.3.1
[v3.3.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.2.0...v3.3.0
[v3.2.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.1.1...v3.2.0
[v3.1.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.1.0...v3.1.1
[v3.1.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.0.1...v3.1.0
[v3.0.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v3.0.0...v3.0.1
[v3.0.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v2.1.0...v3.0.0
[v2.1.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v2.0.2...v2.1.0
[v2.0.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v2.0.1...v2.0.2
[v2.0.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v2.0.0...v2.0.1
[v2.0.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.9.0...v2.0.0
[v1.9.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.8.1...v1.9.0
[v1.8.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.8.0...v1.8.1
[v1.8.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.3...v1.8.0
[v1.7.3]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.2...v1.7.3
[v1.7.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.1...v1.7.2
[v1.7.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.7.0...v1.7.1
[v1.7.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.6.0...v1.7.0
[v1.6.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.5.2...v1.6.0
[v1.5.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.5.1...v1.5.2
[v1.5.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.5.0...v1.5.1
[v1.5.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.4.0...v1.5.0
[v1.4.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.3.1...v1.4.0
[v1.3.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.3.0...v1.3.1
[v1.3.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.2.0...v1.3.0
[v1.2.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.1.0...v1.2.0
[v1.1.0]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.6...v1.1.0
[v1.0.6]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.5...v1.0.6
[v1.0.5]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.4...v1.0.5
[v1.0.4]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.3...v1.0.4
[v1.0.3]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.2...v1.0.3
[v1.0.2]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.1...v1.0.2
[v1.0.1]: https://github.com/raphaelstolt/lean-package-validator/compare/v1.0.0...v1.0.1
