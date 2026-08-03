<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://af.symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi dari config.php
require_once 'config.php';



// Ambil variabel dari environment
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");



// Buat koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Periksa koneksi pertama
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}



// Ambil ID dari URL atau POST
$asset_id = isset($_GET['id']) ? $_GET['id'] : '';

// Query untuk mengambil data aset
$sql = "SELECT id, kode, nama, location, devisi, keterangan, lampiran, beli FROM aset WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $asset_id);
$stmt->execute();

// Bind result ke variabel
$stmt->bind_result($id, $kode, $nama, $location, $devisi, $keterangan, $lampiran, $beli);

// Fetch hasil
if ($stmt->fetch()) {
    $asset = [
        'id' => $id,
        'kode' => $kode,
        'nama' => $nama,
        'location' => $location,
        'devisi' => $devisi,
        'keterangan' => $keterangan,
        'lampiran' => $lampiran,
        'beli' => $beli
    ];
} else {
    echo "Asset not found.";
    exit();
}

// Tutup statement pertama sebelum menjalankan query baru
$stmt->close();

// Query untuk mengambil total susut berdasarkan kode aset
$sql_susut = "SELECT SUM(susut) AS total_susut FROM susut WHERE kode = ?";
$stmt_susut = $conn->prepare($sql_susut);
$stmt_susut->bind_param("s", $kode);
$stmt_susut->execute();
$stmt_susut->bind_result($total_susut);

// Ambil hasil total susut
$stmt_susut->fetch();
$asset['total_susut'] = $total_susut !== null ? $total_susut : 0;

$stmt_susut->close();

// Tutup koneksi

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Jurnal</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
     <style>
       body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }

        .table-container {
            margin: 5px auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            max-width: 800px;
        }

        h1 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0px 0;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table th {
            background-color: #f9f9f9;
            color: #555;
        }

        .total {
            margin: 20px 0;
            font-size: 0.8em;
        }

        .total h3 {
            color: #555;
        }

        form {
            margin-top: 0px;
        }

        label {
            display: block;
            font-size: 0.9em;
            margin-bottom: 0px;
        }

        input[type="text"],select {
            width: 100%;
            padding: 2px;
            margin-bottom: 0px;
            border: 1px solid #ddd;
            border-radius: 0px;
        }

        .button {
            padding: 10px 20px;
            background-color: #28a745;
            color: blue;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .button:hover {
            background-color: #218838;
        }

        .home-icon1, .left-icon {
            text-decoration: none;
            font-size: 1.5em;
            color: #555;
        }

        .home-icon1 {
            margin-right: 10px;
        }

        .no-data {
            color: red;
            text-align: center;
        }
         input[readonly] {
            background-color: grey;
            color: white; /* Warna teks putih untuk kontras dengan marun */
            border: 0px solid #800000; /* Tambahkan border agar lebih jelas */
        }
      .form-group {
            display: flex;
            flex-direction: column;
            flex: 1 1 calc(50% - 20px); /* Mengatur 2 kolom */
        }
  .form-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px; /* Jarak antar elemen */
        }
  th:nth-child(1),
        td:nth-child(2) {
            width: 5%; /* Kolom No. */
        }

        th:nth-child(2),
        td:nth-child(2) {
            width: 20%; /* Kolom Account Code */
        }

        th:nth-child(3),
        td:nth-child(3) {
            width: 30%; /* Kolom Account Name */
        }

        th:nth-child(4),
        td:nth-child(4) {
            width: 20%; /* Kolom Total Bank */
        }

        th:nth-child(5),
        td:nth-child(5) {
            width: 25%; /* Kolom Nominal */
        }
   #jurnal_table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Atur lebar kolom */
        #jurnal_table th:nth-child(1), 
        #jurnal_table td:nth-child(1) {
            width: 5%; /* Kolom No. */
        }

        #jurnal_table th:nth-child(2), 
        #jurnal_table td:nth-child(2) {
            width: 1%; /* Kolom Account Code */
        }

        #jurnal_table th:nth-child(3), 
        #jurnal_table td:nth-child(3) {
            width: 30%; /* Kolom Account Name */
        }

        #jurnal_table th:nth-child(4), 
        #jurnal_table td:nth-child(4) {
            width: 20%; /* Kolom Total Bank */
        }

        #jurnal_table th:nth-child(5), 
        #jurnal_table td:nth-child(5) {
            width: 20%; /* Kolom Nominal */
        }

        /* Styling untuk header */
        #jurnal_table th {
            background-color: #f4f4f4;
            font-weight: bold;
            text-align: left;
            padding: 8px;
        }

        /* Styling untuk isi tabel */
        #jurnal_table td {
            padding: 8px;
            border: 1px solid #ddd;
        }

        /* Styling untuk footer */
        #jurnal_table tfoot td {
            font-weight: bold;
            text-align: right;
            background-color: #e9e9e9;
        }
