# Task Completion Checklist

## Before Completing Any Task

### Code Quality
- [ ] Follow PSR-12 coding standards
- [ ] Use strict types declaration
- [ ] Implement proper type hints
- [ ] Use Laravel 12+ patterns and best practices
- [ ] Follow Filament v3 conventions if applicable

### Database Changes
- [ ] Create proper migrations with descriptive names
- [ ] Add necessary indexes for performance
- [ ] Use UUID primary keys for new tables
- [ ] Implement proper foreign key constraints
- [ ] Test migration rollback functionality

### Internationalization
- [ ] Use `__()` for all user-facing text
- [ ] Add translations to both English and Vietnamese files
- [ ] Organize translation keys by feature area
- [ ] Ensure enum labels are translatable

### Testing and Validation
- [ ] Test functionality manually
- [ ] Verify database changes work correctly
- [ ] Check API endpoints if applicable
- [ ] Validate form requests and validation rules
- [ ] Test error handling scenarios

### Documentation
- [ ] Update relevant documentation files
- [ ] Add inline comments only where necessary
- [ ] Ensure code is self-explanatory
- [ ] Update API documentation if endpoints changed

### Performance and Security
- [ ] Use eager loading to prevent N+1 queries
- [ ] Implement proper authorization checks
- [ ] Validate all user inputs
- [ ] Use appropriate caching where beneficial
- [ ] Follow security best practices

## Final Steps
- [ ] Run `composer dev` to test all services
- [ ] Clear all caches: `php artisan config:clear`
- [ ] Format code: `./vendor/bin/pint`
- [ ] Verify no breaking changes introduced
- [ ] Update memory files if architectural changes made

## Never Do Without Permission
- [ ] Commit or push code to repository
- [ ] Install new dependencies
- [ ] Change database structure in production
- [ ] Deploy code to production
- [ ] Modify core Laravel files