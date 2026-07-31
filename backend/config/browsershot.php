<?php

/**
 * Invoice PDFs render through headless Chrome (see
 * App\Services\InvoiceService::renderPdf) rather than dompdf, which can't
 * shape complex scripts like Khmer. The Docker image installs Chromium and
 * Node via apk rather than letting Puppeteer download its own build (which
 * ships a glibc binary that won't run on Alpine's musl libc) — these paths
 * point Browsershot at that system install instead.
 */
return [
    'node_binary' => env('BROWSERSHOT_NODE_BINARY', '/usr/bin/node'),
    'node_modules_path' => env('BROWSERSHOT_NODE_MODULES_PATH', base_path('node_modules')),
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH', '/usr/bin/chromium-browser'),
];
