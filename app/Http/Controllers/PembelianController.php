<?php

namespace App\Http\Controllers;

use App\Helpers\JurnalHelper;
use App\Http\Requests\StorePembelianRequest;
use App\Http\Requests\UpdatePembelianRequest;
use App\Models\Coa;
use App\Models\DetailPembelian;
use App\Models\Hutang;
use App\Models\Jurnal;
use App\Models\Pembelian;
use App\Models\PriceHistory;
use App\Models\Produk;
use App\Models\StokHistory;
use App\Models\Suplayer;
use App\Models\WirehouseStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PembelianController extends BaseController
{
    public function index()
    {
        $module = 'Pembelian';
        $suplayers = Suplayer::select('uuid', 'nama')->get();
        $aset = Coa::where('tipe', 'aset')->select('uuid', 'nama')->get();
        return view('pages.pembelian.index', compact('module', 'suplayers', 'aset'));
    }

    public function getProdukBySuplayer($params)
    {
        $produks = Produk::where('uuid_suplayer', $params)
            ->select('uuid', 'nama_barang')
            ->get();

        return response()->json($produks);
    }

    public function get(Request $request)
    {
        $columns = [
            'pembelians.uuid' => 'uuid',
            'pembelians.uuid_suplayer' => 'uuid_suplayer',
            'pembelians.no_invoice' => 'no_invoice',
            'pembelians.pembayaran' => 'pembayaran',
            'pembelians.tanggal_transaksi' => 'tanggal_transaksi',
            'pembelians.created_by' => 'created_by',
            'pembelians.updated_by' => 'updated_by',
            'suplayers.nama' => 'nama_suplayer',
            'COALESCE(SUM(detail_pembelians.qty * produks.hrg_modal),0)' => 'total_harga',
        ];

        // Hitung total tanpa filter
        $totalData = Pembelian::count();

        // SELECT dengan alias
        $selects = [];
        foreach ($columns as $dbCol => $alias) {
            $selects[] = "$dbCol as $alias";
        }

        $baseQuery = Pembelian::selectRaw(implode(", ", $selects))
            ->leftJoin('suplayers', 'suplayers.uuid', '=', 'pembelians.uuid_suplayer')
            ->leftJoin('detail_pembelians', 'detail_pembelians.uuid_pembelian', '=', 'pembelians.uuid')
            ->leftJoin('produks', 'produks.uuid', '=', 'detail_pembelians.uuid_produk')
            ->groupBy(
                'pembelians.uuid',
                'pembelians.uuid_suplayer',
                'pembelians.no_invoice',
                'pembelians.pembayaran',
                'pembelians.tanggal_transaksi',
                'pembelians.created_by',
                'pembelians.updated_by',
                'suplayers.nama'
            );

        // Searching
        if (!empty($request->search['value'])) {
            $search = $request->search['value'];
            $baseQuery->where(function ($q) use ($search, $columns) {
                foreach ($columns as $dbCol => $alias) {
                    if (str_contains($dbCol, 'SUM')) continue;
                    $q->orWhere($dbCol, 'like', "%{$search}%");
                }
            });
        }

        // Clone query untuk menghitung totalFiltered
        $filteredQuery = clone $baseQuery;
        $totalFiltered = $filteredQuery->get()->count(); // pakai get()->count() agar sesuai dengan hasil group

        // Sorting
        $baseQuery->orderBy('pembelians.tanggal_transaksi', 'asc');

        // Pagination
        $data = $baseQuery
            ->skip($request->start)
            ->take($request->length)
            ->get();

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
    }

    public function store(StorePembelianRequest $request)
    {
        // Ambil produk berdasarkan UUID
        $produk = Produk::whereIn('uuid', $request->uuid_produk)->get();

        if ($produk->count() !== count($request->uuid_produk)) {
            return response()->json(['status' => 'error', 'message' => 'Ada produk yang tidak ditemukan.'], 404);
        }

        if (count($request->uuid_produk) !== count($request->qty)) {
            return response()->json(['status' => 'error', 'message' => 'Jumlah produk dan qty tidak sesuai.'], 400);
        }

        // Simpan pembelian
        $pembelian = Pembelian::create([
            'uuid_suplayer'      => $request->uuid_suplayer,
            'no_invoice'         => $request->no_invoice,
            'no_internal'        => $request->no_internal,
            'pembayaran'         => $request->pembayaran,
            'tanggal_transaksi'  => $request->tanggal_transaksi,
            'keterangan'         => $request->keterangan,
            'created_by'         => Auth::user()->nama,
        ]);

        $totalPembelian = 0;

        // Simpan detail pembelian + hitung total
        foreach ($request->uuid_produk as $index => $uuid_produk) {
            $produk = $produk->where('uuid', $uuid_produk)->first();
            $qty = $request->qty[$index];

            $hargaBaru = (int) preg_replace('/\D/', '', $request->harga[$index]);

            DetailPembelian::create([
                'uuid_pembelian' => $pembelian->uuid,
                'uuid_produk'    => $uuid_produk,
                'qty'            => $qty,
                'harga'         => $hargaBaru,
            ]);

            // Simpan harga lama sebelum update
            $hargaLama = (int) $produk->hrg_modal;

            // Update harga modal produk
            $produk->update(['hrg_modal' => $hargaBaru]);

            // Tambahkan price history hanya jika modal berubah
            if ($hargaLama !== $hargaBaru) {
                PriceHistory::create([
                    'uuid_produk' => $produk->uuid,
                    'harga'       => $hargaBaru,
                ]);
            }

            $totalPembelian += $qty * $produk->hrg_modal;

            // 🎯 UPDATE STOK (Menambah stok)
            StokHistory::create([
                'uuid_produk' => $uuid_produk,
                'stock'   => $qty,
            ]);
        }

        $suplayer = Suplayer::where('uuid', $request->uuid_suplayer)->first();

        // Tambahkan stok per produk
        foreach ($request->uuid_produk as $index => $uuid_produk) {
            WirehouseStock::create([
                'uuid_produk'    => $uuid_produk,
                'qty'            => $request->qty[$index],
                'jenis'          => 'masuk',
                'sumber'         => 'pembelian',
                'keterangan'     => 'Pembelian dari supplier: ' . $suplayer->nama,
            ]);
        }

        // Jika pembayaran kredit → simpan hutang
        if ($request->pembayaran === 'Kredit') {
            Hutang::create([
                'uuid_pembelian' => $pembelian->uuid,
                'jatuh_tempo'    => now()->addDays(7)->format('d-m-Y'),
            ]);
        }

        // ======== Jurnal Otomatis ===pembelian======
        $persediaan = Coa::where('nama', 'Persediaan (Modal)')->first();
        $kas        = Coa::where('uuid', $request->aset)->first();
        $hutang     = Coa::where('nama', 'Hutang Usaha')->first();

        if ($request->pembayaran === 'Cash') {
            JurnalHelper::create(
                $request->tanggal_transaksi,
                $request->no_invoice,
                'Pembelian Cash ' . $kas->nama,
                [
                    ['uuid_coa' => $persediaan->uuid, 'jenis' => 'debit', 'nominal' => $totalPembelian],
                    ['uuid_coa' => $kas->uuid,        'jenis' => 'kredit', 'nominal' => $totalPembelian],
                ]
            );
        } elseif ($request->pembayaran === 'Kredit') {
            JurnalHelper::create(
                $request->tanggal_transaksi,
                $request->no_invoice,
                'Pembelian Kredit',
                [
                    ['uuid_coa' => $persediaan->uuid, 'jenis' => 'debit', 'nominal' => $totalPembelian],
                    ['uuid_coa' => $hutang->uuid,     'jenis' => 'kredit', 'nominal' => $totalPembelian],
                ]
            );
        }

        return response()->json(['status' => 'success']);
    }

    public function edit($uuid)
    {
        $pembelian = Pembelian::with(['details.produk'])->where('uuid', $uuid)->first();
        $junal = Jurnal::where('ref', $pembelian->no_invoice)->get();

        if ($junal) {
            $pembelian->aset = $junal[1]->uuid_coa;
        }

        return response()->json($pembelian);
    }

    public function update(StorePembelianRequest $request, $uuid)
    {
        // Cari data pembelian
        $pembelian = Pembelian::where('uuid', $uuid)->firstOrFail();

        // Ambil detail lama
        $detailLama = DetailPembelian::where('uuid_pembelian', $pembelian->uuid)->get();

        // 🔥 1. KEMBALIKAN STOK LAMA (kurangi stok)
        foreach ($detailLama as $d) {
            StokHistory::create([
                'uuid_produk' => $d->uuid_produk,
                'stock'         => -$d->qty, // dikurangi
            ]);
        }

        // Ambil produk berdasarkan UUID
        $produkList = Produk::whereIn('uuid', $request->uuid_produk)->get();

        // Pastikan semua produk ditemukan
        if ($produkList->count() !== count($request->uuid_produk)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ada produk yang tidak ditemukan.'
            ], 404);
        }

        // Update data pembelian
        $pembelian->update([
            'uuid_suplayer'     => $request->uuid_suplayer,
            'no_invoice'        => $request->no_invoice,
            'no_internal'       => $request->no_internal,
            'pembayaran'        => $request->pembayaran,
            'tanggal_transaksi' => $request->tanggal_transaksi,
            'keterangan'        => $request->keterangan,
            'updated_by'        => Auth::user()->nama,
        ]);

        // Hapus detail lama
        DetailPembelian::where('uuid_pembelian', $pembelian->uuid)->delete();

        $totalPembelian = 0;

        // Simpan detail baru
        foreach ($request->uuid_produk as $index => $uuid_produk) {
            $produk = $produkList->firstWhere('uuid', $uuid_produk);
            $qty = (int) $request->qty[$index];
            $hargaBaru = (int) preg_replace('/\D/', '', $request->harga[$index]);

            DetailPembelian::create([
                'uuid_pembelian' => $pembelian->uuid,
                'uuid_produk'    => $uuid_produk,
                'qty'            => $qty,
                'harga'          => $hargaBaru,
            ]);

            // Hitung total
            $totalPembelian += $qty * $hargaBaru;

            // Simpan harga lama sebelum update
            $hargaLama = (int) $produk->hrg_modal;

            // Update harga modal produk
            $produk->update(['hrg_modal' => $hargaBaru]);

            // Tambahkan price history hanya jika modal berubah
            if ($hargaLama !== $hargaBaru) {
                PriceHistory::create([
                    'uuid_produk' => $produk->uuid,
                    'harga'       => $hargaBaru,
                ]);
            }

            // 🎯 UPDATE STOK (Menambah stok)
            // 🔥 TAMBAHKAN STOK BARU
            StokHistory::create([
                'uuid_produk' => $uuid_produk,
                'stock'         => $qty,   // menambah stok
            ]);
        }

        $suplayer = Suplayer::where('uuid', $request->uuid_suplayer)->first();

        // Reset stok lama lalu simpan stok baru (hindari double stock)
        WirehouseStock::where('sumber', 'pembelian')
            ->where('keterangan', 'Pembelian dari supplier: ' . $suplayer->nama)
            ->delete();

        foreach ($request->uuid_produk as $index => $uuid_produk) {
            WirehouseStock::create([
                'uuid_produk'    => $uuid_produk,
                'qty'            => $request->qty[$index],
                'jenis'          => 'masuk',
                'sumber'         => 'pembelian',
                'keterangan'     => 'Pembelian dari supplier: ' . $suplayer->nama,
            ]);
        }

        // Jika pembayaran kredit → update / buat hutang
        if ($request->pembayaran === 'Kredit') {
            Hutang::updateOrCreate(
                ['uuid_pembelian' => $pembelian->uuid],
                ['jatuh_tempo'    => now()->addDays(7)->format('d-m-Y')]
            );
        } else {
            // Jika sudah lunas → hapus hutang lama
            Hutang::where('uuid_pembelian', $pembelian->uuid)->delete();
        }

        // ======== Jurnal Otomatis =========
        $persediaan = Coa::where('nama', 'Persediaan (Modal)')->first();
        $kas        = Coa::where('uuid', $request->aset)->first();
        $hutang     = Coa::where('nama', 'Hutang Usaha')->first();

        Jurnal::where('ref', $pembelian->no_invoice)->delete();

        if ($request->pembayaran === 'Cash') {
            JurnalHelper::create(
                $request->tanggal_transaksi,
                $request->no_invoice,
                'Pembelian Cash ' . $kas->nama,
                [
                    ['uuid_coa' => $persediaan->uuid, 'jenis' => 'debit', 'nominal' => $totalPembelian],
                    ['uuid_coa' => $kas->uuid,        'jenis' => 'kredit', 'nominal' => $totalPembelian],
                ]
            );
        } elseif ($request->pembayaran === 'Kredit') {
            JurnalHelper::create(
                $request->tanggal_transaksi,
                $request->no_invoice,
                'Pembelian Kredit',
                [
                    ['uuid_coa' => $persediaan->uuid, 'jenis' => 'debit', 'nominal' => $totalPembelian],
                    ['uuid_coa' => $hutang->uuid,     'jenis' => 'kredit', 'nominal' => $totalPembelian],
                ]
            );
        }

        return response()->json(['status' => 'success']);
    }

    public function delete($params)
    {
        // Cari pembelian yang mau dihapus
        $pembelian = Pembelian::where('uuid', $params)->firstOrFail();

        // Ambil semua produk terkait pembelian
        $uuidProduks = DetailPembelian::where('uuid_pembelian', $pembelian->uuid)
            ->pluck('uuid_produk');

        // Hapus stok yang terkait pembelian ini
        WirehouseStock::where('sumber', 'pembelian')
            ->whereIn('uuid_produk', $uuidProduks)
            ->delete();

        // Hapus detail pembelian
        DetailPembelian::where('uuid_pembelian', $pembelian->uuid)->delete();

        // Hapus hutang (jika ada)
        Hutang::where('uuid_pembelian', $pembelian->uuid)->delete();

        // // Hapus jurnal yang terkait
        Jurnal::where('ref', $pembelian->no_invoice)->delete();

        // Hapus pembelian utama
        $pembelian->delete();

        return response()->json(['status' => 'success']);
    }

    // public function form_po($uuid)
    // {
    //     $po = PoPusat::where('uuid', $uuid)->first();

    //     if (!$po) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'PO tidak ditemukan'
    //         ]);
    //     }

    //     // ambil supplier
    //     $supplier = Suplayer::where('uuid', $po->uuid_suplayer)->first();

    //     // ambil detail PO
    //     $details = DetailPoPusat::where('uuid_po_pusat', $po->uuid)->get();

    //     $detailsFormatted = $details->map(function ($d) {
    //         $produk = Produk::where('uuid', $d->uuid_produk)->first();
    //         return [
    //             'uuid_produk' => $d->uuid_produk,
    //             'nama_barang' => $produk ? $produk->nama_barang : null,
    //             'qty' => $d->qty,
    //             'harga' => $d->harga,
    //         ];
    //     });

    //     return response()->json([
    //         'status' => 'success',
    //         'po' => [
    //             'uuid_suplayer' => $po->uuid_suplayer,
    //             'nama_suplayer' => $supplier ? $supplier->nama : null,
    //             'no_po' => $po->no_po,
    //             'tanggal_transaksi' => $po->tanggal_transaksi,
    //             'keterangan' => $po->keterangan
    //         ],
    //         'details' => $detailsFormatted
    //     ]);
    // }

    // public function export_excel($uuid)
    // {
    //     // ===== Ambil data pembelian berdasarkan UUID =====
    //     $pembelian = Pembelian::with(['details', 'details.produk', 'suplayer'])
    //         ->where('uuid', $uuid)
    //         ->firstOrFail();

    //     $spreadsheet = new Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();

    //     // ===== Header =====
    //     $headers = [
    //         'A1' => 'No Invoice',
    //         'B1' => 'Nama Suplayer',
    //         'C1' => 'Pembayaran',
    //         'D1' => 'Produk',
    //         'E1' => 'Qty',
    //         'F1' => 'Harga',
    //         'H1' => 'Total Harga',
    //     ];

    //     foreach ($headers as $col => $text) {
    //         $sheet->setCellValue($col, $text);
    //     }

    //     // ===== Isi Data =====
    //     $row = 2;
    //     foreach ($pembelian->details as $index => $detail) {
    //         $sheet->setCellValue('A' . $row, $pembelian->no_invoice);
    //         $sheet->setCellValue('B' . $row, optional($pembelian->suplayer)->nama ?? '-');
    //         $sheet->setCellValue('C' . $row, $pembelian->pembayaran);
    //         $sheet->setCellValue('D' . $row, optional($detail->produk)->nama_barang ?? '-');
    //         $sheet->setCellValue('E' . $row, $detail->qty);
    //         $sheet->setCellValue('F' . $row, $detail->harga);
    //         $sheet->setCellValue('H' . $row, '=E' . $row . '*F' . $row);
    //         $row++;
    //     }

    //     // Auto width kolom
    //     foreach (range('A', 'H') as $col) {
    //         $sheet->getColumnDimension($col)->setAutoSize(true);
    //     }

    //     // ==== Styling Header ====
    //     $headerStyle = [
    //         'font' => ['bold' => true],
    //         'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    //         'borders' => [
    //             'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    //         ],
    //         'fill' => [
    //             'fillType' => Fill::FILL_SOLID,
    //             'color' => ['rgb' => 'D9E1F2'], // biru muda
    //         ],
    //     ];
    //     $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    //     // ==== Border untuk semua data ====
    //     $sheet->getStyle('A1:H' . ($row - 1))->applyFromArray([
    //         'borders' => [
    //             'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    //         ],
    //     ]);

    //     // ==== Total di paling bawah ====
    //     $sheet->mergeCells('A' . $row . ':G' . $row);
    //     $sheet->setCellValue('A' . $row, 'TOTAL');
    //     $sheet->setCellValue('H' . $row, '=SUM(H2:H' . ($row - 1) . ')');

    //     $totalStyle = [
    //         'font' => ['bold' => true],
    //         'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    //         'borders' => [
    //             'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    //         ],
    //         'fill' => [
    //             'fillType' => Fill::FILL_SOLID,
    //             'color' => ['rgb' => 'FCE4D6'], // oranye muda
    //         ],
    //     ];
    //     $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($totalStyle);

    //     // Format kolom harga jadi Rupiah
    //     $sheet->getStyle('F2:H' . $row)
    //         ->getNumberFormat()
    //         ->setFormatCode('"Rp" #,##0');

    //     // ===== Download =====
    //     $fileName = 'pembelian-' . $pembelian->no_invoice . '.xlsx';
    //     $writer = new Xlsx($spreadsheet);

    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header("Content-Disposition: attachment; filename=\"$fileName\"");
    //     header('Cache-Control: max-age=0');

    //     $writer->save('php://output');
    //     exit;
    // }
}
