<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Office-only notes; separate from client portal comments and tracking history. */
class ShipmentComment extends Model
{
    protected $fillable = ['shipment_id', 'created_by', 'body'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(ShipmentCommentAttachment::class)->orderBy('id');
    }
}
