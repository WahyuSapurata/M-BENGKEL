@php
    $path = explode('/', Request::path());
    $role = auth()->user()->role;
@endphp
<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header justify-content-center">
            <a class="b-brand">
                <!-- ========   change your logo hear   ============ -->
                <img src="{{ asset('logo.png') }}" style="width: 200px" alt="" class="logo logo-lg" />
                <img src="{{ asset('logo_favicon.png') }}" alt="" class="logo logo-sm" />
            </a>
        </div>
        <div class="navbar-content">
            {{-- <pre>{{ print_r(array_keys(session('hak_akses')->toArray()), true) }}</pre> --}}

            @if ($role === 'admin')
                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Admin</label>
                    </li>

                    {{-- Dashboard (global, tidak pakai canView) --}}
                    <li class="nxl-item nxl-hasmenu {{ $path[1] === 'dashboard-admin' ? 'active' : '' }}">
                        <a href="{{ route('admin.dashboard-admin') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboard</span>
                        </a>
                    </li>

                    {{-- Setup --}}
                    <li class="nxl-item nxl-hasmenu {{ $path[1] === 'setup' ? 'active' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-sliders"></i></span>
                            <span class="nxl-mtext">Setup</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'data-pengguna' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.data-pengguna') }}">Data Pengguna</a>
                            </li>
                            <li
                                class="nxl-item {{ isset($path[2]) && $path[2] === 'target-penjualan' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.target-penjualan') }}">Target
                                    Penjualan</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Master Data --}}
                    <li class="nxl-item nxl-hasmenu {{ $path[1] === 'master-data' ? 'active' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Master Data</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'kategori' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.kategori') }}">Kategori</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'suplayer' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.suplayer') }}">Suplayer</a>
                            </li>


                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'jasa' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.jasa') }}">Jasa</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'produk' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.produk') }}">Produk</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'kasir' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.kasir') }}">Data Kasir</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'costumer' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.costumer') }}">Data Costumer</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Transaksi --}}
                    <li class="nxl-item nxl-hasmenu {{ $path[1] === 'transaksi' ? 'active' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-dollar-sign"></i></span>
                            <span class="nxl-mtext">Transaksi</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'pembelian' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.pembelian') }}">Pembelian</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'hutang' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.hutang') }}">Hutang</a>
                            </li>
                        </ul>
                    </li>

                    {{-- Accounting --}}
                    <li class="nxl-item nxl-hasmenu {{ $path[1] === 'accounting' ? 'active' : '' }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-trello"></i></span>
                            <span class="nxl-mtext">Accounting</span><span class="nxl-arrow"><i
                                    class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li
                                class="nxl-item {{ isset($path[2]) && $path[2] === 'vw-lap-transaksi' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.vw-lap-transaksi') }}">Laporan
                                    Transaksi</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'pengeluaran' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.pengeluaran') }}">Pengeluaran</a>
                            </li>

                            <li
                                class="nxl-item {{ isset($path[2]) && $path[2] === 'vw-jurnal-umum' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.vw-jurnal-umum') }}">Jurnal Umum</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'vw-buku-besar' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.vw-buku-besar') }}">Buku Besar</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'vw-neraca' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.vw-neraca') }}">Neraca</a>
                            </li>

                            <li class="nxl-item {{ isset($path[2]) && $path[2] === 'vw-laba-rugi' ? 'active' : '' }}">
                                <a class="nxl-link" href="{{ route('admin.vw-laba-rugi') }}">Laba Rugi</a>
                            </li>
                        </ul>
                    </li>

                    <li class="nxl-item nxl-hasmenu {{ $path[1] === 'sumary-report' ? 'active' : '' }}">
                        <a href="{{ route('admin.sumary-report') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-repeat"></i></span>
                            <span class="nxl-mtext">Sumary Report</span>
                        </a>
                    </li>
                </ul>
            @endif
        </div>
    </div>
</nav>
