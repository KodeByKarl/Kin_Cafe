<?php
declare(strict_types=1);

function listFiles(string $root, array $extensions, array $excludeDirs = []): array {
    $result = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $skip = false;
        foreach ($excludeDirs as $dir) {
            if (stripos($path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, $extensions, true)) {
            $result[] = $path;
        }
    }
    sort($result);
    return $result;
}

function fileText(string $path): string {
    $txt = @file_get_contents($path);
    return is_string($txt) ? $txt : '';
}

function lineOfOffset(string $text, int $offset): int {
    if ($offset <= 0) return 1;
    return substr_count(substr($text, 0, $offset), "\n") + 1;
}

function findPhpFunctions(string $path, string $text): array {
    $out = [];
    if (preg_match_all('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $text, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $hit) {
            $name = $hit[0];
            $pos = $hit[1];
            $out[] = [
                'name' => $name,
                'file' => $path,
                'line' => lineOfOffset($text, $pos),
            ];
        }
    }
    return $out;
}

function findJsFunctions(string $path, string $text): array {
    $out = [];
    if (preg_match_all('/\bfunction\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/', $text, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $hit) {
            $name = $hit[0];
            $pos = $hit[1];
            $out[] = [
                'name' => $name,
                'file' => $path,
                'line' => lineOfOffset($text, $pos),
            ];
        }
    }
    return $out;
}

function countCallsites(array $files, string $pattern): int {
    $count = 0;
    foreach ($files as $path) {
        $txt = fileText($path);
        if ($txt === '') continue;
        if (preg_match_all($pattern, $txt, $m)) {
            $count += count($m[0]);
        }
    }
    return $count;
}

$root = dirname(__DIR__);
$excludeDirs = ['backups', '.github', 'tests', 'tools'];
$phpFiles = listFiles($root, ['php'], $excludeDirs);
$jsFiles = listFiles($root, ['js'], $excludeDirs);

$phpIndexText = '';
$phpFunctions = [];
foreach ($phpFiles as $path) {
    $txt = fileText($path);
    $phpIndexText .= "\n/*FILE:" . $path . "*/\n" . $txt;
    $phpFunctions = array_merge($phpFunctions, findPhpFunctions($path, $txt));
}

$jsFunctions = [];
foreach ($jsFiles as $path) {
    $txt = fileText($path);
    $jsFunctions = array_merge($jsFunctions, findJsFunctions($path, $txt));
}

$phpCallFiles = $phpFiles;
$jsCallFiles = array_merge($phpFiles, $jsFiles);

$phpCandidates = [];
foreach ($phpFunctions as $fn) {
    $name = $fn['name'];
    if (in_array($name, ['__construct'], true)) continue;
    $calls = countCallsites($phpCallFiles, '/\b' . preg_quote($name, '/') . '\s*\(/');
    if ($calls <= 1) {
        $phpCandidates[] = array_merge($fn, ['callsites' => $calls]);
    }
}

$jsCandidates = [];
foreach ($jsFunctions as $fn) {
    $name = $fn['name'];
    $calls = countCallsites($jsCallFiles, '/\b' . preg_quote($name, '/') . '\s*\(/');
    if ($calls <= 1) {
        $jsCandidates[] = array_merge($fn, ['callsites' => $calls]);
    }
}

$rootPhp = array_filter(glob($root . DIRECTORY_SEPARATOR . '*.php') ?: [], 'is_file');
sort($rootPhp);
$refIndex = fileText($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php')
    . "\n" . fileText($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php');
foreach ($phpFiles as $p) {
    $refIndex .= "\n" . fileText($p);
}
foreach ($jsFiles as $p) {
    $refIndex .= "\n" . fileText($p);
}

$unreferencedPages = [];
foreach ($rootPhp as $page) {
    $base = basename($page);
    if (in_array($base, ['index.php'], true)) continue;
    $hits = preg_match_all('/\b' . preg_quote($base, '/') . '\b/', $refIndex, $m);
    if ($hits <= 1) {
        $unreferencedPages[] = $base;
    }
}

echo "# Cleanup Audit Report\n\n";
echo "- Generated: " . date('c') . "\n";
echo "- Scope: PHP/JS usage heuristics (string-based). Review before removal.\n\n";

echo "## Potentially Unused PHP Functions\n";
echo "These are functions with <= 1 textual callsite (often just their own definition).\n\n";
if (!$phpCandidates) {
    echo "- None detected by heuristic.\n\n";
} else {
    foreach ($phpCandidates as $fn) {
        $file = str_replace($root . DIRECTORY_SEPARATOR, '', $fn['file']);
        echo "- {$fn['name']} ({$fn['callsites']}): {$file}:{$fn['line']}\n";
    }
    echo "\n";
}

echo "## Potentially Unused JS Functions\n";
echo "These are JS functions with <= 1 textual callsite.\n\n";
if (!$jsCandidates) {
    echo "- None detected by heuristic.\n\n";
} else {
    foreach ($jsCandidates as $fn) {
        $file = str_replace($root . DIRECTORY_SEPARATOR, '', $fn['file']);
        echo "- {$fn['name']} ({$fn['callsites']}): {$file}:{$fn['line']}\n";
    }
    echo "\n";
}

echo "## Potentially Unreferenced Pages\n";
echo "Top-level PHP pages that do not appear to be linked/referenced elsewhere.\n\n";
if (!$unreferencedPages) {
    echo "- None detected by heuristic.\n";
} else {
    foreach ($unreferencedPages as $p) {
        echo "- {$p}\n";
    }
}

