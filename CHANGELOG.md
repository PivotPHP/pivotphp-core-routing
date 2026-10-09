# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.2] - 2026-10-09

### Fixed

- Optional route parameters (`:param?`) are now rejected with a clear
  `InvalidArgumentException` at registration, instead of being silently miscompiled.
  ([SPEC-026](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-026-optional-route-params.md))

## [1.2.1] - 2026-10-09

### Fixed

- `Router::getStats()`/`RouteCache::getDebugInfo()` no longer `serialize()` the route table
  (which is forbidden for `Closure` handlers and threw). Memory usage is now estimated by
  entry count. ([SPEC-047](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-047-routing-getstats-closure-fatal.md))
- `FileCacheStrategy` now rejects non-exportable values (`Closure`) explicitly in `set()`/
  `writeRoutesCache()` instead of failing silently on read.
  ([SPEC-048](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-048-file-cache-closures.md))
- URL-keyed caches (`prefixMatchCache`, `exactMatchCache`) are now capped at 1000 entries and
  per-URL tracking was removed, preventing unbounded memory growth in persistent workers.
  ([SPEC-046](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-046-routing-unbounded-caches.md))

## [1.2.0] - 2026-10-08

### Added

- Support for brace-delimited route parameters (`{id}` and `{id<constraint>}`) as an
  alternative to the colon syntax (`:id`), backed by the same constraint/shortcut/ReDoS
  validation. ([SPEC-002](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-002-brace-params.md))
- Array callables `[Classe::class, 'métodoDeInstância']` now resolve lazily
  (the class is instantiated at call time) instead of throwing
  `InvalidArgumentException`. Static methods and instance array callables keep
  working as before. ([SPEC-003](https://github.com/PivotPHP/pivotphp-specs/blob/main/SPECS/SPEC-003-array-callable.md))
