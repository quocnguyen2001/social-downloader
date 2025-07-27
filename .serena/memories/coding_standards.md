# Coding Standards and Conventions

## PHP Standards
- **PSR-12 Compliance**: 4 spaces indentation, 120 character line limit
- **Strict Types**: Always use `declare(strict_types=1);`
- **Type Hints**: Use typed properties and return types (PHP 8.1+)
- **Enums**: Use PHP 8.1+ enums with `label()`, `getIcon()`, `getColor()` methods

## Laravel Patterns
- **Request Validation**: Always use dedicated FormRequest classes
- **Service Layer**: Business logic in Services, thin controllers
- **Eloquent**: Use `Model::query()->...` pattern, require `::query()`
- **Internationalization**: Use `__()` for all user-facing text, no hardcoded strings

## Database Conventions
- **Primary Keys**: UUID-based primary keys for all models
- **Relationships**: Proper foreign key constraints with cascade deletes
- **Indexes**: Add indexes for performance on frequently queried columns
- **Migrations**: Descriptive migration names with timestamps

## Filament v3 Standards
- **Resources**: Use Section components to group form fields
- **Validation**: Implement proper validation on form fields
- **Authorization**: Use Laravel policies for access control
- **Performance**: Use eager loading for relationships

## File Organization
- **Namespacing**: Follow PSR-4 autoloading standards
- **Single Responsibility**: One class per file, clear purpose
- **Naming**: Descriptive class and method names
- **Documentation**: Minimal comments, self-explanatory code

## Translation System
- **Structure**: Organized by feature areas in `/lang/en/` and `/lang/vi/`
- **Keys**: Use dot notation for nested translations
- **Enums**: All enum labels must be translatable
- **Consistency**: English as primary, Vietnamese as secondary