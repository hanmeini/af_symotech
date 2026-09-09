# af_symotech — AGENTS.md

**Proyek:** ERP & Sistem Penempatan Pekerja Migran Indonesia (P3MI / TKI) untuk PT Arni Family
**URL Live:** https://af.symotech.id
**Stack:** PHP 8.2 native + MySQL 8.0 (prosedural, tanpa framework, PDO & mysqli)

---

## Profil & Domain Bisnis

PT Arni Family adalah perusahaan penempatan pekerja migran (P3MI/TKI) ke negara tujuan seperti Taiwan, Hong Kong, Singapura, Malaysia, dan negara lainnya.
Sistem ini menangani seluruh siklus proses pekerja migran mulai dari pendaftaran, medis, berkas resmi pemerintah, visa, hingga keberangkatan dan keuangan biaya proses per pekerja.

---

## Alur / Pipeline Proses TKI (Pekerja Migran)

Setiap pekerja migran (`pendaftaran`) melewati tahapan dokumen dan operasional berikut secara berurutan:

1. **Pendaftaran (`pendaftaran.php`):** Registrasi data diri NIK, nama, negara tujuan, cabang pendaftaran, dan sponsor/PL. Dilengkapi validasi NIK 16 digit (cek blacklist `masalah`, duplikat `aktive`, dan reaktivasi `nonaktive`).
2. **Kesehatan 1 / Medical Pra (`belumkesehatan.php`, `kesehatan.php`):** Pemeriksaan kesehatan tahap awal (Fit / Unfit / Pending). TKI Unfit dapat dibatalkan prosesnya.
3. **Keluar ID SISKOP2MI (`keluarid.php`):** Penerbitan nomor registrasi ID resmi dari Kementerian Ketenagakerjaan / BP2MI.
4. **Pembuatan Paspor (`pasport.php`):** Rekomendasi dan penerbitan paspor di Kantor Imigrasi.
5. **Psikotes (`sikotes.php`):** Uji psikologi pekerja migran.
6. **Uji Kompetensi (`ujikom.php`):** Sertifikasi kompetensi BNSP/LSP (misal: Caregiver, Housemaid, Operator Pabrik).
7. **Kesehatan 2 / Medical Full (`belumkesehatan2.php`, `kesehatan2.php`):** Pemeriksaan kesehatan lengkap sebelum pengajuan visa.
8. **Job Order (`belumjob.php`, `dapatjob.php`, `saatjob.php`):** Pencocokan pekerja dengan majikan/employer di negara tujuan.
9. **SUHAN & TETO (Khusus Taiwan):**
   - `suhan.php`: Pengurusan Surat Izin Perekrutan Tenaga Kerja dari Kemnaker Taiwan.
   - `teto.php`: Pengajuan visa kerja ke kantor perwakilan Taipei Economic and Trade Office (TETO).
10. **Asuransi Pra (`asuransipra.php`):** Asuransi perlindungan sebelum keberangkatan.
11. **OPP / E-KTKLN (`opp.php`):** Orientasi Pra Pemberangkatan dan penerbitan Elektronik Kartu Tenaga Kerja Luar Negeri.
12. **Penerbangan / Keberangkatan (`penerbangan.php`, `sudahterbang.php`):** Tiket pesawat, jadwal terbang (flight tag D), dan pelepasan bandara.
13. **Setelah Penempatan (`setelahpenempatan.php`):** Pemantauan masa kerja di negara tujuan.
14. **Kasus Khusus:**
    - `masalah.php`: Penanganan TKI bermasalah/aduan di luar negeri.
    - `mundur.php`: Pencatatan TKI yang mengundurkan diri / batal proses.

---

## Modul Keuangan & Biaya TKI

- **Biaya Per TKI (`biayapertki.php`):** Laporan buku besar rincian pengeluaran/biaya proses per individu pekerja (tabel `nikby` berelasi ke master `jenisbiaya`). Mendukung filter status klaim, tanggal, cabang, dan pencarian NIK/Nama.
- **Kas & Bank TKI (`kastki.php`, `banktki.php`):** Pencatatan penerimaan/pengeluaran kas/bank yang dialokasikan langsung ke NIK pekerja tertentu.
- **Fee Sponsor (`feesponsor.php`, `sponsorin.php`):** Perhitungan komisi untuk sponsor/Petugas Lapangan (PL).
- **Akuntansi Umum:** `coa.php`, `jurnal.php`, `ledger.php`, `kas.php`, `bank.php`, `neraca.php`, `lr.php` (Laba Rugi).

---

## Tabel-Tabel Kunci Database (`symotech_af`)

| Tabel | Fungsi |
|---|---|
| `me` | Akun pengguna (`userid`, `username`, `password`, `location`, `bagian`, `jabatan`) |
| `pendaftaran` | Master data pekerja migran / TKI (`nik`, `nama`, `negara`, `cabang`, `sponsor`, `status`, kolom tanggal tiap proses `tanggal_*`, `edit`, `minta`) |
| `location` | Master Cabang (`idl`, `nama_cabang`) — ID 1 / HO adalah Kantor Pusat |
| `sponsor` | Master data sponsor / agen lapangan (`nik`, `nama`) |
| `nikby` | Transaksi rincian biaya yang dikeluarkan per TKI (`nik`, `nom`, `tanggal`, `klaim`, `kode_transaksi`, `kodeby`) |
| `jenisbiaya` | Master kategori biaya TKI (`id`, `namaby`) |
| `masalah` | Daftar pekerja migran bermasalah |
| `mundur` | Daftar pekerja migran yang mengundurkan diri |
| `coa` | Master Chart of Accounts (COA) |
| `jurnal` | Jurnal transaksi keuangan |
| `menu` | Daftar menu aplikasi |
| `akses_bagian`, `akses_jabatan`, `akses_location_menu` | Matriks RBAC hak akses menu per bagian, jabatan, dan cabang |

---

## Sistem Izin Edit Data (Approval Workflow)

Untuk mencegah perubahan data sepihak oleh cabang:
- Jika cabang ingin mengubah data yang sudah terkunci, sistem menggunakan mekanisme **`minta = 1`** via `reqedit_*.php`.
- HO / Admin Pusat menyetujui izin edit di `izinedit.php` (mengubah kolom `edit = 'ya' / 'bisa'`).
- Setelah cabang selesai mengedit, flag izin dikembalikan menjadi terkunci.

---

## Aturan Deployment & CI/CD (KRITIS)

- **Push ke branch `main`:** Otomatis mentrigger GitHub Actions (`.github/workflows/deploy.yml`) yang melakukan curl webhook ke `https://af.symotech.id/gitpull.php`.
- **CI Lint Wajib Lolos:** GitHub Actions menjalankan:
  ```bash
  find . -name "*.php" -print0 | xargs -0 -n1 php -l || exit 1
  ```
  **Setiap file PHP yang dimodifikasi WAJIB lulus `php -l` tanpa syntax error.** Jika ada satu file saja yang error, deploy otomatis digagalkan.
- **Gitpull Manual:** Dapat diakses di browser melalui `gitpull.php`.
