<?php

return [
    'renderer' => env('PDF_RENDERER', 'dompdf'),
    'legacy_fallback' => (bool) env('PDF_LEGACY_FALLBACK', false),
    'node_binary' => env('PDF_NODE_BINARY', 'node'),
    'playwright_script' => env('PDF_PLAYWRIGHT_SCRIPT') ?: base_path('scripts/render-pdf.mjs'),
];
