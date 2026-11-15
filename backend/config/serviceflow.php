<?php

return [

    'currency' => env('SERVICEFLOW_CURRENCY', 'EGP'),

    /*
    | SLA window per priority, in hours. A request is overdue when it is still
    | open and now() is past its due_at (created_at + sla hours).
    */
    'sla_hours' => [
        'urgent' => 4,
        'high' => 24,
        'normal' => 48,
        'low' => 72,
    ],

    'codes' => [
        'service_request_prefix' => 'SR',
        'invoice_prefix' => 'INV',
    ],

    /*
    | Fallback used only when the settings table has no tax_rate row.
    */
    'default_tax_rate' => 14,

];
