<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentCommentAttachment extends Model
{
    protected $fillable = ['shipment_comment_id', 'file_path', 'original_name', 'mime_type', 'file_size'];

    public function comment()
    {
        return $this->belongsTo(ShipmentComment::class, 'shipment_comment_id');
    }
}
