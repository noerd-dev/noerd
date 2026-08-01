<?php

/**
 * The single polymorphic relation field component. Its markup lives in the
 * theme folders (themes/{theme}/polymorphic-relation-field.blade.php) and is
 * selected through the $theme property supplied by the detail block.
 */
new class extends \Noerd\Livewire\PolymorphicRelationFieldComponent {}; ?>

@include(\Noerd\Support\ThemeElementResolver::resolveRelationTemplate('polymorphic-relation-field', $theme))
