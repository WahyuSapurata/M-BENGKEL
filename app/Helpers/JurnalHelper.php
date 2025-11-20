<?php

namespace App\Helpers;

use App\Models\Jurnal;
use Carbon\Carbon;

class JurnalHelper
{
    public static function create(
        $tanggal,
        $ref,
        $deskripsi,
        $entries = [],
    ) {
        foreach ($entries as $entry) {
            Jurnal::create([
                'tanggal'     => $tanggal ?? Carbon::now(),
                'ref'   => $ref,
                'deskripsi'   => $deskripsi,
                'uuid_coa'    => $entry['uuid_coa'],
                'jenis'       => $entry['jenis'],
                'nominal'      => $entry['nominal'] ?? 0,
            ]);
        }
    }
}
