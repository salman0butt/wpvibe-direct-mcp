<?php

parity_test('specific named workflow beats broad related WordPress provider on natural tasks', function() {
    $expected = array(
        'edit block theme global styles' => 'block-themes',
        'update FluentCart product inventory' => 'fluentcart',
        'configure Merchant WooCommerce offers' => 'merchant',
        'change Botiga WooCommerce storefront' => 'botiga',
    );
    foreach ($expected as $query => $slug) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same($slug, $result['matches'][0]['slug'] ?? null,
            'specific named skill should outrank related generic skill: '.$query);
    }
});

parity_test('all built-in exact skill names override natural-language search heuristics', function() {
    $catalog = array_merge(WPVDMCP_Skills::list_metadata(), array_values(WPVDMCP_Parity_Skills::extra_catalog()));
    assert_same(67, count($catalog), 'expected complete built-in skill catalog');
    foreach ($catalog as $meta) {
        $slug = $meta['slug'];
        $search = WPVDMCP_Parity::execute('load_skill', array('query' => $slug));
        assert_same($slug, $search['matches'][0]['slug'] ?? null, 'exact identifier must rank first: '.$slug);
        assert_same(100, $search['matches'][0]['score'] ?? null, 'exact identifier must retain score 100: '.$slug);
        foreach ($search['matches'] as $result) {
            assert_true(!isset($result['instructions']), 'search metadata must not expose instruction bodies: '.$slug);
        }
        $loaded = WPVDMCP_Parity::execute('load_skill', array('name' => $slug));
        assert_same($slug, $loaded['skill'] ?? null, 'exact selected name must load a skill: '.$slug);
        assert_true(isset($loaded['instructions']) && strlen($loaded['instructions']) > 0, 'selected skill should retain full instructions: '.$slug);
    }
});
