# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- CHANGELOG.md file to track project changes

### Fixed
- PostgreSQL database connection issues in TableResource.php
  - Added proper PostgreSQL connection configuration parameters
  - Separated MySQL and PostgreSQL configuration arrays
  - Added required PostgreSQL parameters: `charset`, `prefix`, `prefix_indexes`, and `search_path`
  - Added MySQL-specific parameters: `charset`, `collation`, `prefix`, and `prefix_indexes`

### Changed
- Improved dynamic database connection building in TableResource.php:62-88 and TableResource.php:159-185

## [Previous Versions]

### Features
- Redis Stream Queue System with custom queue connectors
- Database configuration management for MySQL and PostgreSQL
- Stream message processing with insert and update operations
- Field mapping between applications and database tables
- Filament admin interface for database and table management

### Known Issues
- PostgreSQL table listing not working in database dropdown (fixed in unreleased)

---

## Change Log Format

### Categories
- **Added** for new features
- **Changed** for changes in existing functionality
- **Deprecated** for soon-to-be removed features
- **Removed** for now removed features
- **Fixed** for any bug fixes
- **Security** in case of vulnerabilities

### Version Format
- `[Unreleased]` - Changes currently in development
- `[Version]` - Released versions with dates