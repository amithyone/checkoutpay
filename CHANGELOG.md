# Changelog

All notable changes to the Email Payment Gateway project.

## [Unreleased] - 2026-01-07

### Added
- ✅ **Payment Expiration Feature**: Payments now automatically expire after 24 hours (configurable)
- ✅ **Duplicate Payment Detection**: Prevents processing the same payment twice
- ✅ **Payment Statistics API**: New `/api/v1/statistics` endpoint for analytics
- ✅ **Database Indexes**: Performance optimizations with composite indexes
- ✅ **Expire Payments Command**: Scheduled job to clean up expired payments
- ✅ **Improvements Documentation**: Comprehensive roadmap in `IMPROVEMENTS.md`

### Changed
- Payment model now includes `expires_at` field
- Pending payments query excludes expired payments
- Payment service sets expiration time on creation

### Removed
- Old Node.js service files (`services/*.js`) - cleaned up

### Fixed
- Improved duplicate payment handling
- Better error handling in payment matching

## [1.0.0] - 2026-01-06

### Added
- Initial release
- Email monitoring system
- Payment matching logic
- Webhook notifications
- RESTful API endpoints
- Gmail integration
- Queue-based processing
- Scheduled email monitoring
