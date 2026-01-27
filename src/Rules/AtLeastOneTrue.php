<?php

namespace Noerd\Rules;

use Illuminate\Contracts\Validation\Rule;

class AtLeastOneTrue implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Check if the value is an array and at least one element is true
        return is_array($value) && in_array(true, $value, true);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must have at least one true value.';
    }
}
