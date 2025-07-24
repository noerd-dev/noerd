<?php

use function Livewire\Volt\{with, usesPagination};
use function Livewire\Volt\{layout, state};

//layout('noerd::components.layouts.start');
?>

<div class="max-w-4xl mx-auto">


    <div class="mb-12 flex">
        <x-noerd::dashboard-card icon="cart" title="Zum Shop" external="sads" background="bg-green-50"/>
    </div>




    {{--
   <div class="mb-12">
       <div class="font-semibold text-sm border-b pb-2">
           {{__('Open Orders')}}
       </div>
   </div>

   <livewire:orders-table statusFilter="0"/>
   --}}
</div>
