<?php

namespace App\Http\Controllers;

use App\Helpers\JurnalHelper;
use App\Http\Requests\StorePemindahanDanaRequest;
use App\Http\Requests\UpdatePemindahanDanaRequest;
use App\Models\Coa;
use App\Models\Jurnal;
use App\Models\PemindahanDana;
use Illuminate\Http\Request;

class PemindahanDanaController extends BaseController
{
    public function index()
    {
        $module = 'PemindahanDana';
        $coa = Coa::where('tipe', 'aset')->get();
        return view('admin.pemindahan.index', compact('module', 'coa'));
    }

    public function get(Request $request)
    {
        $columns = [
            'uuid',
            'deskripsi',
            'sumber_dana',
            'tujuan_dana',
            'nominal',
        ];

        $totalData = PemindahanDana::count();

        $query = PemindahanDana::select($columns);

        // Searching
        if (!empty($request->search['value'])) {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search, $columns) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $totalFiltered = $query->count();

        // Sorting
        if ($request->order) {
            $orderCol = $columns[$request->order[0]['column']];
            $orderDir = $request->order[0]['dir'];
            $query->orderBy($orderCol, $orderDir);
        } else {
            $query->latest();
        }

        // Pagination
        $query->skip($request->start)->take($request->length);

        $data = $query->get();

        // Format response DataTables
        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
    }

    public function store(StorePemindahanDanaRequest $request)
    {
        $nominal = preg_replace('/\D/', '', $request->nominal);

        $pemindahan = PemindahanDana::create([
            'deskripsi'   => $request->deskripsi,
            'sumber_dana' => $request->sumber_dana,
            'tujuan_dana' => $request->tujuan_dana,
            'nominal'     => $nominal,
        ]);

        // COA sumber dana (yang berkurang)
        $coaSumber = Coa::where('nama', $request->sumber_dana)->firstOrFail();

        // COA tujuan dana (yang bertambah)
        $coaTujuan = Coa::where('nama', $request->tujuan_dana)->firstOrFail();

        JurnalHelper::create(
            now()->format('d-m-Y'),
            'Pemindahan Dana',
            'Pemindahan ' . $request->sumber_dana . ' ke ' . $request->tujuan_dana . ': ' . $request->deskripsi,
            [
                // Tujuan dana bertambah → DEBIT
                [
                    'uuid_coa' => $coaTujuan->uuid,
                    'jenis'    => 'debit',
                    'nominal'  => $nominal,
                ],

                // Sumber dana berkurang → KREDIT
                [
                    'uuid_coa' => $coaSumber->uuid,
                    'jenis'    => 'kredit',
                    'nominal'  => $nominal,
                ]
            ]
        );

        return response()->json(['status' => 'success']);
    }

    public function edit($params)
    {
        $data = PemindahanDana::where('uuid', $params)->firstOrFail();
        return response()->json($data);
    }

    public function update(StorePemindahanDanaRequest $request, $params)
    {
        $pemindahan = PemindahanDana::where('uuid', $params)->firstOrFail();

        // simpan data lama sebelum diupdate
        $oldDeskripsi   = $pemindahan->deskripsi;
        $oldSumberDana  = $pemindahan->sumber_dana;
        $oldTujuanDana  = $pemindahan->tujuan_dana;

        $nominal = preg_replace('/\D/', '', $request->nominal);

        // update data
        $pemindahan->update([
            'deskripsi'   => $request->deskripsi,
            'sumber_dana' => $request->sumber_dana,
            'tujuan_dana' => $request->tujuan_dana,
            'nominal'     => $nominal,
        ]);

        // hapus jurnal lama
        Jurnal::where('keterangan', 'like', "%Pemindahan%{$oldSumberDana}%{$oldTujuanDana}%{$oldDeskripsi}%")->delete();

        // COA baru
        $coaSumber = Coa::where('nama', $request->sumber_dana)->firstOrFail();
        $coaTujuan = Coa::where('nama', $request->tujuan_dana)->firstOrFail();

        // buat jurnal baru
        JurnalHelper::create(
            now()->format('d-m-Y'),
            'Pemindahan Dana',
            'Pemindahan ' . $request->sumber_dana . ' ke ' . $request->tujuan_dana . ': ' . $request->deskripsi,
            [
                // tujuan dana bertambah → DEBIT
                [
                    'uuid_coa' => $coaTujuan->uuid,
                    'jenis'    => 'debit',
                    'nominal'  => $nominal,
                ],
                // sumber dana berkurang → KREDIT
                [
                    'uuid_coa' => $coaSumber->uuid,
                    'jenis'    => 'kredit',
                    'nominal'  => $nominal,
                ],
            ]
        );

        return response()->json(['status' => 'success']);
    }

    public function delete($params)
    {
        $pemindahan = PemindahanDana::where('uuid', $params)->firstOrFail();

        // hapus jurnal berdasarkan data lengkap
        Jurnal::where('keterangan', 'like', "%Pemindahan%{$pemindahan->sumber_dana}%{$pemindahan->tujuan_dana}%{$pemindahan->deskripsi}%")->delete();

        // hapus data pemindahan dana
        $pemindahan->delete();

        return response()->json(['status' => 'success']);
    }
}
