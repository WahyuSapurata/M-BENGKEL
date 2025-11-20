<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Costumer extends Model
{
    use HasFactory;

    protected $table = 'costumers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid',
        'uuid_penjualan',
        'nama',
        'alamat',
        'nomor',
        'plat',
    ];

    protected static function boot()
    {
        parent::boot();

        // Event listener untuk membuat UUID sebelum menyimpan
        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }

    // Relasi ke tabel penjualan
    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'uuid_penjualan', 'uuid');
    }
}
