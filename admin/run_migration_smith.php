<?php
// Temporary migration runner - DELETE THIS FILE after running
$host = 'localhost';
$user = 'root';
$pass = '1234';
$db   = 'source';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$migrations = [
    'Supplier Ledger'          => 'G:/xampp/htdocs/etail_development_src/database/views/ret_view_supplier_ledger.sql',
    'Smith Ledger'             => 'G:/xampp/htdocs/etail_development_src/database/views/ret_view_smith_ledger.sql',
    'Supplier Approval Ledger' => 'G:/xampp/htdocs/etail_development_src/database/views/ret_view_supplier_approval_ledger.sql',
];

echo "<h2>Running View Migrations...</h2>";

foreach ($migrations as $name => $file) {
    $sql = file_get_contents($file);
    // Strip SQL comments
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    echo "<h3>$name</h3>";
    $ok = true;
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        if ($conn->query($stmt) === false) {
            echo "<p style='color:red'>❌ Error: " . htmlspecialchars($conn->error) . "</p>";
            echo "<pre>" . htmlspecialchars(substr($stmt, 0, 300)) . "...</pre>";
            $ok = false;
            break;
        }
    }
    if ($ok) echo "<p style='color:green'>✅ $name view updated successfully.</p>";
}

$conn->close();
echo "<hr><p style='color:orange'><strong>⚠️ DELETE this file: admin/run_migration_smith.php</strong></p>";
?>
