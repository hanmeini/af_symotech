# Catatan Audit Akuntansi & Daftar Backlog Pembaruan — af_symotech

**Tanggal Audit:** 10 September 2026  
**Aplikasi:** ERP & Sistem Penempatan PMI (PT Arni Family) — `af.symotech.id`  
**Status:** Dicatat untuk rencana pembaruan berikutnya (*pending update*)

---

## Ringkasan Eksekutif

Audit dilakukan menyeluruh terhadap modul keuangan dan akuntansi di `af_symotech`.  
Secara umum, pencatatan jurnal otomatis dari alur operasional 15 tahapan TKI (Medical, Paspor, Ujikom, Tiket, dll.) dan laporan buku besar/neraca saldo/laba rugi sudah memiliki fondasi yang berjalan. Namun, terdapat kesenjangan fitur (*UX/UI gap*) dibanding sistem saudara (`symotec2_jns` & `masbox_symotech`), beberapa baris debug output yang tertinggal di backend handler, serta modul Fee Sponsor yang masih berupa file kosong (*stub*).

---

## Rincian Temuan & Rencana Pembaruan

### 1. Prioritas 1: Modernisasi UI & Input COA Searchable (Select2 + Autofocus)

#### A. Form Jurnal & Kas/Bank Transaksi Umum
* **File Terkait:**
  - `jurnal.php` (Jurnal Umum)
  - `bankin.php` (Bank Masuk)
  - `bankout.php` (Bank Keluar)
  - `kasin.php` (Kas Masuk)
  - `kas.php` (Kas Keluar)
* **Kondisi Saat Ini:**
  - Menggunakan elemen `<select name="coa[]">` HTML biasa dengan ratusan pilihan akun tanpa fitur pencarian.
  - Tampilan form menggunakan background gradasi ungu/pink lama (`#ea90e6, #6c6cc9`).
* **Rencana Perbaikan:**
  - Mengintegrasikan library **Select2** pada dropdown COA.
  - Menerapkan **autofocus otomatis** pada kolom pencarian saat dropdown Select2 dibuka:
    ```javascript
    $(document).on('select2:open', () => {
        let searchField = document.querySelector('.select2-search__field');
        if (searchField) searchField.focus();
    });
    ```
  - Re-inisialisasi Select2 secara otomatis saat user menambah baris transaksi baru (*dynamic row*).
  - Modernisasi tema visual: Layout kartu putih bersih, tipografi Google Font *Inter*, indikator balance hijau/merah yang elegan.

#### B. Form Transaksi Kas/Bank Khusus TKI
* **File Terkait:**
  - `banktki.php` (Bank Khusus Biaya/Penerimaan TKI)
  - `kastki.php` (Kas Khusus Biaya/Penerimaan TKI)
* **Kondisi Saat Ini:**
  - Dropdown akun COA masih standar tanpa pencarian.
* **Rencana Perbaikan:**
  - Menerapkan Select2 searchable COA + autofocus yang sama seperti form kas/bank umum.

#### C. Laporan Buku Besar (`ledger.php`)
* **File Terkait:** `ledger.php`
* **Kondisi Saat Ini:**
  - Input COA menggunakan teks biasa: `<input type="text" name="coa" placeholder="Masukkan COA">`. User harus mengingat atau menghafal nomor akun secara manual.
* **Rencana Perbaikan:**
  - Ubah input menjadi Select2 Searchable Dropdown yang memuat seluruh daftar akun dari tabel `coa` (format: `Kode - Nama Akun`).

---

### 2. Prioritas 2: Pembersihan Bug Debugging & Perbaikan Backend Handler

#### A. Pembersihan Teks Debug Output `Hasil POST`
* **File Terkait:**
  - `save_bankin.php` (baris 82–95)
  - `save_kasin.php` (baris 82–95)
  - `save_jurnalbank.php` (baris 81–94)
  - `save_jurnalkas.php` (baris 80–93)
* **Kondisi Saat Ini:**
  Terdapat kode dump POST yang mencetak HTML sebelum redirect:
  ```php
  echo "<h2>Hasil POST</h2>";
  echo "<pre>";
  echo "Tanggal: " . htmlspecialchars($_POST['tanggal']) . "<br>";
  ...
  echo "</pre>";
  ```
  Hal ini menyebabkan layar menampilkan teks kasar sebelum beralih ke halaman cetak.
