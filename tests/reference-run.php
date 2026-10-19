<?php
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity-fields.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity-skills.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity-insights.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-parity.php';
require_once dirname(__DIR__) . '/includes/class-wpvdmcp-mcp-app-transport.php';

$tests=array();
function parity_test($name,$fn){global $tests;$tests[$name]=$fn;}
function parity_tool($name){foreach(WPVDMCP_Parity::merge_definitions(WPVDMCP_Tools::definitions()) as $t){if(($t['name']??'')===$name)return $t;}return null;}

require __DIR__ . '/reference/cases-1.php';
require __DIR__ . '/reference/cases-2.php';
require __DIR__ . '/reference/cases-3.php';
require __DIR__ . '/reference/cases-4.php';
require __DIR__ . '/reference/cases-5.php';
require __DIR__ . '/reference/cases-6.php';
require __DIR__ . '/reference/cases-7.php';
require __DIR__ . '/reference/cases-8.php';
require __DIR__ . '/reference/cases-9.php';
require __DIR__ . '/reference/cases-10.php';
require __DIR__ . '/reference/cases-11.php';
require __DIR__ . '/reference/cases-12.php';
require __DIR__ . '/reference/cases-13.php';
require __DIR__ . '/reference/cases-14.php';
require __DIR__ . '/reference/cases-15.php';
require __DIR__ . '/reference/cases-16.php';
require __DIR__ . '/reference/cases-17.php';
require __DIR__ . '/reference/cases-18.php';
require __DIR__ . '/reference/cases-19.php';
require __DIR__ . '/reference/cases-20.php';
require __DIR__ . '/reference/cases-21.php';
require __DIR__ . '/reference/cases-22.php';
require __DIR__ . '/reference/cases-23.php';
require __DIR__ . '/reference/cases-24.php';
require __DIR__ . '/reference/cases-25.php';
require __DIR__ . '/reference/cases-26.php';
require __DIR__ . '/reference/cases-27.php';
require __DIR__ . '/reference/cases-28.php';
require __DIR__ . '/reference/cases-29.php';
require __DIR__ . '/reference/cases-30.php';
require __DIR__ . '/reference/cases-31.php';
require __DIR__ . '/reference/cases-32.php';
require __DIR__ . '/reference/cases-33.php';
require __DIR__ . '/reference/cases-34.php';
require __DIR__ . '/reference/cases-35.php';
require __DIR__ . '/reference/cases-36.php';
require __DIR__ . '/reference/cases-37.php';
require __DIR__ . '/reference/cases-38.php';
require __DIR__ . '/reference/cases-39.php';
require __DIR__ . '/reference/cases-40.php';
require __DIR__ . '/reference/cases-41.php';
require __DIR__ . '/reference/cases-42.php';
require __DIR__ . '/reference/cases-43.php';
require __DIR__ . '/reference/cases-44.php';
require __DIR__ . '/reference/cases-45.php';
require __DIR__ . '/reference/cases-46.php';
require __DIR__ . '/reference/cases-47.php';
require __DIR__ . '/reference/cases-48.php';
require __DIR__ . '/reference/cases-49.php';
require __DIR__ . '/reference/cases-50.php';
require __DIR__ . '/reference/cases-51.php';

$fail=0;foreach($tests as $name=>$fn){try{$fn();echo "PASS $name\n";}catch(Throwable $e){$fail++;echo "FAIL $name: {$e->getMessage()}\n";}}echo sprintf("\n%d reference parity tests, %d failures\n",count($tests),$fail);exit($fail?1:0);
