# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

* Optional Laravel MCP server for writing Course → Lesson → video Step data (`create_video_course` plus granular tools). New courses default to private. Hosts that want MCP should `composer require laravel/mcp`.

## v4.7.6 - 2026-08-07

### What's Changed

* Bump shell-quote from 1.8.4 to 1.10.0 by @dependabot[bot] in https://github.com/TappNetwork/Filament-LMS/pull/118
* Bump postcss from 8.5.12 to 8.5.25 by @dependabot[bot] in https://github.com/TappNetwork/Filament-LMS/pull/119
* Add support to use navigation from another panel by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/120

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.7.5...v4.7.6

## v4.7.5 - 2026-07-21

### What's Changed

* Add SCORM multi-part upload support by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/117

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.7.4...v4.7.5

## v4.7.4 - 2026-07-20

### What's Changed

* Fix certificate PDF generation on Chromium sandbox-restricted hosts by @stevewilliamson in https://github.com/TappNetwork/Filament-LMS/pull/116

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.7.3...v4.7.4

## v4.7.3 - 2026-07-15

### What's Changed

* CU-868kcn1vz: Fix YouTube mobile play for learners by @scottgrayson in https://github.com/TappNetwork/Filament-LMS/pull/115

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.7.2...v4.7.3

## v4.7.2 - 2026-07-14

### What's Changed

* Tweak course completion for SCORM Articulate Storyline type by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/114

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.7.1...v4.7.2

## v4.7.1 - 2026-07-10

### What's Changed

* Show evaluation button on courses by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/113

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.7.0...v4.7.1

## v4.7.0 - 2026-07-09

### What's Changed

* Add unique validation to slug, name, and external id by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/112

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.6.0...v4.7.0

## v4.6.0 - 2026-07-07

### What's Changed

* Support Pest 5 by @swilla in https://github.com/TappNetwork/Filament-LMS/pull/111
* Course evaluation by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/110

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.5.0...v4.6.0

## v4.5.0 - 2026-06-20

### What's Changed

* Bump shell-quote from 1.8.1 to 1.8.4 by @dependabot[bot] in https://github.com/TappNetwork/Filament-LMS/pull/107
* Use Node 24-compatible GitHub Actions by @swilla in https://github.com/TappNetwork/Filament-LMS/pull/108
* Bump esbuild from 0.25.0 to 0.28.1 by @dependabot[bot] in https://github.com/TappNetwork/Filament-LMS/pull/109
* Import Common Cartridge and SCORM packages by @swilla in https://github.com/TappNetwork/Filament-LMS/pull/105

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.4.2...v4.5.0

## v4.4.2 - 2026-05-12

### What's Changed

* Add configurable resources by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/104

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.4.1...v4.4.2

## v4.4.1 - 2026-05-12

### What's Changed

* Add filament library integration by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/103

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.4.0...v4.4.1

## v4.4.0 - 2026-04-28

### What's Changed

* Fix CI compatibility for Excel 4 and Laravel CSRF middleware by @swilla in https://github.com/TappNetwork/Filament-LMS/pull/101
* Bump postcss from 8.4.49 to 8.5.12 by @dependabot[bot] in https://github.com/TappNetwork/Filament-LMS/pull/102
* Add import course by @andreia in https://github.com/TappNetwork/Filament-LMS/pull/96

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.3.1...v4.4.0

## v4.3.1 - 2026-04-27

### What's Changed

* Add PHP 8.5 to CI testing matrix by @swilla in https://github.com/TappNetwork/Filament-LMS/pull/100

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.3.0...v4.3.1

## v4.3.0 - 2026-04-14

### What's Changed

* Add Laravel 13 support by @swilla in https://github.com/TappNetwork/Filament-LMS/pull/99

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.2.0...v4.3.0

## v4.2.0 - 2026-03-26

### What's Changed

* Bump minimatch from 3.1.2 to 3.1.5 by @dependabot[bot] in https://github.com/TappNetwork/Filament-LMS/pull/97
* Credits by @scottgrayson in https://github.com/TappNetwork/Filament-LMS/pull/98

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.1.11...v4.2.0

## v4.1.11 - 2026-02-19

### What's Changed

* Fix external_id auto-generation: keep acronyms together (e.g. dns_cloudflare not d_n_s_cloudflare) by @scottgrayson in https://github.com/TappNetwork/Filament-LMS/pull/95

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.1.10...v4.1.11

## v4.1.10 - 2026-02-13

### What's Changed

* Table actions in ActionGroup at row start by @scottgrayson in https://github.com/TappNetwork/Filament-LMS/pull/94

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.1.9...v4.1.10

## v4.1.9 - 2026-02-06

### What's Changed

* Fix reporting page: Eloquent wrapper, configurable user name, filters by @scottgrayson in https://github.com/TappNetwork/Filament-LMS/pull/93

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.1.8...v4.1.9

## v4.1.8 - 2026-02-06

### What's Changed

* Step page: Filament section + fi-prose for text-only steps and markdown by @scottgrayson in https://github.com/TappNetwork/Filament-LMS/pull/92

**Full Changelog**: https://github.com/TappNetwork/Filament-LMS/compare/v4.1.7...v4.1.8
