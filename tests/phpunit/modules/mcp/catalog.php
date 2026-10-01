<?php

use PHPUnit\Framework\TestCase;

/**
 * tests for Hm_MCP_Catalog and the generated OpenAPI document
 */
class Hm_Test_MCP_Catalog extends TestCase {

    public function setUp(): void {
        require_once __DIR__.'/fakes.php';
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_operations_are_well_formed() {
        $catalog = new Hm_MCP_Catalog();
        $routes = [];
        foreach ($catalog->all() as $name => $op) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z_]{1,63}$/', $name);
            $this->assertContains($op['permission'], Hm_MCP_Permissions::keys(), $name);
            $this->assertArrayHasKey($op['kind'], Hm_MCP_Catalog::ANNOTATIONS, $name);
            $this->assertTrue(method_exists('Hm_MCP_Mail', $op['handler']), $name);
            $this->assertNotEmpty($op['title']);
            $this->assertGreaterThan(20, strlen($op['description']));
            $this->assertLessThanOrEqual(64, strlen($op['invoking']));
            $this->assertLessThanOrEqual(64, strlen($op['invoked']));
            $this->assertSame('object', $op['input']['type'], $name);
            $this->assertFalse($op['input']['additionalProperties'], $name);
            $this->assertSame('object', $op['output']['type'], $name);
            foreach ($op['input']['required'] ?? [] as $required) {
                $this->assertArrayHasKey($required, $op['input']['properties'], $name);
            }
            if (!empty($op['rest'])) {
                $key = implode(' ', $op['rest']);
                $this->assertArrayNotHasKey($key, $routes, 'duplicate route '.$key);
                $routes[$key] = true;
                preg_match_all('/\{([a-z_]+)\}/', $op['rest'][1], $matches);
                foreach ($matches[1] as $param) {
                    $this->assertContains($param, $op['input']['required'] ?? [], $name.' path parameter');
                }
            }
            if ($op['kind'] === 'read') {
                $this->assertSame('read', $op['permission'], $name);
            }
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_schemas_compile_and_validate_sample_values() {
        $validator = new Mcp\Capability\Discovery\SchemaValidator();
        $catalog = new Hm_MCP_Catalog();
        foreach ($catalog->all() as $name => $op) {
            $errors = $validator->validateAgainstJsonSchema(['__unknown__' => 1], Hm_MCP_Catalog::normalize($op['input']));
            $this->assertNotEmpty($errors, $name.' accepts unknown arguments');
            foreach ($errors as $error) {
                $this->assertNotSame('internal', $error['keyword'], $name.' input schema: '.$error['message']);
            }
            $errors = $validator->validateAgainstJsonSchema(new stdClass(), Hm_MCP_Catalog::normalize($op['output']));
            foreach ($errors as $error) {
                $this->assertNotSame('internal', $error['keyword'], $name.' output schema: '.$error['message']);
            }
        }
        $this->assertEquals(new stdClass(), Hm_MCP_Catalog::normalize(['type' => 'object', 'properties' => []])['properties']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_allowed_filters_by_permission() {
        $catalog = new Hm_MCP_Catalog();
        $none = array_fill_keys(Hm_MCP_Permissions::keys(), false);
        $this->assertSame([], $catalog->allowed($none));
        $read = $catalog->allowed(array_merge($none, ['read' => true]));
        $this->assertArrayHasKey('list_messages', $read);
        $this->assertArrayHasKey('search', $read);
        $this->assertArrayHasKey('fetch', $read);
        foreach ($read as $op) {
            $this->assertSame('read', $op['permission']);
        }
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_tool_definitions_for_chatgpt() {
        $catalog = new Hm_MCP_Catalog();
        $tool = json_decode(json_encode(Hm_MCP_Endpoint::tool('get_profile', $catalog->get('get_profile'))), true);
        $this->assertSame([['type' => 'oauth2', 'scopes' => ['mail.read']]], $tool['securitySchemes']);
        $this->assertSame($tool['securitySchemes'], $tool['_meta']['securitySchemes']);
        $this->assertTrue($tool['_meta']['openai/profile']);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
        $this->assertFalse($tool['annotations']['destructiveHint']);
        $this->assertFalse($tool['annotations']['openWorldHint']);
        $this->assertFalse($tool['outputSchema']['additionalProperties']);
        $this->assertSame(['id'], $tool['outputSchema']['required']);
        $search = json_decode(json_encode(Hm_MCP_Endpoint::tool('search', $catalog->get('search'))), true);
        $this->assertSame(['query'], array_keys($search['inputSchema']['properties']));
        $fetch = json_decode(json_encode(Hm_MCP_Endpoint::tool('fetch', $catalog->get('fetch'))), true);
        $this->assertSame(['id'], array_keys($fetch['inputSchema']['properties']));
        $this->assertSame(['id', 'title', 'text', 'url'], $fetch['outputSchema']['required']);
        $empty = json_encode(Hm_MCP_Endpoint::tool('list_accounts', $catalog->get('list_accounts')));
        $this->assertStringContainsString('"properties":{}', $empty);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_openapi_document() {
        list($services) = hm_mcp_fake_services([], []);
        $doc = json_decode(json_encode($services->rest()->openapi()), true);
        $this->assertSame('3.1.0', $doc['openapi']);
        $this->assertSame('https://mail.example.com/api/v1', $doc['servers'][0]['url']);
        $this->assertSame('bearer', $doc['components']['securitySchemes']['bearerAuth']['scheme']);
        $ids = [];
        foreach ($doc['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $this->assertArrayNotHasKey($operation['operationId'], $ids);
                $ids[$operation['operationId']] = true;
                $this->assertArrayHasKey('200', $operation['responses']);
                if ($method === 'get') {
                    $this->assertArrayNotHasKey('requestBody', $operation);
                }
            }
        }
        $get = $doc['paths']['/messages/{message_id}']['get'];
        $this->assertSame('path', $get['parameters'][0]['in']);
        $this->assertTrue($get['parameters'][0]['required']);
        $this->assertContains('max_chars', array_column($get['parameters'], 'name'));
        $this->assertArrayHasKey('requestBody', $doc['paths']['/tools/search_messages']['post']);
        $this->assertSame('read', $doc['paths']['/accounts']['get']['x-cypht-permission']);
    }

    /**
     * @preserveGlobalState disabled
     * @runInSeparateProcess
     */
    public function test_rest_query_coercion() {
        $schema = (new Hm_MCP_Catalog())->get('list_messages')['input'];
        $this->assertSame(['limit' => 5, 'unread_only' => true, 'flagged_only' => false, 'view' => 'unread', 'offset' => 'x'],
            Hm_MCP_Rest::coerce($schema, ['limit' => '5', 'unread_only' => 'true', 'flagged_only' => '0', 'view' => 'unread', 'offset' => 'x']));
    }
}
