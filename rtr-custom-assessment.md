# RTR Custom Assessment Plugin

## Project Overview

WordPress plugin for an AJAX-powered entrepreneurial mindset assessment with admin dashboard, results scoring, CSV/PDF export, and email notifications.

**Shortcode:** `[custom_assessment]`
**Version:** 2.0.0
**PHP Requirement:** 7.4+
**WordPress Requirement:** 6.0+

## Core Features

- Step-by-step assessment questions with progress tracking
- Automatic score calculation and profile summaries
- Multiple assessment types (Social Fluency, Inner Dimensions)
- Admin dashboard for questions, categories, and submissions
- CSV/PDF export functionality
- Email notifications to respondents
- Full-screen AJAX-powered UI
- Logging system for tracking

## Directory Structure

### `/admin/`

- `class-ca-admin.php` - Admin dashboard menu and settings

### `/includes/` - Core logic

- `class-ca-database.php` - Database table creation and queries
- `class-ca-assessment-types.php` - Base assessment type class
- `class-ca-assessment-registry.php` - Registry pattern for assessment types
- `class-ca-questions.php` - Base questions class
- `class-ca-social-fluency-questions.php` - Social Fluency assessment questions
- `class-ca-inner-dimensions-questions.php` - Inner Dimensions assessment questions
- `class-ca-scoring.php` - Score calculation logic
- `class-ca-ajax.php` - AJAX endpoints
- `class-ca-shortcode.php` - Shortcode registration and rendering
- `class-ca-mailer.php` - Email sending (results)
- `class-ca-pdf.php` - PDF export functionality
- `class-ca-logger.php` - Logging/debugging

### `/assets/`

- `/css/admin.css` - Admin dashboard styles
- `/css/assessment.css` - Frontend assessment styles
- `/js/admin.js` - Admin functionality
- `/js/assessment.js` - Frontend assessment interaction

## Key Constants

```php
CA_VERSION = '2.0.0'
CA_PLUGIN_DIR = plugin directory path
CA_PLUGIN_URL = plugin URL
CA_TEXT_DOMAIN = 'rtr-custom-assessment'
```

## Plugin Bootstrap

1. `register_activation_hook` - Creates database tables via `CA_Database::create_tables()`
2. `plugins_loaded` hook initializes: `CA_Ajax`, `CA_Shortcode`, `CA_Admin`

## Main Classes & Responsibilities

| Class                           | Purpose                               |
| ------------------------------- | ------------------------------------- |
| `CA_Database`                   | Table creation, schema, data queries  |
| `CA_Assessment_Types`           | Base class for assessment types       |
| `CA_Assessment_Registry`        | Manages registered assessment types   |
| `CA_Questions`                  | Base questions class                  |
| `CA_Social_Fluency_Questions`   | Social Fluency assessment             |
| `CA_Inner_Dimensions_Questions` | Inner Dimensions assessment           |
| `CA_Scoring`                    | Score calculation & result generation |
| `CA_Ajax`                       | Handles AJAX endpoints                |
| `CA_Shortcode`                  | Frontend shortcode output             |
| `CA_Mailer`                     | Email delivery                        |
| `CA_PDF`                        | PDF export                            |
| `CA_Logger`                     | Debug logging                         |
| `CA_Admin`                      | Admin dashboard UI                    |

## Development Tips

- Assessment types extend `CA_Assessment_Types`
- Use registry pattern to add new assessment types
- AJAX calls handled via `CA_Ajax` class
- Scoring logic separate from questions
- PDF/CSV export via dedicated classes
- Email templates in `CA_Mailer`

## Common Tasks

- Add new assessment: Create class extending `CA_Assessment_Types`, register in registry
- Add questions: Extend `CA_Questions` or specific assessment class
- Modify scoring: Edit `CA_Scoring` class
- Add AJAX endpoint: Add to `CA_Ajax` class
- Database changes: Update `CA_Database` class

## Important Notes

- Plugin uses standard WordPress hooks (`plugins_loaded`, `register_activation_hook`)
- Text domain: `rtr-custom-assessment` for translations
- Check database schema in `CA_Database` for table structure
