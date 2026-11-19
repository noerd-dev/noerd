<?php

declare(strict_types=1);

namespace Noerd\Attributes;

use Attribute;

/**
 * Marks a component method as a save whose outcome the page shows, like
 * store(), save() and update(): when it ends with an error the page marks the
 * tabs holding the failing fields and says next to the Save button that
 * nothing was saved (ValidationFeedbackHook).
 *
 *     #[ReportsSaveOutcome]
 *     public function confirm(): void
 *
 * It only reports the outcome — permissions stay the method's own business:
 * unlike the three built-in save names, a marked method is not guarded by the
 * WriteGuardHook and does not persist the relation forms.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ReportsSaveOutcome {}
