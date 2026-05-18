<?php
session_start();
include 'koneksi.php'; 

// 1. CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';

// Hanya Admin & Owner yang boleh ubah Master Akun (Akuntan cuma boleh lihat/input jurnal)
if (!in_array($role_user, ['admin', 'owner', 'akuntan'])) {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>"; exit;
}

// --- LOGIC TAMBAH / EDIT AKUN ---
$pesan = "";
if (isset($_POST['simpan_akun'])) {
    $mode           = $_POST['mode']; // 'add' atau 'edit'
    $kode_akun      = $_POST['kode_akun'];
    $kode_lama      = $_POST['kode_lama']; // Untuk mode edit
    $nama_akun      = $_POST['nama_akun'];
    $kategori       = $_POST['kategori'];
    $tipe_laporan   = $_POST['tipe_laporan'];
    $posisi_normal  = $_POST['posisi_normal'];

    if ($mode == 'add') {
        // Cek duplikat kode menggunakan Prepared Statement
        $stmt_cek = $koneksi->prepare("SELECT kode_akun FROM akun_coa WHERE kode_akun = ?");
        $stmt_cek->bind_param("s", $kode_akun);
        $stmt_cek->execute();
        $stmt_cek->store_result();
        
        if ($stmt_cek->num_rows > 0) {
            $pesan = "<script>Swal.fire('Gagal!', 'Kode Akun $kode_akun sudah ada!', 'error');</script>";
        } else {
            $stmt_ins = $koneksi->prepare("INSERT INTO akun_coa (kode_akun, nama_akun, kategori, tipe_laporan, posisi_normal) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("sssss", $kode_akun, $nama_akun, $kategori, $tipe_laporan, $posisi_normal);
            if ($stmt_ins->execute()) {
                $pesan = "<script>Swal.fire({icon: 'success', title: 'Berhasil', text: 'Akun baru ditambahkan', timer: 1500, showConfirmButton: false});</script>";
            } else {
                $pesan = "<script>Swal.fire('Error', 'Database error: " . $stmt_ins->error . "', 'error');</script>";
            }
            $stmt_ins->close();
        }
        $stmt_cek->close();
    } else if ($mode == 'edit') {
        // Update data menggunakan Prepared Statement
        $stmt_upd = $koneksi->prepare("UPDATE akun_coa SET kode_akun = ?, nama_akun = ?, kategori = ?, tipe_laporan = ?, posisi_normal = ? WHERE kode_akun = ?");
        $stmt_upd->bind_param("ssssss", $kode_akun, $nama_akun, $kategori, $tipe_laporan, $posisi_normal, $kode_lama);
        
        if ($stmt_upd->execute()) {
            if ($kode_akun != $kode_lama) {
                $stmt_jurnal = $koneksi->prepare("UPDATE jurnal_umum SET kode_akun = ? WHERE kode_akun = ?");
                $stmt_jurnal->bind_param("ss", $kode_akun, $kode_lama);
                $stmt_jurnal->execute();
                $stmt_jurnal->close();
            }
            $pesan = "<script>Swal.fire({icon: 'success', title: 'Updated', text: 'Data akun diperbarui', timer: 1500, showConfirmButton: false});</script>";
        } else {
            $pesan = "<script>Swal.fire('Error', 'Gagal update: " . $stmt_upd->error . "', 'error');</script>";
        }
        $stmt_upd->close();
    }
}

// --- LOGIC HAPUS ---
if (isset($_GET['hapus'])) {
    $kode = $_GET['hapus'];
    
    $stmt_cek = $koneksi->prepare("SELECT COUNT(*) as total FROM jurnal_umum WHERE kode_akun = ?");
    $stmt_cek->bind_param("s", $kode);
    $stmt_cek->execute();
    $res_cek = $stmt_cek->get_result();
    $data_pakai = $res_cek->fetch_assoc();
    $stmt_cek->close();
    
    if ($data_pakai['total'] > 0) {
        $pesan = "<script>Swal.fire('Ditolak!', 'Akun ini sudah digunakan dalam transaksi jurnal. Tidak bisa dihapus demi integritas data.', 'warning');</script>";
    } else {
        $stmt_del = $koneksi->prepare("DELETE FROM akun_coa WHERE kode_akun = ?");
        $stmt_del->bind_param("s", $kode);
        if ($stmt_del->execute()) {
            $pesan = "<script>Swal.fire({icon: 'success', title: 'Terhapus', text: 'Akun berhasil dihapus', timer: 1500, showConfirmButton: false}).then(() => { window.location='master_akun.php'; });</script>";
        }
        $stmt_del->close();
    }
}

// --- AMBIL DATA ---
$query = "SELECT * FROM akun_coa ORDER BY kode_akun ASC";
$result = mysqli_query($koneksi, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Akun (COA) - PT. SURYA CERAH SEMESTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap');
        
        body { font-family: 'Google Sans', sans-serif; background-color: #F8FAFC; }
        
        /* Table Styling matching reference image */
        table.dataTable { border-collapse: separate !important; border-spacing: 0 12px !important; border: none !important; margin-top: -12px !important; }
        table.dataTable thead th { border: none !important; background: transparent; color: #9CA3AF; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding-bottom: 0px; padding-left: 1.5rem; }
        table.dataTable tbody tr { background-color: #ffffff; box-shadow: 0 2px 15px -3px rgba(0,0,0,0.03), 0 10px 20px -2px rgba(0,0,0,0.02); transition: all 0.2s ease; border-radius: 12px; }
        table.dataTable tbody tr:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        table.dataTable tbody td { border: none !important; padding: 1.2rem 1.5rem; vertical-align: middle; }
        table.dataTable tbody td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        table.dataTable tbody td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        
        /* Group Header Styling */
        table.dataTable tbody tr.group-header { background: transparent !important; box-shadow: none !important; border-radius: 0 !important; cursor: default; }
        table.dataTable tbody tr.group-header:hover { transform: none !important; box-shadow: none !important; background: transparent !important; }
        table.dataTable tbody tr.group-header td { padding: 2.5rem 0 0.5rem 0.5rem !important; border: none !important; }
        
        /* Custom Search Input */
        .dataTables_wrapper .dataTables_filter { margin-bottom: 1.5rem; float: left !important; text-align: left !important; }
        .dataTables_wrapper .dataTables_filter label { color: #9CA3AF; font-weight: 600; font-size: 13px; display: flex; items-center; }
        .dataTables_wrapper .dataTables_filter input { border-radius: 9999px; border: 1px solid #E5E7EB; padding: 10px 20px; outline: none; background: #fff; width: 300px; font-size: 14px; margin-left: 10px; color: #4B5563; font-weight: 500; box-shadow: 0 2px 5px rgba(0,0,0,0.02);}
        .dataTables_wrapper .dataTables_filter input:focus { border-color: #6366F1; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .dataTables_wrapper .dataTables_length { display: none; } /* Hide 'show entries' */
        .dataTables_wrapper .dataTables_info { color: #9CA3AF; font-size: 13px; font-weight: 500; padding-top: 1rem; }
        .dataTables_wrapper .dataTables_paginate { padding-top: 1rem; }
        
        /* Mobile First Responsive Styles */
        @media (max-width: 1024px) {
            table.dataTable { width: 100% !important; display: block; }
            table.dataTable thead { display: none !important; }
            table.dataTable tbody { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; width: 100%; }
            table.dataTable tbody tr { display: flex; flex-direction: column; margin-bottom: 0; padding: 1rem; position: relative; height: 100%; box-sizing: border-box; }
            table.dataTable tbody tr.group-header { display: block; padding: 0 !important; margin-bottom: 0.25rem; grid-column: span 2 / span 2; }
            table.dataTable tbody tr.group-header td { padding: 1.25rem 0 0.5rem 0 !important; }
            table.dataTable tbody td { display: flex; flex-direction: column; padding: 0.4rem 0 !important; align-items: flex-start; width: 100%; box-sizing: border-box; overflow: hidden; }
            table.dataTable tbody td:first-child { border-bottom: 1px dashed #E5E7EB !important; margin-bottom: 0.5rem; padding-bottom: 0.5rem !important; }
            table.dataTable tbody td:last-child { 
                position: relative; 
                top: auto; 
                right: auto; 
                flex-direction: row; 
                padding: 0 !important; 
                margin-top: 0.5rem; 
                justify-content: flex-end; 
                border-top: 1px solid #f3f4f6 !important; 
                padding-top: 0.75rem !important;
                width: 100%;
            }
            
            /* Mobile Field Labels */
            table.dataTable tbody td:nth-child(2)::before { content: "Kategori & Nama"; font-size: 8px; font-weight: 700; color: #9CA3AF; text-transform: uppercase; margin-bottom: 0.15rem; }
            table.dataTable tbody td:nth-child(3)::before { content: "Tipe Laporan"; font-size: 8px; font-weight: 700; color: #9CA3AF; text-transform: uppercase; margin-bottom: 0.15rem; }
            table.dataTable tbody td:nth-child(4)::before { content: "Posisi Normal"; font-size: 8px; font-weight: 700; color: #9CA3AF; text-transform: uppercase; margin-bottom: 0.15rem; }

            .dataTables_wrapper .dataTables_filter { float: none !important; margin-bottom: 1rem; }
            .dataTables_wrapper .dataTables_filter label { flex-direction: column; align-items: flex-start; width: 100%; display: flex; }
            .dataTables_wrapper .dataTables_filter input { width: 100%; margin-left: 0; margin-top: 0.5rem; box-sizing: border-box; }
            .dataTables_wrapper { overflow-x: hidden; width: 100%; box-sizing: border-box; }
        }
        
        /* Modal Overlay */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 50; padding: 1rem; }
    </style>
</head>
<body class="text-slate-800 bg-[#F8FAFC]">

    <?php include 'sidebar.php'; ?>
    <?php echo $pesan; ?>

    <div id="main-content" class="lg:ml-64 min-h-screen bg-[#F8FAFC] transition-all duration-300 relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-96 h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob pointer-events-none"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-pink-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000 pointer-events-none"></div>

        <!-- Main Content Area Container -->
        <div class="p-4 md:p-8 relative z-10">
            <!-- Page Title & Actions -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
                
                <!-- Left: Title -->
                <h2 class="text-xl md:text-[22px] font-bold text-slate-800 tracking-tight whitespace-nowrap">Daftar Akun (COA)</h2>
                
                <!-- Middle: Search Input -->
                <div class="w-full lg:flex-1 lg:max-w-md lg:mx-6">
                    <div class="relative flex items-center">
                        <i class="fas fa-search absolute left-4 text-gray-400 text-xs"></i>
                        <input type="text" id="customSearchInput" placeholder="Cari Akun..." class="w-full bg-[#f7f9fc] pl-10 pr-4 py-2 bg-white hover:bg-gray-50 border border-gray-200 rounded-full text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 transition-all shadow-sm placeholder-gray-400">
                    </div>
                </div>

                <!-- Right: Filters & Actions -->
                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Dropdown Filter Simulation (From Image) -->
                    <select id="filterKategori" class="bg-white border border-gray-200 text-gray-600 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:w-auto px-3 py-2.5 shadow-sm font-medium outline-none cursor-pointer">
                        <option value="">Semua Kategori</option>
                        <option value="Aset">Aset</option>
                        <option value="Kewajiban">Kewajiban</option>
                        <option value="Modal">Modal</option>
                        <option value="Pendapatan">Pendapatan</option>
                        <option value="Beban">Beban</option>
                    </select>
                    
                    <button onclick="bukaModal('add')" class="w-full sm:w-auto justify-center flex items-center gap-2 bg-[#4f46e5] border border-indigo-200 hover:border-indigo-300 hover:bg-[#6c65f1] hover:text-white text-white px-4 py-2.5 rounded-lg text-sm font-bold shadow-sm transition-all whitespace-nowrap">
                        <i class="fas fa-plus"></i> Tambah Akun
                    </button>
                </div>
            </div>

        <div class="relative z-10 w-full overflow-x-auto pb-10">
            <table id="tabelAkun" class="w-full text-left" style="width:100%">
                <thead>
                    <tr>
                        <th class="w-24">Kode Akun</th>
                        <th>Kategori & Nama</th>
                        <th>Tipe Laporan</th>
                        <th>Posisi Normal</th>
                        <th class="text-right pr-6">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <tr data-kategori="<?= $row['kategori'] ?>">
                        <td>
                            <span class="bg-slate-100 text-slate-500 font-mono text-[11px] font-extrabold px-3.5 py-1.5 rounded-full inline-flex items-center tracking-wider">
                                <?= $row['kode_akun'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-[13px] font-bold text-gray-800 mb-0.5"><?= $row['nama_akun'] ?></div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider"><?= $row['kategori'] ?></div>
                        </td>
                        <td>
                            <div class="text-[13px] font-bold text-gray-700 mb-0.5"><?= $row['tipe_laporan'] ?></div>
                            <div class="text-[10px] font-semibold text-gray-400">TIPE DOKUMEN</div>
                        </td>
                        <td>
                            <div class="text-[13px] font-bold text-gray-700 mb-0.5"><?= $row['posisi_normal'] ?></div>
                            <div class="text-[10px] font-semibold text-gray-400">SALDO NORMAL</div>
                        </td>
                        <td class="text-right pr-6 space-x-1.5 whitespace-nowrap">
                            <button onclick="editAkun('<?= $row['kode_akun'] ?>', '<?= $row['nama_akun'] ?>', '<?= $row['kategori'] ?>', '<?= $row['tipe_laporan'] ?>', '<?= $row['posisi_normal'] ?>')" 
                                    class="w-8 h-8 rounded-full bg-slate-50 border border-slate-200 text-slate-400 hover:bg-[#6366F1] hover:text-white hover:border-transparent transition-all shadow-sm" title="Edit">
                                <i class="fas fa-pen text-[10px]"></i>
                            </button>
                            <button onclick="konfirmasiHapus('<?= $row['kode_akun'] ?>')" 
                                    class="w-8 h-8 rounded-full bg-slate-50 border border-slate-200 text-slate-400 hover:bg-red-500 hover:text-white hover:border-transparent transition-all shadow-sm" title="Hapus">
                                <i class="fas fa-times text-[10px]"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalAkun" class="modal-overlay hidden">
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-2xl w-full max-w-lg transform transition-all scale-100 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 class="text-xl font-bold text-slate-800" id="modalTitle">Tambah Akun Baru</h3>
                <button onclick="tutupModal()" class="text-slate-400 hover:text-red-500 text-2xl">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="mode" id="formMode" value="add">
                <input type="hidden" name="kode_lama" id="kodeLama">

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Kode Akun</label>
                        <input type="number" name="kode_akun" id="inputKode" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-purple-500 outline-none transition" placeholder="Contoh: 51001" required>
                        <p class="text-xs text-slate-400 mt-1">1=Aset, 2=Hutang, 3=Modal, 4=Pendapatan, 5=Beban</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nama Akun</label>
                        <input type="text" name="nama_akun" id="inputNama" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:ring-2 focus:ring-purple-500 outline-none transition" placeholder="Contoh: Beban Iklan" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Kategori</label>
                            <select name="kategori" id="inputKategori" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 outline-none" required>
                                <option value="Aset">Aset</option>
                                <option value="Kewajiban">Kewajiban</option>
                                <option value="Modal">Modal</option>
                                <option value="Pendapatan">Pendapatan</option>
                                <option value="Beban">Beban</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipe Laporan</label>
                            <select name="tipe_laporan" id="inputTipe" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 outline-none" required>
                                <option value="Neraca">Neraca</option>
                                <option value="Laba Rugi">Laba Rugi</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Posisi Normal</label>
                        <select name="posisi_normal" id="inputPosisi" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 outline-none" required>
                            <option value="Debit">Debit (Aset & Beban)</option>
                            <option value="Kredit">Kredit (Hutang, Modal, Pendapatan)</option>
                        </select>
                    </div>
                </div>

                <div class="mt-8 flex gap-3 justify-end flex-col-reverse md:flex-row">
                    <button type="button" onclick="tutupModal()" class="w-full md:w-auto px-5 py-3 md:py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium transition">Batal</button>
                    <button type="submit" name="simpan_akun" class="w-full md:w-auto px-6 py-3 md:py-2.5 rounded-xl bg-[#6366F1] text-white hover:bg-[#4F46E5] font-bold shadow-lg transition transform hover:-translate-y-1">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var table = $('#tabelAkun').DataTable({
                responsive: true,
                "pageLength": 100,
                "language": { "search": "", "searchPlaceholder": "Cari Akun..." },
                "dom": 'rtp', // Removed 'f' to hide default search
                "order": [[0, 'asc']],
                "drawCallback": function ( settings ) {
                    var api = this.api();
                    var last = null;
         
                    api.rows( {page:'current'} ).every( function ( rowIdx, tableLoop, rowLoop ) {
                        var rowNode = this.node();
                        var kategori = $(rowNode).data('kategori');
                        
                        if ( last !== kategori ) {
                            $(rowNode).before(
                                '<tr class="group-header"><td colspan="5"><h2 class="text-lg font-extrabold text-slate-800">' + kategori + '</h2></td></tr>'
                            );
                            last = kategori;
                        }
                    });
                }
            });
            
            // Bind Custom Search Input
            $('#customSearchInput').on('keyup', function() {
                table.search(this.value).draw();
            });

            // Bind Custom Category Filter
            $('#filterKategori').on('change', function() {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                // The category text is in column 1
                table.column(1).search(val ? val : '', true, false).draw();
            });
            
            // Auto select logic helper
            $('#inputKode').on('input', function() {
                let val = $(this).val();
                let firstChar = val.charAt(0);
                
                if(firstChar === '1') { 
                    $('#inputKategori').val('Aset'); $('#inputTipe').val('Neraca'); $('#inputPosisi').val('Debit'); 
                } else if(firstChar === '2') { 
                    $('#inputKategori').val('Kewajiban'); $('#inputTipe').val('Neraca'); $('#inputPosisi').val('Kredit'); 
                } else if(firstChar === '3') { 
                    $('#inputKategori').val('Modal'); $('#inputTipe').val('Neraca'); $('#inputPosisi').val('Kredit'); 
                } else if(firstChar === '4') { 
                    $('#inputKategori').val('Pendapatan'); $('#inputTipe').val('Laba Rugi'); $('#inputPosisi').val('Kredit'); 
                } else if(firstChar === '5') { 
                    $('#inputKategori').val('Beban'); $('#inputTipe').val('Laba Rugi'); $('#inputPosisi').val('Debit'); 
                }
            });
        });

        function bukaModal(mode) {
            document.getElementById('modalAkun').classList.remove('hidden');
            document.getElementById('formMode').value = mode;
            
            if(mode == 'add') {
                document.getElementById('modalTitle').innerText = "Tambah Akun Baru";
                document.getElementById('inputKode').value = '';
                document.getElementById('inputNama').value = '';
                document.getElementById('kodeLama').value = '';
            }
        }

        function editAkun(kode, nama, kat, tipe, posisi) {
            bukaModal('edit');
            document.getElementById('modalTitle').innerText = "Edit Akun: " + nama;
            document.getElementById('inputKode').value = kode;
            document.getElementById('kodeLama').value = kode;
            document.getElementById('inputNama').value = nama;
            document.getElementById('inputKategori').value = kat;
            document.getElementById('inputTipe').value = tipe;
            document.getElementById('inputPosisi').value = posisi;
        }

        function tutupModal() {
            document.getElementById('modalAkun').classList.add('hidden');
        }

        function konfirmasiHapus(kode) {
            Swal.fire({
                title: 'Hapus Akun?',
                text: "Akun " + kode + " akan dihapus permanen.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?hapus=' + kode;
                }
            })
        }
    </script>
</body>
</html>
