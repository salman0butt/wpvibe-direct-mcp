<?php
parity_test('WooCommerce product updates refuse dropping replace-all lists without explicit replacement',function(){
    set_test_routes(array('/wc/v3/products/44'=>array('GET','PUT')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $GLOBALS['rest_dispatch_callback']=function($r){
        if('GET'===$r->get_param('_method')){
            return new WP_REST_Response(array(
                'id'=>44,
                'attributes'=>array(array('id'=>1,'name'=>'Color','options'=>array('Red')),array('id'=>2,'name'=>'Size','options'=>array('M'))),
                'categories'=>array(array('id'=>10,'name'=>'Shirts'),array('id'=>20,'name'=>'Sale')),
                'tags'=>array(array('id'=>30,'name'=>'Summer')),
                'images'=>array(array('id'=>100,'src'=>'https://example.test/100.jpg'),array('id'=>101,'src'=>'https://example.test/101.jpg')),
            ),200);
        }
        return new WP_REST_Response(array('id'=>44,'body'=>$r->get_json_params()),200);
    };
    $unsafe=WPVDMCP_Parity::execute('rest_api',array(
        'method'=>'PUT','path'=>'/wc/v3/products/44',
        'body'=>array('attributes'=>array(array('id'=>1,'name'=>'Color','options'=>array('Blue'))))
    ));
    assert_true(is_wp_error($unsafe),'partial replace-all product list must be refused');
    assert_same('woocommerce_replace_guard',$unsafe->get_error_code(),'WooCommerce replacement guard code');
    unset($GLOBALS['rest_dispatch_callback']);
});

parity_test('WooCommerce product update accepts complete lists and explicit replacement marker is stripped',function(){
    set_test_routes(array('/wc/v3/products/44'=>array('GET','PUT')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $written=null;
    $GLOBALS['rest_dispatch_callback']=function($r) use (&$written){
        if('GET'===$r->get_param('_method')){
            return new WP_REST_Response(array(
                'id'=>44,
                'attributes'=>array(array('id'=>1,'name'=>'Color'),array('id'=>2,'name'=>'Size')),
                'categories'=>array(array('id'=>10),array('id'=>20)),
                'tags'=>array(),
                'images'=>array(array('id'=>100),array('id'=>101)),
            ),200);
        }
        $written=$r->get_json_params();
        return new WP_REST_Response(array('id'=>44,'body'=>$written),200);
    };

    $complete=array(
        'method'=>'PUT','path'=>'/wc/v3/products/44',
        'body'=>array(
            'attributes'=>array(array('id'=>1,'name'=>'Color'),array('id'=>2,'name'=>'Size')),
            'categories'=>array(array('id'=>10),array('id'=>20)),
            'images'=>array(array('id'=>100),array('id'=>101))
        )
    );
    $approval=WPVDMCP_Parity::execute('rest_api',$complete);
    assert_same('approval_required',$approval['status']??null,'complete WooCommerce update should proceed to approval');

    $replace=array('method'=>'PUT','path'=>'/wc/v3/products/44','body'=>array(
        'attributes'=>array(array('id'=>1,'name'=>'Color')),
        'wpvibe_replace'=>true,
    ));
    $replace_approval=WPVDMCP_Parity::execute('rest_api',$replace);
    assert_same('approval_required',$replace_approval['status']??null,'explicit replacement should proceed to approval');
    WPVDMCP_Approvals::approve($replace_approval['approval_id'],1);
    $replace['approval_id']=$replace_approval['approval_id'];
    $done=WPVDMCP_Parity::execute('rest_api',$replace);
    assert_same(44,$done['data']['id']??null,'explicit replacement executed');
    assert_true(!array_key_exists('wpvibe_replace',$written??array()),'wpvibe_replace marker must not reach WooCommerce');
    unset($GLOBALS['rest_dispatch_callback']);
});

parity_test('WooCommerce batch update refuses an item that would drop protected lists',function(){
    set_test_routes(array('/wc/v3/products/batch'=>array('POST'),'/wc/v3/products/44'=>array('GET')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $GLOBALS['rest_dispatch_callback']=function($r){
        if('/wc/v3/products/44'===$r->get_param('_route')){
            return new WP_REST_Response(array('id'=>44,'categories'=>array(array('id'=>10),array('id'=>20)),'attributes'=>array(),'tags'=>array(),'images'=>array()),200);
        }
        return new WP_REST_Response(array('update'=>array()),200);
    };
    $unsafe=WPVDMCP_Parity::execute('rest_api',array(
        'method'=>'POST','path'=>'/wc/v3/products/batch',
        'body'=>array('update'=>array(array('id'=>44,'categories'=>array(array('id'=>10)))))
    ));
    assert_true(is_wp_error($unsafe),'partial WooCommerce batch item must be refused');
    assert_same('woocommerce_replace_guard',$unsafe->get_error_code(),'WooCommerce batch replacement guard code');
    unset($GLOBALS['rest_dispatch_callback']);
});
