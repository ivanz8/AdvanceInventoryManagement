/**
 * The policy mappings for the application.
 *
 * @var array<class-string, class-string>
 */
protected $policies = [
    // ... existing policies ...
    Branch::class => BranchPolicy::class,
]; 