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

$fail=0;foreach($tests as $name=>$fn){try{$fn();echo "PASS $name\n";}catch(Throwable $e){$fail++;echo "FAIL $name: {$e->getMessage()}\n";}}echo sprintf("\n%d reference parity tests, %d failures\n",count($tests),$fail);exit($fail?1:0);
