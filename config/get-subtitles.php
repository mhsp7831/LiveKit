<?php
// Set the content type to JSON
header('Content-Type: application/json');

// We need the functions file for constants and validation
require_once __DIR__ . '/functions.php';

// 1. Get and Validate the Event ID
$event_id = $_GET['event'] ?? null;
if (!$event_id || !is_valid_event_id($event_id)) {
    // If the event ID is missing or invalid, send an error
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid or missing event ID.']);
    exit;
}

// 2. Construct the file path and check if it exists
$subtitlesFile = EVENTS_DIR . $event_id . '/subtitles.json';
if (!file_exists($subtitlesFile)) {
    // If the subtitles file doesn't exist, send an error
    http_response_code(404); // Not Found
    echo json_encode(['error' => 'Subtitles file not found for this event.']);
    exit;
}

// 3. --- IMPLEMENT ETAG CACHING ---
// Create a unique identifier for the file based on its content's hash
$etag = md5_file($subtitlesFile);

// Set the ETag header in the response
header('ETag: ' . $etag);
// Advise the browser to always check with the server before using a cached version
header('Cache-Control: no-cache, must-revalidate');

// Check if the browser sent an ETag with its request
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    // If the browser's ETag matches the current file's ETag,
    // send a "304 Not Modified" response and stop.
    // This saves bandwidth as no file content is sent.
    http_response_code(304);
    exit;
}

// 4. If there was no ETag match, send the full file content
readfile($subtitlesFile);
exit;