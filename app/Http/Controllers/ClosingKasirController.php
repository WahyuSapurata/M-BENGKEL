<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClosingKasirRequest;
use App\Http\Requests\UpdateClosingKasirRequest;
use App\Models\ClosingKasir;
use App\Models\Penjualan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClosingKasirController extends BaseController
{
    /**
     * Proses Closing Kasir
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'total_fisik' => 'required', // uang fisik dari kasir
            ],
            [
                'total_fisik.required' => 'Kolom total fisik harus di isi.'
            ]
        );

        $tanggal = Carbon::now()->format('d-m-Y');

        $penjualans = Penjualan::where('uuid_user', Auth::user()->uuid)
            ->where('tanggal_transaksi', $tanggal)
            ->with(['detailPenjualans']) // ⬅️ tambahkan relasi paket
            ->get();

        // Fungsi bantu hitung total per penjualan (produk + paket + jasa)
        $hitungTotal = function ($p) {
            $totalProduk = $p->detailPenjualans->sum('total_harga');

            $totalJasa = 0;
            if (!empty($p->uuid_jasa)) {
                $uuidJasaArray = is_array($p->uuid_jasa) ? $p->uuid_jasa : json_decode($p->uuid_jasa, true);

                if (!empty($uuidJasaArray)) {
                    // Hitung frekuensi tiap UUID
                    $counts = array_count_values($uuidJasaArray);

                    // Ambil semua harga jasa sesuai UUID
                    $hargaJasa = DB::table('jasas')
                        ->whereIn('uuid', array_keys($counts))
                        ->pluck('harga', 'uuid'); // [uuid => harga]

                    // Hitung total jasa sesuai frekuensi
                    foreach ($counts as $uuid => $qty) {
                        $totalJasa += ($hargaJasa[$uuid] ?? 0) * $qty;
                    }
                }
            }

            return $totalProduk + $totalJasa;
        };

        // Total penjualan
        $totalPenjualan = $penjualans->sum(fn($p) => $hitungTotal($p));

        // Total cash
        $totalCash = $penjualans->where('pembayaran', 'Tunai')->sum(fn($p) => $hitungTotal($p));

        // Total transfer
        $totalTransfer = $penjualans->where('pembayaran', 'Transfer Bank')->sum(fn($p) => $hitungTotal($p));

        // Hitung selisih antara sistem vs fisik
        $selisih = $request->total_fisik - $totalCash;

        // Simpan ke tabel closing_kasirs
        $closing = ClosingKasir::create([
            'uuid_user' => $request->uuid_kasir_outlet,
            'tanggal_closing'   => $tanggal,
            'total_penjualan'   => $totalPenjualan,
            'total_cash'        => $totalCash,
            'total_transfer'    => $totalTransfer,
            'total_fisik'       => $request->total_fisik,
            'selisih'           => $selisih,
        ]);

        // // Catat Jurnal Closing
        // $kasOutlet = Coa::where('nama', 'Kas Outlet')->firstOrFail();
        // $kas       = Coa::where('nama', 'Kas')->firstOrFail();
        // $no_bukti  = 'CLS-' . strtoupper(Str::random(6));

        // if ($totalCash > 0) {
        //     JurnalHelper::create(
        //         $tanggal,
        //         $no_bukti,
        //         'Closing Kasir',
        //         [
        //             ['uuid_coa' => $kas->uuid,       'debit'  => $totalCash],
        //             ['uuid_coa' => $kasOutlet->uuid, 'kredit' => $totalCash],
        //         ],
        //         $kasir->uuid_outlet
        //     );
        // }

        return response()->json([
            'status' => 'success',
            'data'   => $closing
        ]);
    }

    // public function history_summary($params)
    // {
    //     // Sama dengan index, bisa dipanggil ulang dari index() untuk menghindari duplikasi
    //     return $this->index($params);
    // }


    public function sumaryreport(Request $request)
    {
        $module = 'Summary Report';

        if ($request->ajax()) {
            // Tentukan tanggal batas (5 hari terakhir)
            $tanggalMulai = Carbon::now()->subDays(5)->format('d-m-Y');

            $query = ClosingKasir::with(['kasir.user'])
                ->whereRaw("STR_TO_DATE(tanggal_closing, '%d-%m-%Y') >= STR_TO_DATE(?, '%d-%m-%Y')", [$tanggalMulai])
                ->orderByRaw("STR_TO_DATE(tanggal_closing, '%d-%m-%Y') DESC"); // urut dari terbaru

            $data = $query->get()->map(function ($item) {
                return [
                    'kasir'            => $item->kasir->user->nama ?? '-',
                    'tanggal_closing'  => $item->tanggal_closing,
                    'total_penjualan'  => number_format($item->total_penjualan, 0, ',', '.'),
                    'total_cash'       => number_format($item->total_cash, 0, ',', '.'),
                    'total_transfer'   => number_format($item->total_transfer, 0, ',', '.'),
                    'total_fisik'      => number_format($item->total_fisik, 0, ',', '.'),
                    'selisih'          => number_format($item->selisih, 0, ',', '.'),
                    'uuid'             => $item->uuid,
                ];
            });

            return response()->json(['data' => $data]);
        }

        return view('admin.sumarireort.index', compact('module'));
    }
}
