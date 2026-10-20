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

parity_test('WordPress integrations with external platforms are not mistaken for offsite requests', function() {
    foreach (array(
        'Fix an Instagram feed on our website using Smash Balloon' => 'smash-balloon',
        'Integrate Facebook social feed into Elementor homepage' => 'elementor',
        'Add a LinkedIn share button to my WooCommerce product page' => 'woocommerce',
        'Embed a YouTube video in my homepage Gutenberg block' => 'gutenberg',
        'Need hotel room reservations with Amelia on my website' => 'amelia',
        'Not WordPress.com, this is my self-hosted WordPress site; fix Yoast SEO titles' => 'yoast-seo',
        'My website is not WordPress.com; update a WPForms contact form' => 'wpforms',
		'Configure Smash Balloon Facebook feed' => 'smash-balloon',
		'Fix Elementor Instagram feed' => 'elementor',
    ) as $query => $expected) {
        $response = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same($expected, $response['matches'][0]['slug'] ?? null,
            'named WordPress plugin should override offsite context: '.$query);
    }
});

parity_test('explicit not-a-WordPress site scope and single-token vendor alias', function() {
    $offsite = WPVDMCP_Parity::execute('load_skill', array('query' => 'This is not a WordPress site; set up WooCommerce'));
    assert_same(array(), $offsite['matches'] ?? null, 'explicit not a WordPress site must abstain despite plugin name');
    $named = WPVDMCP_Parity::execute('load_skill', array('query' => 'RankMath'));
    assert_same('rank-math', $named['matches'][0]['slug'] ?? null,
        'single-token concatenated plugin brand should be discoverable');
});

parity_test('exact user saved skill overrides external-platform abstention', function() {
    $before = $GLOBALS['wp_options'];
    try {
        $GLOBALS['wp_options'][WPVDMCP_Parity_Skills::OPTION] = array(
            'instagram' => array(
                'slug' => 'instagram',
                'title' => 'Instagram workflow',
                'description' => 'User-owned social media workflow for a connected WordPress site.',
                'instructions' => 'PRIVATE USER SKILL BODY',
                'version' => 1,
                'reference_files' => array(),
            ),
        );
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => 'instagram'));
        assert_same('instagram', $result['matches'][0]['slug'] ?? null,
            'exact locally saved skill slug must outrank external-platform abstention');
        assert_same(100, $result['matches'][0]['score'] ?? null,
            'exact local skill retains score 100');
        assert_true(!isset($result['matches'][0]['instructions']), 'search must not expose private body');
    } finally { $GLOBALS['wp_options'] = $before; }
});

parity_test('longer WordPress and competing-plugin negations are respected', function() {
    foreach (array(
        'I do not use WordPress; fix Facebook comments',
        'This is a website outside of WordPress, fix Instagram feed',
    ) as $query) {
        $result = WPVDMCP_Parity::execute('load_skill', array('query' => $query));
        assert_same(array(), $result['matches'] ?? null, 'non-WordPress scope must abstain: '.$query);
    }
    $result = WPVDMCP_Parity::execute('load_skill', array('query' => 'Please do not use Rank Math; update the Yoast SEO canonical'));
    assert_same('yoast-seo', $result['matches'][0]['slug'] ?? null, 'negated Rank Math should not outrank requested Yoast SEO');
});