* **Rencana Perbaikan:**
  Hapus blok dump debug tersebut agar alur penyimpanan langsung mengarah ke cetak bukti transaksi tanpa polusi tampilan.

#### B. Perbaikan Tipe Data Parameter Location di `save_jurnal.php`
* **File Terkait:** `save_jurnal.php` (baris 76, 97, 109)
* **Kondisi Saat Ini:**
  Di `jurnal.php`, input dikirim sebagai `location[]` (array), namun di `save_jurnal.php` langsung dimasukkan ke `bind_param("...s...", ..., $location, ...)` sebagai array, berpotensi memicu notice/error `Array to string conversion` pada PHP 8.2.
* **Rencana Perbaikan:**
  Normalisasi pengambilan nilai lokasi:
  ```php
  $location_val = is_array($_POST['location']) ? ($_POST['location'][0] ?? '') : ($_POST['location'] ?? '');
  ```

#### C. Pembersihan Duplikasi Koneksi Database
* **File Terkait:** `jurnal.php`, `bankin.php`, `bankout.php`, `kasin.php`, `kas.php`, `banktki.php`, `kastki.php`
* **Kondisi Saat Ini:**
  Terdapat inisialisasi ganda beruntun untuk `$conn = new mysqli(...)` dan `$pdo = new PDO(...)` di bagian atas file.
* **Rencana Perbaikan:**
  Satukan dan rapikan pembukaan koneksi database secara efisien.

---

### 3. Prioritas 3: Pembangunan Modul Rekap Fee Sponsor (`feesponsor.php`)

* **File Terkait:** `feesponsor.php`, berelasi dengan `fee.php` (`tariffee`), `sponsorin.php` (`sponsor`), dan `pendaftaran`.
* **Kondisi Saat Ini:**
  File `feesponsor.php` saat ini masih berupa file *stub* kosong (hanya 21 baris teks: *"Halaman ini dibuat otomatis oleh sistem"*).
* **Rencana Pengembangan:**
  1. Buat antarmuka laporan rekapitulasi komisi / fee sponsor.
  2. Logika perhitungan:
     - Menggabungkan data calon TKI di tabel `pendaftaran` yang memiliki sponsor (`p.sponsor`) dan negara tujuan (`p.negara`).
     - Mencocokkan nilai komisi per kepala berdasarkan master tarif di tabel `tariffee` (`fee.php`).
     - Menyediakan filter per sponsor, filter rentang tanggal pendaftaran/terbang, dan filter status (Proses, Sudah Terbang, Batal).
     - Menampilkan total hak komisi sponsor yang sudah dibayarkan vs yang belum dibayarkan.

---

## Tabel Checklist Rencana Pembaruan

| No | Modul / File | Kategori | Deskripsi Pekerjaan | Status |
|:--:|:---|:---|:---|:--:|
| 1 | `jurnal.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 2 | `bankin.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 3 | `bankout.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 4 | `kasin.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 5 | `kas.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 6 | `banktki.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 7 | `kastki.php` | UX / UI | Select2 searchable COA + autofocus + modern UI | Pending |
| 8 | `ledger.php` | UX / UI | Ganti input text COA menjadi Select2 dropdown | Pending |
| 9 | `save_bankin.php` | Backend | Hapus debug dump `echo <h2>Hasil POST</h2>` | Pending |
| 10 | `save_kasin.php` | Backend | Hapus debug dump `echo <h2>Hasil POST</h2>` | Pending |
| 11 | `save_jurnalbank.php` | Backend | Hapus debug dump `echo <h2>Hasil POST</h2>` | Pending |
| 12 | `save_jurnalkas.php` | Backend | Hapus debug dump `echo <h2>Hasil POST</h2>` | Pending |
| 13 | `save_jurnal.php` | Backend | Fix array-to-string conversion pada `$location` | Pending |
| 14 | `feesponsor.php` | Fitur Baru | Bangun laporan rekapitulasi fee komisi sponsor | Pending |
