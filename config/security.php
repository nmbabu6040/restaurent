<?php

return [
    // enforce | report-only | off. Start with report-only on a new server and watch the browser console.
    'csp' => env('SECURITY_CSP', 'enforce'),
];
