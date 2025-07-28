# Dokan-Mod Development Guide

## Environment
- PHP 8.0 required
- WordPress plugin extending Dokan and WooCommerce
- Composer for dependency management

## Commands
- `composer install` - Install dependencies
- `composer update` - Update dependencies
- `composer dump-autoload` - Update autoloader after adding new classes

## Code Style Guidelines
- **Namespace**: `Dokan_Mods` for all classes
- **Class Names**: PascalCase (e.g., `ManifestoClass`)
- **Method Names**: camelCase (e.g., `createMenu`, `loopInstanziateClass`)
- **Files**: Each class has its own file, named to match the class
- **Security**: Always include `if (!defined('ABSPATH')) exit;` at the start of files
- **Class Loading**: Check `if (!class_exists())` before loading classes
- **Error Handling**: Use `error_log()` for logging, `wp_die()` for fatal errors
- **Sanitization**: Use WordPress sanitization functions for user input
- **Nonce Verification**: Always verify nonces for form submissions
- **Hook Naming**: Follow WordPress conventions for hooks

## Quality Assurance
- Qodana used for static code analysis