<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackagingListLabelPrint extends Model
{
    use HasFactory;

    protected $fillable = ['packaging_list_carton_id', 'label_format', 'copies', 'reason', 'printed_by', 'printed_at'];
    protected $casts = ['printed_at' => 'datetime'];

    public function carton() { return $this->belongsTo(PackagingListCarton::class, 'packaging_list_carton_id'); }
    public function printer() { return $this->belongsTo(User::class, 'printed_by'); }
}
