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

parity_test('held-out genuine WordPress requests preserve relevant workflow intent', function() {
    $queries = array(
        'Configure Merchant Pro discount offers in our WooCommerce store' => 'merchant',
        'Set Yoast SEO canonical URLs, not Rank Math' => 'yoast-seo',
        'Improve organic visibility by changing article meta descriptions' => 'seo',
        'Speed up the site by eliminating render-blocking CSS' => 'performance',
        'Screen-reader users cannot follow the tab order' => 'accessibility',
        'We need a reusable playbook for onboarding new editors' => 'saved-skills',
    );
    foreach ($queries as $query => $expected) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same($expected, $result['matches'][0]['slug'] ?? null, 'independent held-out WordPress intent: '.$query);
    }
});

parity_test('held-out offsite requests do not imply WordPress plugin workflows', function() {
    foreach (array('Find hotel booking appointments for a conference', 'Disable comments on my YouTube channel') as $query) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same(array(), $result['matches'] ?? null, 'offsite intent must abstain: '.$query);
    }
});

parity_test('explicitly external platforms and negated WordPress context never route to local workflows', function() {
    $offsite = array(
        'Disable comments on a YouTube video',
        'Fix comments on my YouTube videos',
        'Hotel reservations and appointment booking for a conference',
        'Book hotel rooms and meeting appointments for our guests',
        'Not WordPress; disable comments on my YouTube channel',
        'Merchant shipping discounts for my Shopify store',
        'Search stock photos for my personal Instagram page',
    );
    foreach ($offsite as $query) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same(array(), $result['matches'] ?? null, 'offsite platform must not route to WordPress skill: '.$query);
    }
});

parity_test('word variants of built-in workflows remain discoverable', function() {
    foreach (array(
        'Improve keyboard-only navigation for the blind' => 'accessibility',
        'Create repeatable runbooks for editors' => 'saved-skills',
        'Use RankMath instead of Yoast for SEO' => 'rank-math',
    ) as $query => $expected) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same($expected, $result['matches'][0]['slug'] ?? null, 'workflow variant: '.$query);
    }
});
