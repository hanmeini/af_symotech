<?php
// aset_helper.php - Shared helpers for aset.kelompok and soft-delete aset.del (year)

if (!function_exists('asetKelompokList')) {
    function asetKelompokList()
    {
        return [
            'Tanah',
            'Kelompok 1',
            'Kelompok 2',
            'Kelompok 3',
            'Kelompok 4',
            'Bangunan Permanen',
            'Bangunan Tidak Permanen',
        ];
    }
}

if (!function_exists('asetNormalizeKelompok')) {
    function asetNormalizeKelompok($val)
    {
        $val = trim((string)$val);
        $list = asetKelompokList();
        if (in_array($val, $list, true)) {
            return $val;
        }
        return 'Tanah';
    }
}

if (!function_exists('asetRenderKelompokSelect')) {
    function asetRenderKelompokSelect($selected = '', $id = 'kelompok', $name = 'kelompok')
    {
        $selected = trim((string)$selected);
        $list = asetKelompokList();
        $html = '<select name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" required>';
        $isPlaceholderSelected = ($selected === '' || !in_array($selected, $list, true)) ? ' selected' : '';
        $html .= '<option value="" disabled' . $isPlaceholderSelected . '>Pilih Kelompok Aset</option>';
        foreach ($list as $opt) {
            $sel = ($selected === $opt) ? ' selected' : '';
            $esc = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
            $html .= '<option value="' . $esc . '"' . $sel . '>' . $esc . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

if (!function_exists('ensureAsetKelompokDelSchema')) {
    function ensureAsetKelompokDelSchema($conn)
    {
        static $done = false;
        if ($done || !$conn) {
            return;
        }
        $done = true;
        try {
            $resKel = $conn->query("SHOW COLUMNS FROM aset LIKE 'kelompok'");
            if ($resKel && $resKel->num_rows === 0) {
                $conn->query("ALTER TABLE aset ADD COLUMN kelompok ENUM('Tanah','Kelompok 1','Kelompok 2','Kelompok 3','Kelompok 4','Bangunan Permanen','Bangunan Tidak Permanen') NOT NULL DEFAULT 'Tanah' AFTER keteranganlok");
            }
            $resDel = $conn->query("SHOW COLUMNS FROM aset LIKE 'del'");
            if ($resDel && $resDel->num_rows === 0) {
                $conn->query("ALTER TABLE aset ADD COLUMN del SMALLINT NULL DEFAULT NULL AFTER kelompok");
            } elseif ($resDel && ($rowDel = $resDel->fetch_assoc())) {
                $type = strtolower((string)($rowDel['Type'] ?? ''));
                if (strpos($type, 'tinyint') === 0) {
                    $conn->query("ALTER TABLE aset MODIFY COLUMN del SMALLINT NULL DEFAULT NULL");
                }
            }
        } catch (Throwable $e) {
            error_log('ensureAsetKelompokDelSchema error: ' . $e->getMessage());
        }
    }
}
