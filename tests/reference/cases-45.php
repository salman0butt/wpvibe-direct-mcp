<?php

parity_test('malformed editable-definition shapes are filtered before replay and discovery',function(){
    $options_before=$GLOBALS['wp_options'];
    $had_group_callback=array_key_exists('wpvibe_field_group_register_callback',$GLOBALS);
    $group_callback_before=$had_group_callback?$GLOBALS['wpvibe_field_group_register_callback']:null;
    try{
        $GLOBALS['wp_options'][WPVDMCP_Parity_Fields::OPTION]=array(
            'groups'=>array(
                'page:bad-config'=>array(
                    'post_type'=>'page',
                    'group_id'=>'bad-config',
                    'config'=>(object)array('title'=>'Broken'),
                ),
                'page:bad-title'=>array(
                    'post_type'=>'page',
                    'group_id'=>'bad-title',
                    'config'=>array('title'=>(object)array('nested'=>'broken')),
                ),
                'page:valid'=>array(
                    'post_type'=>'page',
                    'group_id'=>'valid',
                    'config'=>array('title'=>'Valid group','context'=>'normal','priority'=>'default'),
                ),
            ),
        );

        $calls=array();
        $GLOBALS['wpvibe_field_group_register_callback']=function($post_type,$group_id,$config) use (&$calls){
            if(!is_array($config) || (isset($config['title'])&&!is_string($config['title']))){
                throw new RuntimeException('malformed persisted group reached native registration');
            }
            $calls[]=array($post_type,$group_id,$config);
            return true;
        };

        WPVDMCP_Parity_Fields::replay();
        assert_same(1,count($calls),'replay must skip malformed persisted groups and keep valid siblings');
        assert_same('valid',$calls[0][1]??null,'valid persisted group must still replay');

        $listed=WPVDMCP_Parity_Fields::execute('list_editable_fields',array());
        assert_same(1,count($listed['groups']??array()),'discovery must filter malformed persisted groups');
        assert_same('valid',$listed['groups'][0]['group_id']??null,'discovery must preserve the valid sibling');
    }finally{
        $GLOBALS['wp_options']=$options_before;
        if($had_group_callback)$GLOBALS['wpvibe_field_group_register_callback']=$group_callback_before;else unset($GLOBALS['wpvibe_field_group_register_callback']);
    }
});
