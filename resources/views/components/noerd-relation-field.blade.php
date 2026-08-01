<?php

/**
 * The single relation field component. Its markup lives in the theme folders
 * (themes/{theme}/relation-field.blade.php) and is selected through the
 * $theme property supplied by the detail block.
 */
new class extends \Noerd\Livewire\RelationFieldComponent {}; ?>

@include(\Noerd\Support\ThemeElementResolver::resolveRelationTemplate('relation-field', $theme))
