<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'document_type_id',
        'status_id',
        'document_number',
        'file_path',
        'original_file_name',
        'mime_type',
        'file_size',
        'admin_note',
        'verified_at',
        'verified_by',
    ];

    protected $hidden = [
        'document_number',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function documentType()
    {
        return $this->belongsTo(MasterType::class, 'document_type_id');
    }

    public function status()
    {
        return $this->belongsTo(MasterType::class, 'status_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
