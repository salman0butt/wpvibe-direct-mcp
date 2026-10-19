<?php

parity_test('malformed JSON-RPC ids never dispatch side-effecting tools in any supported transport',function(){
    $options_before=$GLOBALS['wp_options'];
    $user_before=$GLOBALS['wp_current_user'];
    try{
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','test-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $invalid_ids=array(true,false,array(),array('malformed'=>'id'));
        foreach(array('2026-07-28','2025-11-25','2024-11-05')as$version){
            foreach($invalid_ids as$id){
                $payload=array('jsonrpc'=>'2.0','id'=>$id,'method'=>'tools/call','params'=>array('name'=>'request_upload','arguments'=>array('title'=>'Untrusted invalid id')));
                $response=WPVDMCP_Server::instance()->handle(test_request($payload,$version));
                $data=response_data($response);
                assert_same(400,$response->get_status(),'invalid JSON-RPC id returns HTTP400 for '.$version);
                assert_same(-32600,$data['error']['code']??null,'invalid JSON-RPC id error code for '.$version);
                assert_true(array_key_exists('id',$data)&&null===$data['id'],'invalid JSON-RPC request id must not be echoed to the client');
                foreach(array_keys($GLOBALS['wp_options'])as$key){
                    assert_true(0!==strpos((string)$key,'transient:wpvdmcp_upload_up_'),'invalid JSON-RPC id must not create an upload ticket');
                }
            }
        }
    }finally{$GLOBALS['wp_options']=$options_before;$GLOBALS['wp_current_user']=$user_before;}
});

parity_test('legacy batches reject malformed JSON-RPC ids before tools but process valid notifications and requests',function(){
    $options_before=$GLOBALS['wp_options'];
    $user_before=$GLOBALS['wp_current_user'];
    try{
        $GLOBALS['wp_options']['wpvdmcp_token_hash']=hash('sha256','test-token');
        $GLOBALS['wp_options']['wpvdmcp_user_id']=1;
        $payload=array(
            array('jsonrpc'=>'2.0','method'=>'notifications/initialized','params'=>(object)array()),
            array('jsonrpc'=>'2.0','id'=>array('corrupt'=>'id'),'method'=>'tools/call','params'=>array('name'=>'request_upload','arguments'=>array())),
            array('jsonrpc'=>'2.0','id'=>true,'method'=>'tools/call','params'=>array('name'=>'request_upload','arguments'=>array())),
            array('jsonrpc'=>'2.0','id'=>'valid-list','method'=>'tools/list','params'=>array()),
        );
        $response=WPVDMCP_Server::instance()->handle(test_request($payload,'2024-11-05'));
        $data=response_data($response);
        assert_same(200,$response->get_status(),'legacy mixed batch still responds');
        assert_same(3,count($data),'two invalid id errors, one valid request, no notification result');
        foreach(array(0,1)as$index){
            assert_same(-32600,$data[$index]['error']['code']??null,'bad batch request id rejected before tool dispatch');
            assert_true(array_key_exists('id',$data[$index])&&null===$data[$index]['id'],'bad id replaced with null in protocol error');
        }
        assert_same('valid-list',$data[2]['id']??null,'valid later request still responds');
        assert_true(!empty($data[2]['result']['tools']),'list response remains available');
        foreach(array_keys($GLOBALS['wp_options'])as$key){
            assert_true(0!==strpos((string)$key,'transient:wpvdmcp_upload_up_'),'no malformed ID may create an upload ticket in a batch');
        }
    }finally{$GLOBALS['wp_options']=$options_before;$GLOBALS['wp_current_user']=$user_before;}
});

parity_test('valid scalar JSON-RPC request ids remain supported by MCP tools/call',function(){
    $user_before=$GLOBALS['wp_current_user'];
    try{
        foreach(array('request','',0,12,1.5,null)as$id){
            $request=array('jsonrpc'=>'2.0','id'=>$id,'method'=>'tools/call','params'=>array('name'=>'site_info','arguments'=>array()));
            $response=WPVDMCP_Server::instance()->handle(test_request($request,'2025-11-25'));
            $data=response_data($response);
            assert_true(!isset($data['error']),'valid scalar id must not get JSON-RPC Invalid Request');
            assert_true(array_key_exists('id',$data)&&$id===$data['id'],'valid scalar request id preserved exactly');
        }
    }finally{$GLOBALS['wp_current_user']=$user_before;}
});
