<?php

function getForecastServiceBaseUrl(): string {
    $configuredUrl = trim((string) (getenv('KIN_CAFE_FORECAST_SERVICE_URL') ?: 'http://127.0.0.1:5000'));

    return rtrim($configuredUrl, '/');
}

function forecastServiceCooldownSeconds(): int {
    return 20;
}

function forecastServiceDefaultTimeoutSeconds(): int {
    return 2;
}

function forecastServiceCircuitState(): array {
    if (!isset($_SESSION['forecast_circuit']) || !is_array($_SESSION['forecast_circuit'])) {
        $_SESSION['forecast_circuit'] = [
            'open_until' => 0,
            'last_error_at' => 0,
            'failures' => 0,
        ];
    }

    return $_SESSION['forecast_circuit'];
}

function forecastServiceMarkSuccess(): void {
    $_SESSION['forecast_circuit'] = [
        'open_until' => 0,
        'last_error_at' => 0,
        'failures' => 0,
    ];
}

function forecastServiceMarkFailure(): void {
    $state = forecastServiceCircuitState();
    $failures = (int) ($state['failures'] ?? 0) + 1;
    $_SESSION['forecast_circuit'] = [
        'open_until' => time() + forecastServiceCooldownSeconds(),
        'last_error_at' => time(),
        'failures' => $failures,
    ];
}

function isForecastServiceCircuitOpen(): bool {
    $state = forecastServiceCircuitState();
    return (int) ($state['open_until'] ?? 0) > time();
}

function isForecastServiceReachable(int $timeoutSeconds = 1): bool {
    static $cachedReachable = null;
    if ($cachedReachable !== null) {
        return $cachedReachable;
    }

    if (isForecastServiceCircuitOpen()) {
        $cachedReachable = false;
        return false;
    }

    $url = getForecastServiceBaseUrl() . '/health';

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle === false) {
            forecastServiceMarkFailure();
            $cachedReachable = false;
            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
        ]);
        $body = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        $ok = $body !== false && $httpCode >= 200 && $httpCode < 300 && str_contains((string) $body, '"status"');
        if ($ok) {
            forecastServiceMarkSuccess();
            $cachedReachable = true;
            return true;
        }

        forecastServiceMarkFailure();
        $cachedReachable = false;
        return false;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => max(1, $timeoutSeconds),
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $ok = $body !== false && str_contains((string) $body, '"status"');
    if ($ok) {
        forecastServiceMarkSuccess();
        $cachedReachable = true;
        return true;
    }

    forecastServiceMarkFailure();
    $cachedReachable = false;
    return false;
}

function callForecastService(string $endpoint, array $payload, int $timeoutSeconds = 2): ?array {
    $endpoint = trim(strtolower($endpoint), '/');
    if (!in_array($endpoint, ['sales', 'demand'], true)) {
        return null;
    }

    if (isForecastServiceCircuitOpen()) {
        return null;
    }

    // Fast-fail when Flask is hung/offline so page navigation stays snappy.
    if (!isForecastServiceReachable(1)) {
        return null;
    }

    $url = getForecastServiceBaseUrl() . '/forecast/' . $endpoint;
    $body = json_encode($payload);
    if ($body === false) {
        return null;
    }

    $timeoutSeconds = max(1, min(5, $timeoutSeconds > 0 ? $timeoutSeconds : forecastServiceDefaultTimeoutSeconds()));

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle === false) {
            forecastServiceMarkFailure();
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        $responseBody = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($responseBody === false || $httpCode < 200 || $httpCode >= 300) {
            forecastServiceMarkFailure();
            return null;
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            forecastServiceMarkFailure();
            return null;
        }

        forecastServiceMarkSuccess();
        return $decoded;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        forecastServiceMarkFailure();
        return null;
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        forecastServiceMarkFailure();
        return null;
    }

    forecastServiceMarkSuccess();
    return $decoded;
}

function formatForecastMethodLabel(string $methodUsed, bool $insufficientData = false, bool $serviceUnavailable = false): string {
    if ($serviceUnavailable) {
        return 'Powered by Moving Average (service unavailable)';
    }

    if ($insufficientData || stripos($methodUsed, 'moving_average') !== false) {
        return 'Powered by Moving Average (insufficient data)';
    }

    if (stripos($methodUsed, 'SARIMA') !== false) {
        return 'Powered by ' . $methodUsed;
    }

    if (stripos($methodUsed, 'ARIMA') !== false) {
        return 'Powered by ' . $methodUsed;
    }

    return 'Powered by ' . $methodUsed;
}
