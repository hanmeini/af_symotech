<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$tabels = [
    'pendaftaran',
    'kesehatan',
     'kesehatan2',
     'sikotes',
    'dapat_job',
    'ujikom',
    'keluar_id',
    'skck',
     'asuransipra',
    'pembuatan_paspor',
    'visa',
       'penerbangan'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Permintaan Edit</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            padding: 20px;
        }

        h2 {
            text-align: center;
            color: #333;
        }

        .home-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background-color: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #2c3e50;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        button {
            padding: 6px 12px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #27ae60;
        }

    </style>
</head>
<body>

<a href="home.php" class="home-btn">🏠</a>

<h2>Daftar Permintaan Edit</h2>

<table>
    <tr>
        <th>NIK</th>
        <th>Nama</th>
        <th>Cabang</th>
        <th>Jenis Tabel</th>
        <th>Alasan</th>
        <th>Aksi</th>
    </tr>

<?php
foreach ($tabels as $tabel) {
    if ($tabel == 'pendaftaran') {
        $query = "SELECT p.nik, p.nama, l.nama_cabang, p.alesan 
                  FROM pendaftaran p
                  LEFT JOIN location l ON p.cabang = l.idl
                  WHERE p.minta = '1' AND p.edit = 'clouse'";
    } else {
        $query = "SELECT t.nik, p.nama, l.nama_cabang, t.alesan 
                  FROM `$tabel` t
                  LEFT JOIN pendaftaran p ON t.nik = p.nik
                  LEFT JOIN location l ON p.cabang = l.idl
                  WHERE t.minta = '1' AND t.edit = 'clouse'";
    }

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $nik = htmlspecialchars($row['nik']);
            $nama = htmlspecialchars($row['nama'] ?? '-');
            $cabang = htmlspecialchars($row['nama_cabang'] ?? '-');
            $alesan = htmlspecialchars($row['alesan']);
            $jenis = ucfirst(str_replace("_", " ", $tabel));

            echo "<tr>
                    <td>$nik</td>
                    <td>$nama</td>
                    <td>$cabang</td>
                    <td>$jenis</td>
                    <td>$alesan</td>
                    <td>
                        <form method='post' action='proses_izinedit.php' style='display:inline;'>
                            <input type='hidden' name='nik' value='$nik'>
                            <input type='hidden' name='tabel' value='$tabel'>
                            <button type='submit' name='izinkan'>Izinkan Edit</button>
                        </form>
                    </td>
                  </tr>";
        }
    }
}
?>
</table>

</body>
</html>
<?php $conn->close(); ?>
