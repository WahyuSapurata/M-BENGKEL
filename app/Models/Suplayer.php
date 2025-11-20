<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Suplayer extends Model
{
    use HasFactory;

    protected $table = 'suplayers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid',
        'kode',
        'nama',
        'alamat',
        'telepon',
        'kota',
    ];

    protected static function boot()
    {
        parent::boot();

        // Event listener untuk membuat UUID sebelum menyimpan
        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });
    }
}
