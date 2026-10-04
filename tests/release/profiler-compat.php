<?php
// Real CI_Profiler class; minimal CI collaborators, no CRM DB/runtime claim.
define('BASEPATH', __DIR__ . '/../../system/');
error_reporting(E_ALL);
set_error_handler(function ($level, $message) { throw new ErrorException($message, 0, $level); });
function log_message($level, $message) {}
function &get_instance() { global $ci; return $ci; }
function config_item($key) { return 'UTF-8'; }
$ci = (object) [
    'load' => new class { public function language($name) {} },
    'lang' => new class { public function line($key) { return $key; } },
];
require BASEPATH . 'libraries/Profiler.php';
$sections = array_fill_keys(['benchmarks','get','memory_usage','post','uri_string','controller_info','queries','http_headers','session_data','config'], false);
$profiler = new CI_Profiler($sections);
if (strpos($profiler->run(), 'profiler_no_profiles') === false) { throw new Exception('empty sections'); }
$profiler->set_sections(['memory_usage' => true]);
if (strpos($profiler->run(), 'ci_profiler_memory_usage') === false) { throw new Exception('enabled section not rendered'); }
$profiler->set_sections(['memory_usage' => false]);
if (strpos($profiler->run(), 'profiler_no_profiles') === false) { throw new Exception('disable failed'); }
echo "PASS real Profiler constructor, set_sections and run (no installed CRM)\n";
