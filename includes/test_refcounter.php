<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/refcounter.php';

echo "Running refcounter tests...\n";

function assertTrue(bool $cond, string $label): void {
    echo ($cond ? "- PASSED: " : "- FAILED: ") . $label . "\n";
}

function assertThrows(callable $fn, string $label): void {
    try {
        $fn();
        echo "- FAILED: {$label} (no exception)\n";
    } catch (Throwable $e) {
        echo "- PASSED: {$label}\n";
    }
}

$rc = new RefCounter($pdo);
$type = 'test';
$keyA = 'A-' . bin2hex(random_bytes(4));
$keyB = 'B-' . bin2hex(random_bytes(4));

assertTrue($rc->getCount($type, $keyA) === 0, 'Initial count is 0');
$c1 = $rc->acquire($type, $keyA, 'unit');
assertTrue($c1 === 1, 'Acquire increments to 1');
$c2 = $rc->acquire($type, $keyA, 'unit');
assertTrue($c2 === 2, 'Acquire increments to 2');
$c3 = $rc->release($type, $keyA, 'unit');
assertTrue($c3 === 1, 'Release decrements to 1');

$cleaned = 0;
$rc->release($type, $keyA, 'unit', [], function () use (&$cleaned) { $cleaned++; });
assertTrue($rc->getCount($type, $keyA) === 0, 'Release decrements to 0');
assertTrue($cleaned === 1, 'Zero-ref cleanup hook executed');

assertThrows(function () use ($rc, $type, $keyA) {
    $rc->release($type, $keyA, 'unit');
}, 'Prevent negative refcount');

$rc->addLink($type, $keyA, $type, $keyB, 'edge');
assertTrue($rc->getCount($type, $keyB) === 1, 'Link increments target refcount');
assertThrows(function () use ($rc, $type, $keyA, $keyB) {
    $rc->addLink($type, $keyB, $type, $keyA, 'edge');
}, 'Prevent circular references');

$cleaned = 0;
$rc->removeLink($type, $keyA, $type, $keyB, 'edge', [], function () use (&$cleaned) { $cleaned++; });
assertTrue($rc->getCount($type, $keyB) === 0, 'Unlink decrements target to 0');
assertTrue($cleaned === 1, 'Zero-ref cleanup hook executed on unlink');

for ($i = 0; $i < 200; $i++) $rc->acquire($type, $keyA, 'bench');
for ($i = 0; $i < 200; $i++) $rc->release($type, $keyA, 'bench');
assertTrue($rc->getCount($type, $keyA) === 0, 'Many increments/decrements end at 0');

echo "Refcounter tests completed.\n";

