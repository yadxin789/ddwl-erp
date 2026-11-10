<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $fillable = ['spu_id', 'buyer_nick', 'rating', 'content', 'is_hidden'];
}
