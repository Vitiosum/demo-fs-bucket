<?php

// Written once, on the very first start (see Logbook::seed). Sketches are in this folder.
return [
    [
        'hand' => 'The Purser',
        'wind' => 'W',
        'weather' => 'Fair',
        'remarks' => 'Logbook opened. Every page of it is a plain file on the FS Bucket, so it will outlive any redeploy.',
        'sketch' => 'coast.jpg',
    ],
    [
        'hand' => 'The Bosun',
        'wind' => 'NW',
        'weather' => 'Squall',
        'remarks' => 'Weathered a redeploy at dawn. The old instance went over the side, a fresh one came aboard, and not one line of this log was lost.',
        'sketch' => '',
    ],
    [
        'hand' => 'The Lookout',
        'wind' => 'NE',
        'weather' => 'Fog',
        'remarks' => 'Two watches on deck tonight. Both read and write the same pages: one shared hold, mounted on every instance.',
        'sketch' => 'lighthouse.jpg',
    ],
];
