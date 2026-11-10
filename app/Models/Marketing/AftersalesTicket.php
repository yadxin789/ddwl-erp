<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class AftersalesTicket extends Model
{
    protected $fillable = ['ticket_no', 'ecom_order_no', 'customer_name', 'customer_phone', 'type', 'description', 'status', 'handler_id'];
}
