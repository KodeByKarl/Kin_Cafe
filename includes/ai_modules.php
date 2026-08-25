<?php

require_once __DIR__ . '/ai_services.php';

function getAiInsightsSnapshot(PDO $pdo): array {
    return getAiFeatureSuite($pdo);
}