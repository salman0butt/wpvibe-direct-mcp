<?php

parity_test('numeric-only saved-skill slug fails before approval or persistence',function(){
    $key=WPVDMCP_Parity_Skills::OPTION;
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    $had_saved=array_key_exists($key,$GLOBALS['wp_options']);
    $saved_before=$had_saved?$GLOBALS['wp_options'][$key]:null;
    $approval_keys_before=array_values(array_filter(array_keys($GLOBALS['wp_options']),function($option_key){return 0===strpos((string)$option_key,'transient:wpvdmcp_approval_');}));
    try{
        $server=WPVDMCP_Server::instance();
        $response=$server->handle(test_request(array(
            'jsonrpc'=>'2.0',
            'id'=>3901,
            'method'=>'tools/call',
            'params'=>array(
                'name'=>'create_skill',
                'arguments'=>array(
                    'slug'=>'123',
                    'title'=>'Numeric slug',
                    'instructions'=>'A workflow that must never enter persistence with a PHP-coerced numeric array key.',
                ),
                '_meta'=>array(
                    'io.modelcontextprotocol/protocolVersion'=>'2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities'=>(object)array(),
                ),
            ),
        ),'2026-07-28'));
        $data=$response->get_data();
        assert_same(true,$data['result']['isError']??null,'numeric-only slug must fail through public tools/call');
        $error=json_decode($data['result']['content'][0]['text']??'',true);
        assert_same('invalid_skill_slug',$error['code']??null,'numeric-only slug failure code');

        $approval_keys_after=array_values(array_filter(array_keys($GLOBALS['wp_options']),function($option_key){return 0===strpos((string)$option_key,'transient:wpvdmcp_approval_');}));
        assert_same($approval_keys_before,$approval_keys_after,'numeric-only slug must fail before approval creation');
        if($had_saved)assert_same($saved_before,$GLOBALS['wp_options'][$key]??null,'numeric-only slug must not mutate saved-skill persistence');
        else assert_true(!array_key_exists($key,$GLOBALS['wp_options']),'numeric-only slug must not create saved-skill persistence');

        $leading_zero=$server->handle(test_request(array(
            'jsonrpc'=>'2.0',
            'id'=>3904,
            'method'=>'tools/call',
            'params'=>array(
                'name'=>'create_skill',
                'arguments'=>array(
                    'slug'=>'01',
                    'title'=>'Leading zero slug',
                    'instructions'=>'A leading-zero identifier remains a real string array key and must stay compatible.',
                ),
                '_meta'=>array(
                    'io.modelcontextprotocol/protocolVersion'=>'2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities'=>(object)array(),
                ),
            ),
        ),'2026-07-28'));
        assert_same('approval_required',$leading_zero->get_data()['result']['structuredContent']['status']??null,'leading-zero string slug must remain valid');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});

parity_test('persisted numeric saved-skill keys stay out of public discovery',function(){
    $options_before=$GLOBALS['wp_options'];
    $current_user_before=$GLOBALS['wp_current_user'];
    try{
        $numeric=array(
            'slug'=>'123',
            'title'=>'Numeric legacy row',
            'description'=>'Must not surface with a numeric MCP identifier.',
            'min_wordpress'=>'6.0',
            'min_wpvibe'=>'1.0.0',
            'version'=>1,
            'instructions'=>'A legacy row whose PHP array key is numeric after persistence coercion.',
            'reference_files'=>array(),
        );
        $custom=array(
            'slug'=>'custom-ok',
            'title'=>'Custom OK',
            'description'=>'Valid saved skill sibling.',
            'min_wordpress'=>'6.0',
            'min_wpvibe'=>'1.0.0',
            'version'=>1,
            'instructions'=>'A valid saved workflow that must remain loadable while the numeric-key sibling is filtered.',
            'reference_files'=>array(),
        );
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION]=array(123=>$numeric,'custom-ok'=>$custom);

        $saved=WPVDMCP_Parity_Skills::saved();
        assert_true(!array_key_exists(123,$saved),'legacy numeric saved-skill key must be filtered');
        assert_true(isset($saved['custom-ok']),'valid saved-skill sibling must remain visible');

        $server=WPVDMCP_Server::instance();
        $prompts=$server->handle(test_request(array(
            'jsonrpc'=>'2.0',
            'id'=>3902,
            'method'=>'prompts/list',
            'params'=>array('_meta'=>array(
                'io.modelcontextprotocol/protocolVersion'=>'2026-07-28',
                'io.modelcontextprotocol/clientCapabilities'=>(object)array(),
            )),
        ),'2026-07-28'));
        foreach($prompts->get_data()['result']['prompts']??array() as $prompt){
            assert_true(isset($prompt['name'])&&is_string($prompt['name']),'public prompts/list names must remain strings');
            assert_true('123'!==$prompt['name'],'filtered numeric saved skill must not appear in prompts/list');
        }

        $loaded=$server->handle(test_request(array(
            'jsonrpc'=>'2.0',
            'id'=>3903,
            'method'=>'tools/call',
            'params'=>array(
                'name'=>'load_skill',
                'arguments'=>array('skill'=>'custom-ok'),
                '_meta'=>array(
                    'io.modelcontextprotocol/protocolVersion'=>'2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities'=>(object)array(),
                ),
            ),
        ),'2026-07-28'));
        assert_same('custom-ok',$loaded->get_data()['result']['structuredContent']['skill']??null,'valid saved-skill sibling must remain publicly loadable');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        $GLOBALS['wp_current_user']=$current_user_before;
    }
});
