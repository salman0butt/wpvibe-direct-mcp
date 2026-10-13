<?php
parity_test('malformed editable-definition records cannot break replay',function(){
    $key=WPVDMCP_Parity_Fields::OPTION;
    $had=array_key_exists($key,$GLOBALS['wp_options']);
    $previous=$had?$GLOBALS['wp_options'][$key]:null;
    $calls=array();
    try{
        $GLOBALS['wp_options'][$key]=array(
            'fields'=>array(
                'broken'=>'legacy-corrupt-value',
                'page:valid_field'=>array(
                    'post_type'=>'page',
                    'key'=>'valid_field',
                    'config'=>array('type'=>'text','label'=>'Valid field'),
                ),
            ),
        );
        $GLOBALS['wpvibe_field_register_callback']=function($post_type,$field_key,$config)use(&$calls){
            $calls[]=array($post_type,$field_key,$config);
            return true;
        };
        WPVDMCP_Parity_Fields::replay();
        assert_same(1,count($calls),'replay must ignore malformed records and keep valid siblings');
        assert_same('valid_field',$calls[0][1]??null,'valid editable field must still be replayed');
    }finally{
        unset($GLOBALS['wpvibe_field_register_callback']);
        if($had)$GLOBALS['wp_options'][$key]=$previous;else unset($GLOBALS['wp_options'][$key]);
    }
});
