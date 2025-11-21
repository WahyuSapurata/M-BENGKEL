<?php

namespace App\Http\Controllers;

use App\Helpers\JurnalHelper;
use App\Http\Requests\StorePengeluaranRequest;
use App\Http\Requests\UpdatePengeluaranRequest;
use App\Models\Coa;
use App\Models\Jurnal;
use App\Models\Pengeluaran;
use Illuminate\Http\Request;

class PengeluaranController extends BaseController
{
    public function index()
    {
        $module = 'Pengeluaran';
        $coa = Coa::where('tipe', 'aset')->get();
        return view('admin.pengeluaran.index', compact('module', 'coa'));
    }

    public function get(Request $request)
    {
        $columns = [
            'uuid',
            'deskripsi',
            'sumber_dana',
            'nominal',
        ];

        $totalData = Pengeluaran::count();

        $query = Pengeluaran::select($columns);

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

    public function store(StorePengeluaranRequest $request)
    {
        $pengeluaran = Pengeluaran::create([
            'deskripsi'   => $request->deskripsi,
            'sumber_dana' => $request->sumber_dana,
            'nominal'     => preg_replace('/\D/', '', $request->nominal),
        ]);

        $coa = Coa::where('nama', $request->sumber_dana)->firstOrFail();

        JurnalHelper::create(
            now()->format('d-m-Y'),
            $request->sumber_dana,
            'Pengeluaran ' . $request->sumber_dana . ': ' . $request->deskripsi,
            [
                ['uuid_coa' => $coa->uuid, 'jenis' => 'kredit', 'nominal' => preg_replace('/\D/', '', $request->nominal)],
            ]
        );

        return response()->json(['status' => 'success']);
    }

    public function edit($params)
    {
        $data = Pengeluaran::where('uuid', $params)->firstOrFail();
        return response()->json($data);
    }

    public function update(StorePengeluaranRequest $request, $params)
    {
        $pengeluaran = Pengeluaran::where('uuid', $params)->firstOrFail();

        // simpan deskripsi lama sebelum diupdate
        $oldDeskripsi = $pengeluaran->deskripsi;

        // update data
        $pengeluaran->update([
            'deskripsi'   => $request->deskripsi,
            'sumber_dana' => $request->sumber_dana,
            'nominal'     => preg_replace('/\D/', '', $request->nominal),
        ]);

        // hapus jurnal lama (query yang benar)
        Jurnal::where('keterangan', 'like', "%Pengeluaran%{$oldDeskripsi}%")->delete();

        // buat jurnal baru
        $coa = Coa::where('nama', $request->sumber_dana)->firstOrFail();

        JurnalHelper::create(
            now()->format('d-m-Y'),
            $request->sumber_dana,
            'Pengeluaran ' . $request->sumber_dana . ': ' . $request->deskripsi,
            [
                ['uuid_coa' => $coa->uuid, 'jenis' => 'kredit', 'nominal' => preg_replace('/\D/', '', $request->nominal)],
            ]
        );

        return response()->json(['status' => 'success']);
    }

    public function delete($params)
    {
        $pengeluaran = Pengeluaran::where('uuid', $params)->firstOrFail();

        // hapus jurnal berdasarkan deskripsi lama
        Jurnal::where('keterangan', 'like', "%Pengeluaran%{$pengeluaran->deskripsi}%")->delete();

        // hapus pengeluaran
        $pengeluaran->delete();

        return response()->json(['status' => 'success']);
    }
}
