<?php
// Supersede the historical 1.3.3 metadata assertion with the active release target.
global $tests;
unset($tests['release metadata is WPVibe Direct MCP 1.3.3']);

parity_test('release metadata is WPVibe Direct MCP 1.5.1',function(){
    $main=(string)file_get_contents(dirname(__DIR__,2).'/wpvibe-direct-mcp.php');
    $readme=(string)file_get_contents(dirname(__DIR__,2).'/readme.txt');
    $changelog=(string)file_get_contents(dirname(__DIR__,2).'/CHANGELOG.md');
    $root=(string)file_get_contents(dirname(__DIR__,2).'/README.md');
    $parity=(string)file_get_contents(dirname(__DIR__,2).'/docs/PARITY.md');
    $testing=(string)file_get_contents(dirname(__DIR__,2).'/docs/TESTING.md');
    assert_true(strpos($main,'Version:     1.5.1')!==false,'plugin header 1.5.1');
    assert_true(strpos($main,"WPVDMCP_VERSION', '1.5.1")!==false,'version constant 1.5.1');
    assert_true(strpos($readme,'Stable tag: 1.5.1')!==false,'readme stable tag 1.5.1');
    assert_true(strpos($changelog,'## 1.5.1 - 2026-10-20')!==false,'1.5.1 changelog section');
    assert_true(strpos($changelog,'## 1.5.0 - 2026-10-20')!==false,'1.5.0 changelog section');
    assert_true(strpos($changelog,'## 1.4.1 - 2026-10-12')!==false,'1.4.1 changelog section');
    assert_true(strpos($root,'Direct MCP: **1.5.1**')!==false,'README release version 1.5.1');
    assert_true(strpos($root,'Audited WPVibe release/source: **1.20.5**')!==false,'README audited upstream version 1.20.5');
    assert_true(strpos($root,'**277 suite cases**')!==false,'README 1.5.1 suite total 277');
    assert_true(strpos($parity,'Direct MCP release target: **1.5.1**')!==false,'parity release target 1.5.1');
    assert_true(strpos($parity,'Release **1.5.1** runs **277 suite cases')!==false,'parity validation count 277');
    assert_true(strpos($testing,'Release **1.5.1** packages')!==false,'testing docs identify 1.5.1 as released source');
    assert_true(strpos($testing,'Release **1.4.1** contains **35 legacy tests + 14 core parity tests + 70 reference parity tests (119 suite cases)**')!==false,'testing release count 1.4.1');
});
