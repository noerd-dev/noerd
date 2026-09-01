<?php

declare(strict_types=1);

namespace Noerd\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the value is an array with at least one element that is `true`.
 */
class AtLeastOneTrue implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! in_array(true, $value, true)) {
            $fail(__('The :attribute must have at least one true value.'));
        }
    }
}
