<?php
// Supersede the historical 1.3.3 metadata assertion with the active release target.
global $tests;
unset($tests['release metadata is WPVibe Direct MCP 1.3.3']);

parity_test('release metadata is WPVibe Direct MCP 1.4.0',function(){
    $main=(string)file_get_contents(dirname(__DIR__,2).'/wpvibe-direct-mcp.php');
    $readme=(string)file_get_contents(dirname(__DIR__,2).'/readme.txt');
    $changelog=(string)file_get_contents(dirname(__DIR__,2).'/CHANGELOG.md');
    assert_true(strpos($main,'Version:     1.4.0')!==false,'plugin header 1.4.0');
    assert_true(strpos($main,"WPVDMCP_VERSION', '1.4.0")!==false,'version constant 1.4.0');
    assert_true(strpos($readme,'Stable tag: 1.4.0')!==false,'readme stable tag 1.4.0');
    assert_true(strpos($changelog,'## 1.4.0 - 2026-10-12')!==false,'1.4.0 changelog section');
});
