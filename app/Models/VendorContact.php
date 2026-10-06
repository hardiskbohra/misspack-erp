<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Somebody at a supplier who is not the primary contact.
 *
 * The primary contact stays on the vendor row (`contact_person_name` and the
 * two fields beside it) because that is the person the list, the record header
 * and every export show. This model carries the rest of the desk: the
 * accountant who raises the invoice, the dispatch clerk who books the courier,
 * the inspector who signs the quality sheet.
 */
class VendorContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'name', 'designation', 'email', 'mobile', 'whatsapp', 'notes',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
