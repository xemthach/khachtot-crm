<?php

defined('BASEPATH') or exit('No direct script access allowed');

$config['csrf_exclude_uris'] = [
    'kt_matbao_invoice/webhook/invoice',
    'kt_matbao_invoice/webhook/signing',
];