</style>

</head>
<body>
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <div class="container">
        <h2>Form Jurnal Penjualan Aset</h2>
        <form id="formJurnal" action="save_asetjual.php" method="post" enctype="multipart/form-data">
               <div class="form-container">
                      <div class="form-group">
            <label for="tanggal">tanggal:</label>
            <input type="date" id="tanggal" name="tanggal" required>
       </div>
            <div class="form-group">

            <!-- Asset Details from the Database -->
            <label for="id">ID:</label>
            <input type="text" id="id" name="id" value="<?php echo $asset['id']; ?>" readonly>
       </div>
            <div class="form-group">
            <label for="kode">Kode:</label>
            <input type="text" id="kode" name="kode" value="<?php echo $asset['kode']; ?>" readonly>
       </div>
            <div class="form-group">
            <label for="nama">Nama:</label>
            <input type="text" id="nama" name="nama" value="<?php echo $asset['nama']; ?>" readonly>
       </div>
            <div class="form-group">
            <label for="location">Location:</label>
            <input type="text" id="location" name="location" value="<?php echo $asset['location']; ?>" readonly>
       </div>
            <div class="form-group">
            <label for="devisi">Devisi:</label>
            <input type="text" id="devisi" name="devisi" value="<?php echo $asset['devisi']; ?>" readonly>
                   </div>
            <div class="form-group">
            <label for="keterangan">Keterangan:</label>
            <input type="text" id="keterangan" name="keterangan" 
       value="penjualan/<?php echo $asset['kode']; ?>/<?php echo $asset['nama']; ?>/<?php echo $asset['location']; ?>/<?php echo $asset['devisi']; ?>" 
      required >

                   </div>
            <div class="form-group">
            <label for="lampiran">Lampiran:</label>
            <input type="text" id="lampiran" name="lampiran" value="<?php echo $asset['lampiran']; ?>" readonly>
                   </div>
            
            
            <table id="jurnal_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Account__Code</th>
                        <th>Account Name</th>
                       
                        <th>debet</th>
                  <th>kredit</th>
                      
               
                    </tr>
                </thead>
                <tbody>
                    <td>1</td>
                        <td>
                           <select name="coa[]" required onchange="getAccountName(this)">
    <option value="">Pilih COA ASET</option>
    <?php
    
      $servername = 'andria-db.id.domainesia.com';
        $username = 'symotech_1';
        $password = 'Pemuda5758';
        $database = 'symotech_accounting';
        
    // Koneksi ke database
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Kueri untuk mendapatkan data COA
    $sql = "SELECT account_code, account_name 
            FROM coa 
            WHERE layer = 4 AND account_code LIKE '161%'";
    $result = $conn->query($sql);

    // Periksa hasil kueri
    if ($result->num_rows > 0) {
        // Loop melalui data hasil kueri
        while ($row = $result->fetch_assoc()) {
            $account_code = htmlspecialchars($row["account_code"]);
            $account_name = htmlspecialchars($row["account_name"]);
            echo "<option value='$account_code'>$account_code - $account_name</option>";
        }
    } else {
        echo "<option value='' disabled>Tidak ada COA tersedia</option>";
    }

    // Tutup koneksi database

    ?>
</select>

                        </td>
                        <td>
                            <input type="text" name="account_name[]" readonly required>
                        </td>
                <td><input type="hidden" name="debet[]" min="0" value="0" readonly></td>
<td id="beli">
    <span id="beli"><?php echo $asset['beli']; ?></span>
    <input type="hidden" id="beli" name="kredit[]" value="<?php echo $asset['beli']; ?>" readonly>
