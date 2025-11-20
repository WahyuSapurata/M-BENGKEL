<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Jurnal extends Model
{
    use HasFactory;

    protected $table = 'jurnals';
    protected $primaryKey = 'id';
    protected $fillable = [
        'uuid',
        'uuid_coa',
        'tanggal',
        'ref',
        'deskripsi',
        'jenis',
        'nominal',
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
