<?php
parity_test('public REST schema accepts reference and Direct option names',function(){
    $rest=parity_tool('rest_api');
    $props=$rest['inputSchema']['properties'];
    foreach(array('path','route','params','query','fields','max_response_bytes','max_length','approval_id') as $name){
        assert_true(isset($props->$name),'rest_api missing option '.$name);
    }
    assert_true(isset($props->fields['oneOf']),'fields should accept string or array compatibility forms');
    assert_true(isset($rest['inputSchema']['anyOf']),'rest_api should require path or route');
});

parity_test('public REST route query fields and max length aliases normalize before dispatch',function(){
    set_test_routes(array('/wp/v2/posts'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){
        return new WP_REST_Response(array('query'=>$r->get_query_params()),200);
    };
    $result=WPVDMCP_Parity::execute('rest_api',array(
        'method'=>'GET',
        'route'=>'/wp/v2/posts?per_page=5&status=draft',
        'query'=>array('page'=>2),
        'fields'=>'id,title',
        'max_length'=>12000,
    ));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($result),'reference option aliases should dispatch');
    $query=$result['data']['query']??array();
    assert_same('5',(string)($query['per_page']??''),'embedded route query forwarded');
    assert_same('draft',$query['status']??null,'embedded status forwarded');
    assert_same(2,(int)($query['page']??0),'query alias forwarded');
    assert_same('id,title',$query['_fields']??null,'string fields normalized');
});

parity_test('public REST explicit query object wins over embedded duplicate keys',function(){
    set_test_routes(array('/wp/v2/posts'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('query'=>$r->get_query_params()),200);};
    $result=WPVDMCP_Parity::execute('rest_api',array(
        'method'=>'GET','route'=>'/wp/v2/posts?page=1&per_page=10','query'=>array('page'=>3)
    ));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_same(3,(int)($result['data']['query']['page']??0),'explicit query should override embedded query');
    assert_same(10,(int)($result['data']['query']['per_page']??0),'non-conflicting embedded query preserved');
});

parity_test('public REST fields all requests unfiltered response',function(){
    set_test_routes(array('/wp/v2/posts'=>array('GET')));
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('query'=>$r->get_query_params(),'content'=>'full'),200);};
    $result=WPVDMCP_Parity::execute('rest_api',array('method'=>'GET','route'=>'/wp/v2/posts','fields'=>'all'));
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!isset($result['data']['query']['_fields']),'fields=all must not inject WordPress _fields');
    assert_same('full',$result['data']['content']??null,'full response preserved');
});

parity_test('public REST write aliases bind normalized query to approval payload',function(){
    set_test_routes(array('/wp/v2/pages/12'=>array('POST')));
    $GLOBALS['wp_current_user']=1;
    unset($GLOBALS['wp_options']['wpvibe_bypass_approvals']);
    $GLOBALS['rest_dispatch_callback']=function($r){return new WP_REST_Response(array('query'=>$r->get_query_params(),'body'=>$r->get_json_params()),200);};
    $args=array(
        'method'=>'POST','route'=>'/wp/v2/pages/12?context=edit','query'=>array('lang'=>'en'),
        'body'=>array('title'=>'Reference aliases')
    );
    $approval=WPVDMCP_Parity::execute('rest_api_write',$args);
    assert_same('approval_required',$approval['status']??null,'reference write aliases should request approval');
    WPVDMCP_Approvals::approve($approval['approval_id'],1);
    $args['approval_id']=$approval['approval_id'];
    $done=WPVDMCP_Parity::execute('rest_api_write',$args);
    unset($GLOBALS['rest_dispatch_callback']);
    assert_true(!is_wp_error($done),'approved reference alias write should execute');
    assert_same('edit',$done['data']['query']['context']??null,'embedded write query preserved');
    assert_same('en',$done['data']['query']['lang']??null,'query alias preserved');
});
