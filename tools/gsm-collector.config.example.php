<?php
/* --------------------------------------------------------------------------
 * SIM Inbox collector settings. Copy this file to gsm-collector.config.php
 * (same folder) and fill it in. That copy holds passwords: it is ignored by
 * git, never commit it or send it to anyone. Guide: gsm-collector.md
 * -------------------------------------------------------------------------- */
return [
    // how this PC shows up on the SIM Inbox page
    'name' => 'office-pc',

    // the GoIP gateway, as you open it in the browser on this network
    'goip' => [
        'url'  => 'http://192.168.35.51',
        'user' => 'admin',
        'pass' => '<the gateway password>',          // secret
    ],

    // the slots that have a SIM; [] reads every slot
    'lines' => [1, 6, 7, 8],

    // where to send the texts: each WeDo's api/gsm-ingest.php and that server's
    // 'gsm' => ['ingest_key' => ...] from its config.local.php
    'targets' => [
        ['url' => 'https://dashboard.wedoinc.ph/api/gsm-ingest.php', 'key' => '<64 random hex characters>'],   // secret
        // ['url' => 'https://dashboard.wedoinc.xyz/api/gsm-ingest.php', 'key' => '<staging key>'],
    ],

    // seconds between reads in --loop mode (Task Scheduler runs once a minute anyway)
    'interval' => 60,

    // optional: once a day after 'at', each line in 'lines' texts the carrier's
    // balance keyword, and the reply updates the load on the SIM Inbox page.
    // Each inquiry is a text sent from the SIM; check your carrier's charges first.
    'balance_check' => null,
    // 'balance_check' => ['number' => '222', 'text' => 'BAL', 'at' => '08:00'],

    // optional
    // 'log_file'   => __DIR__ . '/gsm-collector.log',
    // 'state_file' => __DIR__ . '/gsm-collector.state.json',   // what has been sent already
    // 'ca_file'    => 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt', // only if https fails with a certificate error
];
