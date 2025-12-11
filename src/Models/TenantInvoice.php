<?php

namespace Noerd\Noerd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Noerd\Database\Factories\TenantInvoiceFactory;

class TenantInvoice extends Model
{
    use HasFactory;

    public $table = 'tenant_invoices';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getInvoiceNumberAttribute(): string
    {
        return $this->number ?? '';
    }

    public function getTenantNameAttribute(): string
    {
        return $this->tenant?->name ?? '';
    }

    protected static function newFactory(): TenantInvoiceFactory
    {
        return TenantInvoiceFactory::new();
    }
}
