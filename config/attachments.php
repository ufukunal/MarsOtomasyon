<?php

return [
    'disk' => 'attachments',
    'max_size' => 25 * 1024 * 1024,

    'mime_extensions' => [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'text/csv' => ['csv'],
        'text/plain' => ['csv'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ],
];
