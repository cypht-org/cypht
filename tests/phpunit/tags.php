<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_Test_Tags
 */
class Hm_Test_Tags extends TestCase {

    public function setUp(): void {
        require __DIR__.'/helpers.php';

    }
    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_get() {
        $tags = Hm_Tags_Wrapper::getAll();
        $this->assertEquals($tags, array());
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_crud() {
        $tag = array(
            'name' => html_entity_decode('Cypht', ENT_QUOTES),
            'parent' => null
        );
        $tag_id = Hm_Tags_Wrapper::add($tag);
        $this->assertNotEmpty($tag_id);

        $tag = Hm_Tags_Wrapper::get($tag_id);
        $this->assertEquals($tag['name'], 'Cypht');

        $tag_with_parent = array(
            'name' => html_entity_decode('Child', ENT_QUOTES),
            'parent' => $tag_id
        );
        $child_id = Hm_Tags_Wrapper::add($tag_with_parent);
        $child = Hm_Tags_Wrapper::get($child_id);
        $this->assertEquals($tag['id'], $child['parent']);

        $child['name'] = 'Child 2';
        Hm_Tags_Wrapper::edit($child_id, $child);
        $child = Hm_Tags_Wrapper::get($child_id);
        $this->assertEquals($child['name'], 'Child 2');

        $this->assertCount(2, Hm_Tags_Wrapper::getAll());

        Hm_Tags_Wrapper::del($child_id);
        $this->assertEmpty(Hm_Tags_Wrapper::get($child_id));

        $this->assertCount(1, Hm_Tags_Wrapper::getAll());
    }
}
