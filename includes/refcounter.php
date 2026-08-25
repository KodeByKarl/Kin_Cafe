<?php
class RefCounter {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function acquire(string $type, string $key, ?string $tag = null, array $meta = []): int {
        return $this->mutate($type, $key, +1, 'acquire', $tag, $meta);
    }

    public function release(string $type, string $key, ?string $tag = null, array $meta = [], ?callable $onZero = null): int {
        $newCount = $this->mutate($type, $key, -1, 'release', $tag, $meta);
        if ($newCount === 0 && $onZero) {
            $onZero($type, $key);
        }
        return $newCount;
    }

    public function addLink(string $fromType, string $fromKey, string $toType, string $toKey, ?string $tag = null, array $meta = []): void {
        $this->pdo->beginTransaction();
        try {
            if ($this->pathExists($toType, $toKey, $fromType, $fromKey)) {
                throw new InvalidArgumentException('Circular reference is not allowed.');
            }
            $stmt = $this->pdo->prepare('INSERT IGNORE INTO resource_ref_edges (from_type, from_key, to_type, to_key) VALUES (?, ?, ?, ?)');
            $stmt->execute([$fromType, $fromKey, $toType, $toKey]);
            if ($stmt->rowCount() > 0) {
                $this->mutateLocked($toType, $toKey, +1, 'link', $tag, array_merge($meta, [
                    'from' => ['type' => $fromType, 'key' => $fromKey],
                ]));
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function removeLink(string $fromType, string $fromKey, string $toType, string $toKey, ?string $tag = null, array $meta = [], ?callable $onZero = null): void {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('DELETE FROM resource_ref_edges WHERE from_type = ? AND from_key = ? AND to_type = ? AND to_key = ?');
            $stmt->execute([$fromType, $fromKey, $toType, $toKey]);
            if ($stmt->rowCount() > 0) {
                $newCount = $this->mutateLocked($toType, $toKey, -1, 'unlink', $tag, array_merge($meta, [
                    'from' => ['type' => $fromType, 'key' => $fromKey],
                ]));
                if ($newCount === 0 && $onZero) {
                    $onZero($toType, $toKey);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getCount(string $type, string $key): int {
        $stmt = $this->pdo->prepare('SELECT ref_count FROM resource_refcounts WHERE resource_type = ? AND resource_key = ? LIMIT 1');
        $stmt->execute([$type, $key]);
        $val = $stmt->fetchColumn();
        return $val === false ? 0 : (int) $val;
    }

    private function mutate(string $type, string $key, int $delta, string $eventType, ?string $tag, array $meta): int {
        $this->pdo->beginTransaction();
        try {
            $new = $this->mutateLocked($type, $key, $delta, $eventType, $tag, $meta);
            $this->pdo->commit();
            return $new;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function mutateLocked(string $type, string $key, int $delta, string $eventType, ?string $tag, array $meta): int {
        $type = $this->normalizeType($type);
        $key = $this->normalizeKey($key);
        if ($delta !== 1 && $delta !== -1) {
            throw new InvalidArgumentException('Delta must be +1 or -1.');
        }

        $stmt = $this->pdo->prepare('SELECT id, ref_count FROM resource_refcounts WHERE resource_type = ? AND resource_key = ? FOR UPDATE');
        $stmt->execute([$type, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            if ($delta < 0) {
                throw new InvalidArgumentException('Release called for unknown resource.');
            }
            $ins = $this->pdo->prepare('INSERT INTO resource_refcounts (resource_type, resource_key, ref_count, last_event_at) VALUES (?, ?, 0, NOW())');
            $ins->execute([$type, $key]);
            $row = ['id' => (int) $this->pdo->lastInsertId(), 'ref_count' => 0];
        }

        $current = (int) $row['ref_count'];
        $new = $current + $delta;
        if ($new < 0) {
            throw new InvalidArgumentException('Reference count would become negative.');
        }

        $upd = $this->pdo->prepare('UPDATE resource_refcounts SET ref_count = ?, last_event_at = NOW() WHERE id = ?');
        $upd->execute([$new, (int) $row['id']]);

        $byUser = isset($_SESSION['admin']) ? (int) $_SESSION['admin'] : null;
        $metaJson = $meta ? json_encode($meta) : null;
        $evt = $this->pdo->prepare('INSERT INTO resource_ref_events (resource_type, resource_key, delta, new_count, event_type, ref_tag, by_user_id, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $evt->execute([$type, $key, $delta, $new, $eventType, $tag, $byUser, $metaJson]);

        if (function_exists('logAuditEvent')) {
            logAuditEvent($this->pdo, 'refcount_' . $eventType, 'resource_ref', null, [
                'resource_type' => $type,
                'resource_key' => $key,
                'delta' => $delta,
                'new_count' => $new,
                'tag' => $tag,
            ]);
        }

        return $new;
    }

    private function pathExists(string $fromType, string $fromKey, string $toType, string $toKey): bool {
        $fromType = $this->normalizeType($fromType);
        $fromKey = $this->normalizeKey($fromKey);
        $toType = $this->normalizeType($toType);
        $toKey = $this->normalizeKey($toKey);

        $frontier = [[$fromType, $fromKey]];
        $seen = [];
        for ($i = 0; $i < 1000 && $frontier; $i++) {
            [$t, $k] = array_shift($frontier);
            $id = $t . ':' . $k;
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            if ($t === $toType && $k === $toKey) {
                return true;
            }
            $stmt = $this->pdo->prepare('SELECT to_type, to_key FROM resource_ref_edges WHERE from_type = ? AND from_key = ?');
            $stmt->execute([$t, $k]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $frontier[] = [(string) $row['to_type'], (string) $row['to_key']];
            }
        }
        return false;
    }

    private function normalizeType(string $type): string {
        $type = strtolower(trim($type));
        $type = preg_replace('/[^a-z0-9._-]/', '', $type);
        if ($type === '') {
            throw new InvalidArgumentException('Resource type is required.');
        }
        return substr($type, 0, 60);
    }

    private function normalizeKey(string $key): string {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('Resource key is required.');
        }
        return substr($key, 0, 128);
    }
}

