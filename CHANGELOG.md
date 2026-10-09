# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Support for brace-delimited route parameters (`{id}` and `{id<constraint>}`) as an
  alternative to the colon syntax (`:id`), backed by the same constraint/shortcut/ReDoS
  validation. ([SPEC-002](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-002-brace-params.md))
