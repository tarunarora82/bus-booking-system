<?php
// Simple Server-Sent Events (SSE) endpoint to stream bus and booking updates.
// Note: long-running PHP processes can be resource-heavy. For production, run a dedicated
// SSE/websocket server or use a queue/pubsub with a small gateway.

// Try to disable output buffering and compression to make streaming through proxies easier
@set_time_limit(0);
@ignore_user_abort(true);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// Hint for nginx to not buffer the response (if present)
header('X-Accel-Buffering: no');

// Flush and send a small padding to bypass some buffering proxies that wait for a chunk
while (ob_get_level() > 0) { @ob_end_flush(); }
echo str_repeat(' ', 2048) . "\n";
@ob_flush(); @flush();

function sendEvent($event, $data) {
    echo "event: $event\n";
    $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    // SSE requires each line to begin with 'data:' and terminate with double newline
    $lines = explode("\n", $payload);
    foreach ($lines as $line) {
        echo "data: {$line}\n";
    }
    echo "\n";
    @ob_flush();
    @flush();
}

function loadJsonFile($path) {
    if (!file_exists($path)) return [];
    $content = @file_get_contents($path);
    if ($content === false) return [];
    $v = json_decode($content, true);
    return $v ?: [];
}

$busesPath = '/var/www/html/data/buses.json';
$bookingsPath = '/var/www/html/data/bookings.json';
$lastBusesMtime = file_exists($busesPath) ? filemtime($busesPath) : 0;
$lastBookingsMtime = file_exists($bookingsPath) ? filemtime($bookingsPath) : 0;

// Initial handshake: send current state once
$initialBuses = loadJsonFile($busesPath);
$initialBookings = loadJsonFile($bookingsPath);
sendEvent('update', ['buses' => $initialBuses, 'bookings' => $initialBookings, 'ts' => time()]);

// Poll for changes and push updates
while (!connection_aborted()) {
    clearstatcache(false, $busesPath);
    clearstatcache(false, $bookingsPath);
    $bmtime = file_exists($busesPath) ? filemtime($busesPath) : 0;
    $kmtime = file_exists($bookingsPath) ? filemtime($bookingsPath) : 0;

    if ($bmtime !== $lastBusesMtime || $kmtime !== $lastBookingsMtime) {
        $lastBusesMtime = $bmtime;
        $lastBookingsMtime = $kmtime;
        $buses = loadJsonFile($busesPath);
        $bookings = loadJsonFile($bookingsPath);
        sendEvent('update', ['buses' => $buses, 'bookings' => $bookings, 'ts' => time()]);
    }

    // Heartbeat every 5 seconds to keep connection alive
    sendEvent('ping', ['ts' => time()]);
    // Sleep for a short interval
    sleep(5);
}

?>

