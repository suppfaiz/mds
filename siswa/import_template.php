<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_siswa.csv"');

$output = fopen('php://output', 'w');

// Write BOM to support UTF-8 in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Semicolon separator is highly compatible with Indonesian Excel versions
$headers = [
    'nis', 'nisn', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 
    'alamat', 'agama', 'kelas_id', 'tahun_masuk', 'no_hp', 'email', 
    'nama_ayah', 'nik_ayah', 'pekerjaan_ayah', 'nama_ibu', 'nik_ibu', 
    'pekerjaan_ibu', 'no_hp_ortu', 'alamat_ortu'
];

fputcsv($output, $headers, ';');

// Add sample data row
$sample = [
    '20260002', '0098765432', 'Agus Budiman', 'L', 'Bandung', '2010-04-12', 
    'Jl. Buah Batu No. 12, Bandung', 'Islam', '1', '2026', '081234567890', 'agus.budiman@siswa.sch.id', 
    'Subarjo', '3204011212850003', 'PNS', 'Subarni', '3204014512870004', 
    'Wiraswasta', '08987654321', 'Jl. Buah Batu No. 12, Bandung'
];

fputcsv($output, $sample, ';');
fclose($output);
exit();
?>