</td>

                  
                  
                    </tr>
                    
                    <td>2</td>
                        <td>
                           <select name="coa[]" required onchange="getAccountName(this)">
    <option value="">Pilih COA AKUM SUSUT</option>
    <?php
    
      $servername = 'andria-db.id.domainesia.com';
        $username = 'symotech_1';
        $password = 'Pemuda5758';
        $database = 'symotech_accounting';
        
    // Koneksi ke database
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Kueri untuk mendapatkan data COA
    $sql = "SELECT account_code, account_name 
            FROM coa 
            WHERE layer = 4 AND account_code LIKE '162%'";
    $result = $conn->query($sql);

    // Periksa hasil kueri
    if ($result->num_rows > 0) {
        // Loop melalui data hasil kueri
        while ($row = $result->fetch_assoc()) {
            $account_code = htmlspecialchars($row["account_code"]);
            $account_name = htmlspecialchars($row["account_name"]);
            echo "<option value='$account_code'>$account_code - $account_name</option>";
        }
    } else {
        echo "<option value='' disabled>Tidak ada COA tersedia</option>";
    }

    // Tutup koneksi database

    ?>
</select>

                        </td>
                        <td>
                            <input type="text" name="account_name[]" readonly  required>
                        </td>
<td id="susut">
    <span id="susut"><?php echo $asset['total_susut']; ?></span>
    <input type="hidden" id="susut" name="debet[]" value="<?php echo $asset['total_susut']; ?>" readonly>
</td>

                    <td><input type="hidden" name="kredit[]" min="0" value="0" readonly></td>
                     </tr>
                    
                    <td>3</td>
                        <td>
                           <select name="coa[]" required onchange="getAccountName(this)">
    <option value="">Pilih COA BANK/KAS</option>
    <?php
    
      $servername = 'andria-db.id.domainesia.com';
        $username = 'symotech_1';
        $password = 'Pemuda5758';
        $database = 'symotech_accounting';
        
    // Koneksi ke database
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Kueri untuk mendapatkan data COA
    $sql = "SELECT account_code, account_name 
            FROM coa 
            WHERE layer = 4 AND account_code LIKE '11%'";
    $result = $conn->query($sql);

    // Periksa hasil kueri
    if ($result->num_rows > 0) {
        // Loop melalui data hasil kueri
        while ($row = $result->fetch_assoc()) {
            $account_code = htmlspecialchars($row["account_code"]);
            $account_name = htmlspecialchars($row["account_name"]);
            echo "<option value='$account_code'>$account_code - $account_name</option>";
        }
    } else {
        echo "<option value='' disabled>Tidak ada COA tersedia</option>";
    }

    // Tutup koneksi database

    ?>
</select>

                        </td>
                        <td>
                            <input type="text" name="account_name[]" readonly required>
                        </td>

                   <td> <input 
    type="text" 
    name="debet[]" 
    min="0" 
    value="0" 
    oninput="formatNumber(this)" 
    onfocus="removeFormatting(this)" 
    onblur="applyFormatting(this)" 
    style="text-align: right;">
</td>

                    <td><input type="hidden" name="kredit[]" min="0" value="0" readonly></td>
                    
                    </tr>
                    
                    <td>4</td>
                        <td>
                            <select name="coa[]" required onchange="getAccountName(this)">
    <option value="">Pilih COA L/R</option>
    <?php
    
      $servername = 'andria-db.id.domainesia.com';
        $username = 'symotech_1';
        $password = 'Pemuda5758';
        $database = 'symotech_accounting';
        
    // Koneksi ke database
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Kueri untuk mendapatkan data COA
    $sql = "SELECT account_code, account_name 
            FROM coa 
       WHERE layer = 4 AND (account_code = '81102' OR account_code = '82003')";

    $result = $conn->query($sql);

    // Periksa hasil kueri
    if ($result->num_rows > 0) {
        // Loop melalui data hasil kueri
        while ($row = $result->fetch_assoc()) {
            $account_code = htmlspecialchars($row["account_code"]);
            $account_name = htmlspecialchars($row["account_name"]);
            echo "<option value='$account_code'>$account_code - $account_name</option>";
        }
    } else {
        echo "<option value='' disabled>Tidak ada COA tersedia</option>";
    }

    // Tutup koneksi database

    ?>
</select>

                        </td>
                        <td>
                            <input type="text" name="account_name[]" readonly required >
                        </td>

                 <td>
 <input 
    type="text" 
    name="debet[]" 
    min="0" 
    value="0" 
    oninput="formatNumber(this)" 
    onfocus="removeFormatting(this)" 
    onblur="applyFormatting(this)" 
    style="text-align: right;">
