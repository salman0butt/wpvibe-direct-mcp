<?php
parity_test('corrupt top-level editable-definition state cannot break discovery',function(){
    $key=WPVDMCP_Parity_Fields::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    $corrupt=(object)array('fields'=>array('unexpected'=>'shape'));
    try{
        $GLOBALS['wp_options'][$key]=$corrupt;

        $result=WPVDMCP_Parity_Fields::execute('list_editable_fields',array());

        assert_true(is_array($result),'corrupt editable-definition state must return a normal discovery result');
        assert_same(array(),$result['fields'],'corrupt editable-definition fields must normalize to empty discovery');
        assert_same(array(),$result['groups'],'corrupt editable-definition groups must normalize to empty discovery');
        assert_same(array(),$result['settings'],'corrupt editable-definition settings must normalize to empty discovery');
        assert_true($GLOBALS['wp_options'][$key]===$corrupt,'read-only discovery must not rewrite corrupt durable state');
    }finally{
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
