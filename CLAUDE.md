# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin that extends Dokan marketplace functionality for an Italian obituary and memorial services marketplace. The plugin manages various types of memorial content including death announcements (annunci morte), memorial posters (manifesti), anniversaries (anniversari), and 30-day memorial services (trigesimi).

## Key Architecture

### Namespace and Autoloading
- Uses PSR-4 autoloading with `Dokan_Mods` namespace
- Classes are loaded via `classes/` directory with automatic file inclusion
- Custom feature toggle system via `ClassEnabler.php` to selectively enable/disable functionality

### Core Classes
- **AnnuncioMorteClass.php**: Manages death announcements (custom post type)
- **ManifestoClass.php**: Handles memorial posters with PDF generation
- **AnniversarioFrontendClass.php**: Anniversary memorial management
- **TrigesimiFrontendClass.php**: 30-day memorial service management
- **EmailManagerClass.php**: Custom email template system
- **AutoPrelievoClass.php**: Automated payment processing for vendors
- **MigrationClass.php**: Handles data migration from legacy systems

### Plugin Dependencies
- Requires WooCommerce and Dokan plugins
- Uses TCPDF library for PDF generation
- Integrates with Elementor page builder

## Development Commands

Since this is a WordPress plugin without a build process:

```bash
# No build commands - direct PHP/CSS/JS editing
# WordPress CLI commands (if wp-cli is installed):
wp plugin activate dokan-mod
wp plugin deactivate dokan-mod
```

## Key Features and Implementation

### 1. Custom Post Types
The plugin registers multiple custom post types for memorial content:
- Death announcements with custom fields
- Manifesti (memorial posters) with template system
- Anniversaries and Trigesimi entries

### 2. PDF Generation
Uses TCPDF to generate printable memorial posters:
- Custom templates in `templates/customize.php`
- Font management for Italian typography
- QR code generation for online viewing

### 3. Vendor Extensions
Extends Dokan vendor functionality:
- Custom registration fields
- Vendor-specific memorial product management
- Automated payment withdrawal system

### 4. Geolocation Features
- CloudFlare-based geolocation for user location detection
- Location-based filtering for memorial services
- Province and city-based search functionality

### 5. Migration System
Comprehensive migration tools in `classes/admin/MigrationTasks/`:
- Legacy data import from SQL/XML
- ID mapping for related content
- Batch processing capabilities

## Important Considerations

### Italian Localization
- All user-facing text is in Italian
- Date formats follow Italian conventions (d/m/Y)
- Currency handling for EUR

### Data Structure
- Heavy use of WordPress post meta for custom fields
- Vendor data stored in user meta
- Custom database tables for performance-critical features

### Security
- Nonce verification on all AJAX endpoints
- Capability checks for vendor operations
- Sanitization of user inputs

### Performance
- Class-based feature toggles to reduce memory usage
- Lazy loading of heavy components
- Caching strategies for frequently accessed data