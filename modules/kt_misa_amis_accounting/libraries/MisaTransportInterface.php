<?php

defined('BASEPATH') or exit('No direct script access allowed');

interface MisaTransportInterface
{
    public function connect(array $input);
    public function request($operation, array $input, array $context = []);
}

