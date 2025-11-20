<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataKasirRequest;
use App\Http\Requests\UpdateDataKasirRequest;
use App\Models\DataKasir;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DataKasirController extends BaseController
{
    public function index()
    {
        $module = 'Data Kasir';
        return view('admin.datakasir.index', compact('module'));
    }

    public function get(Request $request)
    {
        $columns = [
            'data_kasirs.uuid',
            'data_kasirs.uuid_user',
            'data_kasirs.alamat',
            'data_kasirs.telepon',
            'users.nama',
            'users.username',
            'users.password_hash',
        ];

        $totalData = DataKasir::count();

        $query = DataKasir::select($columns)
            ->leftJoin('users', 'users.uuid', '=', 'data_kasirs.uuid_user');

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

    public function store(StoreDataKasirRequest $request)
    {
        $user = User::create([
            'nama' => $request->nama,
            'username' => $request->username,
            'password_hash' => $request->password_hash,
            'password' => Hash::make($request->password_hash),
            'role' => 'kasir',
        ]);

        DataKasir::create([
            'uuid_user' => $user->uuid,
            'alamat' => $request->alamat,
            'telepon' => $request->telepon,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function edit($params)
    {
        $data = DataKasir::where('uuid', $params)->firstOrFail();
        $user = User::where('uuid', $data->uuid_user)->first();

        // Tambahkan data user ke outlet
        if ($user) {
            $data->nama = $user->nama;
            $data->username = $user->username;
            $data->password_hash = $user->password_hash;
        }

        return response()->json($data);
    }

    public function update(StoreDataKasirRequest $request, $params)
    {
        $DataKasir = DataKasir::where('uuid', $params)->firstOrFail();
        $DataKasir->update([
            'alamat'      => $request->alamat,
            'telepon'     => $request->telepon,
        ]);

        // Kalau mau update user juga (opsional)
        if ($DataKasir->uuid_user) {
            User::where('uuid', $DataKasir->uuid_user)->update([
                'nama'     => $request->nama,
                'username' => $request->username,
                'password_hash' => $request->password_hash,
                // password update kalau ada
                'password' => $request->password_hash ? Hash::make($request->password_hash) : DB::raw('password'),
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    public function delete($params)
    {
        $DataKasir = DataKasir::where('uuid', $params)->firstOrFail();

        DB::transaction(function () use ($DataKasir) {
            if ($DataKasir->uuid_user) {
                User::where('uuid', $DataKasir->uuid_user)->delete();
            }
            $DataKasir->delete();
        });

        return response()->json(['status' => 'success']);
    }
}
