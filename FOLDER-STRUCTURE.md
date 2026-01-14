# FormaPress CRM - Folder Structure

## 📁 Root
- `formapress-crm.php` - Main plugin file

## 📦 `/classes/` (5 files)
Core business logic classes:
- `class-formapress-person-manager.php` - Person CRUD & queries
- `class-formapress-company-manager.php` - Company CRUD & queries
- `class-formapress-migration-manager.php` - v1→v2 migration tools
- `class-formapress-shortcode-manager.php` - Shortcode registration
- `class-formapress-person-adapter.php` - v1/v2 compatibility layer

## 👨‍💼 `/admin/` (11 files)
WordPress admin interface files:
- `admin-pages.php` - Admin menu pages
- `admin-opportunity-editor.php` - Custom opportunity editor UI
- `admin-person-editor.php` - Custom person editor UI
- `admin-notices-filter.php` - Admin notice filtering
- `class-formapress-person-meta-boxes.php` - Person edit screen meta boxes
- `class-formapress-company-meta-boxes.php` - Company edit screen meta boxes
- `options-company-attributes.php` - Company attributes settings
- `options-referent-attributes.php` - Referent attributes settings
- `options-prospect-attributes.php` - Prospect attributes settings
- `options-funder-attributes.php` - Funder attributes settings
- `options-opportunity-attributes.php` - Opportunity attributes settings

## 🏗️  `/cpts/` (5 files)
Custom Post Types and Taxonomies:
- `cpt-opportunity.php` - Opportunity CPT registration
- `cpt-invoice.php` - Invoice CPT registration
- `cpt-activity.php` - Activity CPT registration
- `cpt-person.php` - Person CPT (moved to zformations)
- `taxonomy-company-role.php` - Company role taxonomy

## 🧪 `/tests/` (9 files)
Test and verification scripts:
- `test-company-manager.php`
- `test-fresh-migration.php`
- `test-meta-boxes-render.php`
- `test-metabox-schemas.php`
- `test-mock-referent-migration.php`
- `test-person-manager.php`
- `test-referent-migration.php`
- `test-shortcode-registration.php`
- `test-zqpm-mapping.php`

## 📂 `/includes/` (18 files)
Utility functions and integration:
- `attribute-sanitization.php` - Attribute field sanitization
- `bpf-data-capture.php` - BPF data capture hooks
- `cli-fix-trainee-field-names.php` - WP-CLI commands
- `convert-instructor-attributes.php` - Attribute conversion utility
- `crm-communication-modal.php` - Communication modal UI
- `crm-pdf-generation.php` - PDF generation
- `crm-send-communication.php` - Email sending
- `crm-template-shortcodes.php` - CRM shortcodes
- `crm-templates-integration.php` - ZQPM templates integration
- `default-schemas.php` - Default attribute schemas
- `fix-person-multiple-companies.php` - Fix script
- `fix-person-taxonomy.php` - Fix script
- `funding-model.php` - Funding calculations
- `legacy-sync.php` - v1/v2 sync bridge
- `migration-reimport.php` - Re-import utility
- `migration-v2-attributes.php` - Attribute migration
- `synchronization.php` - Data synchronization
- `verify-person.php` - Person data verification

## 🎨 `/assets/`
- `/css/` - Compiled stylesheets
- `/js/` - Compiled JavaScript

## 🛠️  `/_dev/`
- `/js/` - Source JavaScript files
- `/scss/` - Source SCSS files
- `vite.config.js` - Build configuration
- `package.json` - NPM dependencies

---

**Last Updated**: January 14, 2026
**Reorganization**: All files organized by function for better maintainability