</td>
<td>
 <input 
    type="text" 
    name="kredit[]" 
    min="0" 
    value="0" 
    oninput="formatNumber(this)" 
    onfocus="removeFormatting(this)" 
    onblur="applyFormatting(this)" 
    style="text-align: right;">
</td>
                    
                </tbody>
                <tfoot>
                    <tr>
                        
                          <td colspan="3" style="text-align: right;">Total</td>
                        <td id="total_debet">0</td>
                        <td id="total_kredit">0</td>
                     
                         <td id="balance" class="balance"> 0</td>
                         
                    </tr>
            
                    </tr>
                </tfoot>
            </table>
             <br>
            <br>
           <input type="submit" id="submit_button" value="Submit">

            
            
         
        </form>
    </div>

   <script>

 // Hitung total debet, kredit, dan balance
function calculateTotal() {
    var table = document.getElementById("jurnal_table");
    var rows = table.querySelectorAll("tbody tr"); // Correctly target all rows in tbody
    var totalDebet = 0;
    var totalKredit = 0;

    rows.forEach(function (row) {
        var debetInput = row.querySelector('input[name="debet[]"]');
        var kreditInput = row.querySelector('input[name="kredit[]"]');

        var debetValue = debetInput ? parseFloat(debetInput.value.replace(/,/g, '').trim()) : 0;
        var kreditValue = kreditInput ? parseFloat(kreditInput.value.replace(/,/g, '').trim()) : 0;

        if (!isNaN(debetValue)) totalDebet += debetValue;
        if (!isNaN(kreditValue)) totalKredit += kreditValue;
    });

    var balance = totalDebet - totalKredit;

    function formatRibuan(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    document.getElementById("total_debet").innerText = formatRibuan(totalDebet.toFixed(2));
    document.getElementById("total_kredit").innerText = formatRibuan(totalKredit.toFixed(2));
    document.getElementById("balance").innerText = balance === 0 ? "0" : formatRibuan(balance.toFixed(2));
}

// Event listener to trigger total calculation on input change
document.querySelectorAll('input[name="debet[]"], input[name="kredit[]"]').forEach(function (input) {
    input.addEventListener('input', calculateTotal);
});









    function getAccountName(select) {
            var accountCode = select.value;
            var accountNameInput = select.parentNode.nextElementSibling.querySelector('input[name="account_name[]"]');

            // Send AJAX request to fetch account name based on account code
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    accountNameInput.value = xhr.responseText;
                }
            };
            xhr.open("GET", "get_account_name.php?account_code=" + accountCode, true);
            xhr.send();
        }
         // Panggil calculateTotal() setelah dokumen dimuat
    window.onload = function() {
        addRow();
        calculateTotal(); // Panggil calculateTotal() untuk memastikan nilai saldo sudah diperbarui
    };

  // Hapus format ribuan saat fokus
  function removeFormatting(input) {
    input.value = input.value.replace(/,/g, ''); // Hapus koma
  }

  // Tambahkan format ribuan saat blur
  function applyFormatting(input) {
    let value = input.value.replace(/,/g, ''); // Hapus koma untuk mendapatkan nilai mentah
    if (value !== '' && !isNaN(value)) {
      input.value = formatWithCommas(value); // Tambahkan format ribuan
    }
  }

  // Format angka besar saat pengguna mengetik
  function formatLargeNumber(input) {
    let value = input.value.replace(/,/g, ''); // Hapus koma
    // Pastikan hanya angka yang diizinkan
    if (/^\d*$/.test(value)) {
      input.value = formatWithCommas(value); // Tampilkan dengan format ribuan
    } else {
      input.value = input.value.slice(0, -1); // Hapus karakter non-angka terakhir
    }
  }

  // Fungsi untuk menambahkan format ribuan
  function formatWithCommas(value) {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ','); // Tambahkan koma
  }

  // Konfirmasi sebelum submit jurnal
  document.getElementById('formJurnal').addEventListener('submit', function(event) {
    const confirmation = window.confirm("Apakah Anda sudah yakin dengan jurnalnya? sudah cek loction dan devisi sesuai penerima kas/bank?");
    if (!confirmation) {
      event.preventDefault();
      return;
    }

    // Validasi apakah debet dan kredit seimbang
    const balance = parseFloat(document.getElementById('balance').innerText.replace(/,/g, ''));
    if (balance !== 0) {
      event.preventDefault();
      alert("Total debet dan kredit tidak seimbang. Harap perbaiki.");
    }
  });
</script>



</body>
</html>
