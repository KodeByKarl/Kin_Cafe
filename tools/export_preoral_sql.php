<?php
/**
 * Export a live SQL dump for pre-oral / defense.
 * Usage: php tools/export_preoral_sql.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/includes/db.php';

$outDir = $root . DIRECTORY_SEPARATOR . 'sql';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$filename = 'kin_cafe_live_dump_' . date('Y-m-d_His') . '.sql';
$path = $outDir . DIRECTORY_SEPARATOR . $filename;
$content = generateSqlDatabaseDump($pdo);
file_put_contents($path, $content);

echo "Wrote {$path} (" . strlen($content) . " bytes)" . PHP_EOL;
echo "Optional demo seed: php tools/seed_demo_data.php --force" . PHP_EOL;
