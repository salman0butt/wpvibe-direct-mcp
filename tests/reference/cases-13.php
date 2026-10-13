<?php
parity_test('reference manifest records the current Direct-only armored retry extension',function(){
    $manifest=WPVDMCP_Parity::execute('reference_parity_manifest',array());
    $extensions=$manifest['site_local_extensions']??array();
    assert_true(in_array('call_armored',$extensions,true),'call_armored must be recorded as a Direct-only local extension');
});
parity_test('release workflow verifies the focused Abilities JSON regression before and after packaging',function(){
    $workflow_path=dirname(__DIR__,2).'/.github/workflows/release.yml';
    // The release ZIP intentionally excludes GitHub-only workflow metadata. In a
    // source checkout, protect the workflow wiring; in an extracted package,
    // the release job itself executes the focused regression directly.
    if(!is_file($workflow_path)){
        return;
    }
    $workflow=(string)file_get_contents($workflow_path);
    assert_same(2,substr_count($workflow,'php tests/abilities-json-body-run.php'),'release workflow must run the focused Abilities request-body regression on source and extracted package');
});

parity_test('release workflow publishes only current main after both exact-SHA CI gates are green',function(){
    $workflow_path=dirname(__DIR__,2).'/.github/workflows/release.yml';
    if(!is_file($workflow_path)){
        return;
    }
    $workflow=(string)file_get_contents($workflow_path);
    assert_true(strpos($workflow,'workflow_dispatch:')===false,'publishing release workflow must not allow arbitrary manual ref dispatch');
    assert_true(strpos($workflow,'workflows: ["PHP parity checks", "WordPress Elementor E2E"]')!==false,'release must wake after either required CI gate completes');
    assert_true(strpos($workflow,'actions: read')!==false,'release gate must have read-only Actions access for exact-SHA verification');
    assert_true(substr_count($workflow,'git fetch --no-tags origin main')>=2,'release must refresh remote main both before build and immediately before tag');
    assert_true(strpos($workflow,'refs/remotes/origin/main')!==false,'release must compare its target SHA with the current remote main SHA');
    assert_true(substr_count($workflow,'head_sha=$TARGET_SHA')>=2,'release must query exact-SHA workflow gates both before build and before tag');
    assert_true(strpos($workflow,'PHP parity checks')!==false && strpos($workflow,'WordPress Elementor E2E')!==false,'release must require both PHP parity and WordPress Elementor E2E gates');
    assert_true(substr_count($workflow,'completed:success')>=2,'release must require completed success for both exact-SHA gates');
    assert_true(strpos($workflow,"steps.gates.outputs.ready == 'true'")!==false,'release metadata/tag path must be unreachable until the exact-SHA gate is ready');
    $tag_step=strpos($workflow,'- name: Verify or create immutable tag');
    assert_true($tag_step!==false,'immutable tag step missing');
    $tag_body=substr($workflow,$tag_step);
    $tag_fetch=strpos($tag_body,'git fetch --no-tags origin main');
    $tag_create=strpos($tag_body,'git tag -a "$TAG" "$TARGET_SHA"');
    assert_true($tag_fetch!==false && $tag_create!==false && $tag_fetch<$tag_create,'final current-main and CI revalidation must occur inside the immutable tag step immediately before tag creation');
});

parity_test('release workflow never overwrites assets for an already published immutable release',function(){
    $workflow_path=dirname(__DIR__,2).'/.github/workflows/release.yml';
    if(!is_file($workflow_path)){
        return;
    }
    $workflow=(string)file_get_contents($workflow_path);
    assert_true(strpos($workflow,'gh release view "v$VERSION"')!==false,'metadata resolution must detect an already published release before rebuilding it');
    assert_true(strpos($workflow,'--clobber')===false,'immutable release workflow must never overwrite existing release assets');
    assert_true(strpos($workflow,'gh release upload')===false,'an existing release must be skipped rather than receiving replacement assets');
});
