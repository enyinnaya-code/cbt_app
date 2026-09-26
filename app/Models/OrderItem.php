<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'subject_id', 'list_price'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
