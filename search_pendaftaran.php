<?php
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

$q      = trim($_GET['q'] ?? '');
$cabang = trim($_GET['cabang'] ?? '');

if (strlen($q) < 2 || $cabang === '') {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, nik, nama
    FROM pendaftaran
    WHERE (nik LIKE ? OR nama LIKE ?)
      AND cabang = ?
      AND status = 'aktive'
");

$search = "%$q%";
$stmt->bind_param("sss", $search, $search, $cabang);
$stmt->execute();

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$stmt->close();
$conn->close();
