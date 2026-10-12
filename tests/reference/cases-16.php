<?php
parity_test('current Works-with-AI additions are audited and ship dedicated safe playbooks',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    assert_same('2026-10-12',$manifest['audited_at']??null,'reference audit date must cover the current Works-with-AI catalog');

    foreach(array('advanced-custom-fields','ultimate-addons-for-elementor','disable-comments') as $slug){
        assert_true(in_array($slug,$manifest['works_with_ai_integrations']??array(),true),'Works-with manifest missing '.$slug);
        assert_true(in_array($slug,$manifest['cookbook_integrations']??array(),true),'cookbook manifest missing '.$slug);
        $skill=WPVDMCP_Parity::execute('load_skill',array('skill'=>$slug));
        assert_true(!is_wp_error($skill),'skill must load: '.$slug);
        assert_true(strlen($skill['instructions']??'')>500,'skill must contain a substantive workflow: '.$slug);
    }

    $acf=WPVDMCP_Parity::execute('load_skill',array('skill'=>'advanced-custom-fields'));
    foreach(array('acf/settings/enable_acf_ai','code_snippet','acf-field-management','Show in REST API','run_ability') as $needle){
        assert_true(strpos($acf['instructions']??'',$needle)!==false,'ACF playbook missing '.$needle);
    }

    $uae=WPVDMCP_Parity::execute('load_skill',array('skill'=>'ultimate-addons-for-elementor'));
    foreach(array('hfe-templates','Enable Edit Abilities','classic theme','discover_abilities','get_ability_info','run_ability') as $needle){
        assert_true(strpos($uae['instructions']??'',$needle)!==false,'UAE playbook missing '.$needle);
    }

    $comments=WPVDMCP_Parity::execute('load_skill',array('skill'=>'disable-comments'));
    foreach(array('disable-comments/get-status','disable-comments/delete-comments','order_note','is_network_active','run_ability') as $needle){
        assert_true(strpos($comments['instructions']??'',$needle)!==false,'Disable Comments playbook missing '.$needle);
    }
});
