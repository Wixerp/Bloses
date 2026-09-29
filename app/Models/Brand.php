<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'status_id',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function status()
    {
        return $this->belongsTo(MasterType::class, 'status_id');
    }
}
