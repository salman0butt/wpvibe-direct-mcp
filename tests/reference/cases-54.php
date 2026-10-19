<?php

parity_test('MCP prompts list uses descriptive skill metadata without exposing saved instruction bodies', function() {
    $options_before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION] = array(
            'check-brand' => array(
                'slug' => 'check-brand',
                'title' => 'Review brand guidelines',
                'description' => 'Use before changing public-facing colors or brand typography.',
                'instructions' => 'PRIVATE FULL BRAND WORKFLOW BODY',
                'version' => 1,
                'reference_files' => array(),
            ),
        );
        $response = WPVDMCP_Server::instance()->handle(test_request(array(
            'jsonrpc' => '2.0', 'id' => 'skills-prompt-list', 'method' => 'prompts/list',
            'params' => array('_meta' => array(
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) array(),
            )),
        ), '2026-07-28'));
        $prompts = $response->get_data()['result']['prompts'] ?? array();
        $indexed = array();
        foreach ($prompts as $prompt) {
            $indexed[$prompt['name']] = $prompt;
            assert_true(strpos(wp_json_encode($prompt), 'PRIVATE FULL BRAND WORKFLOW BODY') === false, 'prompt metadata must not reveal saved instruction body');
        }
        assert_same(count(WPVDMCP_Parity_Skills::metadata()), count($prompts), 'list must enumerate all built-in and saved skills');
        assert_same('Review brand guidelines', $indexed['check-brand']['title'] ?? null, 'MCP prompt title must reflect the saved skill title');
        assert_same('Use before changing public-facing colors or brand typography.', $indexed['check-brand']['description'] ?? null, 'MCP prompt description must preserve use-case metadata');
        assert_same('Elementor Atomic / Abilities', $indexed['elementor-atomic']['title'] ?? null, 'built-in prompt must expose its meaningful display title');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
    }
});

parity_test('MCP prompts get loads only the selected skill with its display description', function() {
    $options_before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION] = array(
            'check-brand' => array(
                'slug' => 'check-brand',
                'title' => 'Review brand guidelines',
                'description' => 'Use before changing public-facing colors or brand typography.',
                'instructions' => 'PRIVATE FULL BRAND WORKFLOW BODY',
                'version' => 1,
                'reference_files' => array(),
            ),
        );
        $response = WPVDMCP_Server::instance()->handle(test_request(array(
            'jsonrpc' => '2.0', 'id' => 'skills-prompt-get', 'method' => 'prompts/get',
            'params' => array('name' => 'check-brand', '_meta' => array(
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) array(),
            )),
        ), '2026-07-28'));
        $result = $response->get_data()['result'] ?? array();
        assert_same('Use before changing public-facing colors or brand typography.', $result['description'] ?? null, 'selected prompt should describe when the skill is appropriate');
        assert_same('PRIVATE FULL BRAND WORKFLOW BODY', $result['messages'][0]['content']['text'] ?? null, 'get must still return the complete selected instructions');

        $missing = WPVDMCP_Server::instance()->handle(test_request(array(
            'jsonrpc' => '2.0', 'id' => 'missing-skill-prompt', 'method' => 'prompts/get',
            'params' => array('name' => 'missing-skill-prompt', '_meta' => array(
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => (object) array(),
            )),
        ), '2026-07-28'));
        assert_same(-32602, $missing->get_data()['error']['code'] ?? null, 'unknown prompt should still fail with invalid parameters');
    } finally {
        $GLOBALS['wp_options'] = $options_before;
    }
});

parity_test('Abilities API skill describes safe idempotent destructive method selection', function() {
    $loaded = WPVDMCP_Parity_Skills::load('abilities-api');
    assert_true(!is_wp_error($loaded), 'built-in WordPress Abilities skill should load');
    $body = $loaded['instructions'] ?? '';
    assert_true(strpos($body, 'destructive and idempotent') !== false, 'destructive abilities use DELETE only when idempotent');
    assert_true(strpos($body, 'non-idempotent') !== false && strpos($body, 'POST') !== false, 'non-idempotent destructive writes must use POST');
    assert_true(strpos($body, 'destructive as DELETE') === false, 'remove misleading blanket destructive mapping');
});

parity_test('all 67 shipped skills advertise concrete when-to-use discovery descriptions', function() {
    $catalog = array_merge(WPVDMCP_Skills::list_metadata(), array_values(WPVDMCP_Parity_Skills::extra_catalog()));
    assert_same(67, count($catalog), 'all shipped built-in skills must have indexed metadata');
    foreach ($catalog as $meta) {
        $description = $meta['description'] ?? '';
        assert_true(is_string($description) && strlen($description) >= 35 && strlen($description) <= 300,
            'built-in skill should have bounded meaningful use-case description: '.($meta['slug']??''));
    }
});

parity_test('skill discovery finds the intended workflow for common real-world tasks', function() {
    $intents = array(
        'stock' => 'woocommerce',
        'inventory' => 'woocommerce',
        'checkout' => 'cartflows',
        'appointments' => 'amelia',
        'donations' => 'charitable',
        'product feed' => 'adtribes-product-feed',
        'site migration' => 'duplicator',
        'email automation' => 'fluentcrm',
        'global styles' => 'elementor-atomic',
        'GDPR privacy' => 'security',
    );
    foreach ($intents as $query => $required) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_true(is_array($result), 'intent search must complete: '.$query);
        $matches = $result['matches'] ?? array();
        $slugs = array_map(function($item) {return $item['slug'] ?? '';}, $matches);
        assert_true(in_array($required, $slugs, true), 'intent "'.$query.'" must discover '.$required);
        assert_true(!empty($matches[0]['description']), 'search results explain why the skill applies: '.$query);
        foreach ($matches as $item) assert_true(!isset($item['instructions']), 'search must not load bodies');
    }
    $ranked = WPVDMCP_Parity::execute('load_skill', array('query' => 'stock'));
    assert_same('woocommerce', $ranked['matches'][0]['slug'] ?? null, 'WooCommerce stock/inventory skill outranks generic stock photos');
});

parity_test('form searches rank the form-builder skill ahead of performance substring matches', function() {
    foreach (array('form', 'forms', 'contact form') as $query) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same('wpforms', $result['matches'][0]['slug'] ?? null,
            'WPForms should rank first for form intent: '.$query);
    }
    $performance = WPVDMCP_Parity::execute('load_skill', array('query' => 'performance'));
    assert_same('performance', $performance['matches'][0]['slug'] ?? null,
        'exact performance lookups must still select the performance skill');
});
