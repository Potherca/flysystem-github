# Change Log
All notable changes to the `flysystem-github` project will be documented in this 
file. This project adheres to the [keep-a-changelog](http://keepachangelog.com/) 
and [Semantic Versioning](http://semver.org/) conventions.

<!--
## [Unreleased][unreleased]
### Added
### Changed
### Deprecated
### Removed
### Fixed
### Security
-->

## [Unreleased][unreleased]

### Added

- Adds various @throws annotations
- Adds timestamps for files and folders
- Adds integration tests to validate output against comparable output from LocalAdapter

### Changed

- Changes the default for file visibility to visible
- Changes guessing MIME type for files to always use the file's content instead of extension to guarantee results are the same across different filesystem adapters
- Changes method name of ApiInterface::getRecursiveMetadata() to ApiInterface::getTreeMetadata()

### Fixed

- Fixes bug that caused invalid repository names to be accepted (issue #4)
- Fixes bug that caused incorrect Metadata for directories to be returned (issue #6)
- Fixes bug that didn't validate paths ended in a trailing slash (issue #8)
- Fixes bug in permission comparison
- Fixes various links in the README file

## [v0.2.0] - 2015-07-21 - Improvements and UnitTests

### Added

- Adds automated checks (a.k.a. unit-tests) for the Adapter, Client and Settings classes. 
- Adds various utility files for Travis builds, Coveralls and Composer

### Changed

- Makes the PHPUnit configuration more strict
- Renames the Client class to "Api"

## [v0.1.0] - 2015-07-18 - Read functionality

### Added

- Read functionality and Github API authentication have been implemented.

## v0.0.0 - 2015-05-11 - Project Setup

### Added

- Set up project basics like .gitignore file, PHPUnit Configuration file, 
Contributing guidelines, Composer file stating dependencies, MIT License, README 
file and this CHANGELOG file.

[unreleased]: https://github.com/potherca/flysystem-github/compare/v0.2.0...HEAD
[v0.3.0]: https://github.com/potherca/flysystem-github/compare/v0.2.0...v0.3.0
[v0.2.0]: https://github.com/potherca/flysystem-github/compare/v0.1.0...v0.2.0
[v0.1.0]: https://github.com/potherca/flysystem-github/compare/v0.0.0...v0.1.0
[keep-a-changelog]: http://keepachangelog.com/
[Semantic Versioning]: http://semver.org/
