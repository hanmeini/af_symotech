<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://janico.symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (false /* referer bypassed */) {
    header("Location: https://janico.symotech.id");
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

// Ambil variabel dari environment
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");



// Koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}



// Ambil filter dari URL (jika ada)
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

// Mulai query
$filter_sql = "";
if (!empty($filter)) {
    // Cek nama supplier di database kedua
    $filter_escaped = $conn->real_escape_string($filter);
    $sql_cust = "SELECT id FROM supplier WHERE supplier LIKE '%$filter_escaped%'";
    $result_cust = $conn->query($sql_cust);

    $cust_ids = [];
    if ($result_cust && $result_cust->num_rows > 0) {
        while ($row_cust = $result_cust->fetch_assoc()) {
            $cust_ids[] = $row_cust['id'];
        }
    }

    // Susun filter berdasarkan invoice, kode booking, dan supplier id
    $cust_filter = '';
    if (!empty($cust_ids)) {
        $cust_ids_str = implode(",", $cust_ids);
        $cust_filter = " OR beli.cust_id IN ($cust_ids_str)";
    }

    $filter_escaped = $conn->real_escape_string($filter); // escape untuk database pertama
    $filter_sql = " AND (beli.inv LIKE '%$filter_escaped%' OR beli.kodebooking LIKE '%$filter_escaped%' $cust_filter)";
}

$sql_pph23 = "
    SELECT 
        beli.id,
        beli.inv,
        beli.kodebooking,
        beli.cust_id,
        beli.pph23,
        beli.bukpot23,
        (
            SELECT SUM(jurnal.debet)
            FROM jurnal
            WHERE jurnal.journal_number = beli.inv
              AND jurnal.coa = '13103'
        ) AS PPN,
        (
            SELECT SUM(jurnal.debet)
            FROM jurnal
            WHERE jurnal.journal_number = beli.inv
              AND jurnal.coa LIKE '51%'
        ) AS DPP
    FROM 
        BELI beli
    WHERE 
        (beli.bukpot23 IS NULL OR beli.bukpot23 = '')
AND (beli.pph23 IS NOT NULL AND beli.pph23 > 0)

        $filter_sql
    ORDER BY 
        beli.id
";

$result_pph23 = $conn->query($sql_pph23);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pajak Belum dibuat - www.symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #333; padding: 20px; }
        h1 { text-align: center; color: #4CAF50; }
        form { text-align: center; margin-bottom: 20px; }
        input[type="text"], button { padding: 8px; border-radius: 4px; }
        input[type="text"] { border: 1px solid #ccc; margin-right: 10px; }
        button { background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        .home-icon1, .left-icon { position: absolute; top: 0; font-size: 24px; color: maroon; }
        .home-icon1 { left: 0; padding-left: 10px; }
        .left-icon { right: 0; padding-right: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: right; }
        th { background: #4CAF50; color: white; text-align: center; }
        tr:nth-child(even) { background: #f2f2f2; }
        tr:hover { background: #ddd; }
        .action-icon { text-align: center; }
        @media (max-width: 768px) {
            th, td { padding: 8px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
        <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
        <h1>PPH 23 Belum Dibuat</h1>

        <form method="get">
            <input type="text" name="filter" placeholder="Cari Invoice, Kode Booking, atau supplier..." value="<?php echo htmlspecialchars($filter); ?>">
            <button type="submit">Cari</button>
        </form>
<form method="get" action="export_23mbelum.php" style="text-align: center; margin-bottom: 20px;">
    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
    <button type="submit">Export Excel</button>
    <button type="button" onclick="window.location.href='23md.php'">SUDAH DIBUAT</button>
       <button type="button" onclick="window.location.href='23mup.php'" style="position: fixed; background:blue ;  right: 20px;">Upload Bukpot</button>

</form>

        <?php
        if ($result_pph23 && $result_pph23->num_rows > 0) {
            echo "<table>
                    <tr>
                        <th>ID</th>
                        <th>Invoice</th>
                        <th>Kode Booking</th>
                        <th>Supplier</th>
                        <th>Transaksi</th>
                     
                        <th>pph23</th>
                        <th>Tarif (%)</th>
                        <th>Bukpot</th>
                    </tr>";

             $total_dpp = 0;
    $total_pph23 = 0;
 

            while ($row = $result_pph23->fetch_assoc()) {
                $cust_id = intval($row['cust_id']);
                $supplier_name = 'Tidak Ditemukan';
                $sql_supplier = "SELECT supplier FROM supplier WHERE id = $cust_id";
                $result_supplier = $conn->query($sql_supplier);
                if ($result_supplier && $result_supplier->num_rows > 0) {
                    $supplier_row = $result_supplier->fetch_assoc();
                    $supplier_name = $supplier_row['supplier'];
                }

                $dpp = floatval($row['DPP']);
      $pph23 = floatval($row['pph23']);
     
        $tarif = ($dpp > 0) ? ($pph23 / $dpp * 100) : 0;

        $total_dpp += $dpp;
        $total_pph23 += $pph23;
      

        echo "<tr>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($row['inv']) . "</td>
                <td>" . htmlspecialchars($row['kodebooking']) . "</td>
                <td>" . htmlspecialchars($supplier_name) . "</td>
                <td>" . number_format($dpp, 2) . "</td>
               
                <td>" . number_format($pph23, 2) . "</td>
                <td style='text-align:center;'>" . number_format($tarif, 2) . "%</td>
                <td class='action-icon'>
                    <button type='button' onclick='openPopup(\"23min.php?J=" . urlencode($row['id']) . "\")'>Input Bukpot</button>
                </td>
              </tr>";
    }

    echo "<tr style='font-weight:bold; background:#f2f2f2;'>
            <td colspan='4' style='text-align:right;'>TOTAL</td>
            <td>" . number_format($total_dpp, 2) . "</td>
            <td>" . number_format($total_pph23, 2) . "</td>
     
            <td colspan='2'></td>
          </tr>";

            echo "</table>";
        } else {
            echo "<p style='text-align:center;'>Tidak ada data INV yang belum dicetak faktur pajaknya.</p>";
        }
        ?>
    </div>

    <script>
        function openPopup(url) {
            window.open(url, "Popup", "width=800,height=600");
        }
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && ['I', 'C'].includes(e.key.toUpperCase())) || (e.ctrlKey && e.key.toUpperCase() == 'U')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();

?>
