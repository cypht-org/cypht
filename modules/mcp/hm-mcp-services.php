<?php

/**
 * Services shared by the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Creates and shares the objects one request needs
 * @subpackage mcp/lib
 */
class Hm_MCP_Services {

    /* site configuration */
    public $site_config;

    /* Hm_MCP_Config */
    public $config;

    /* optional callable(Hm_MCP_Principal): Hm_MCP_Context, used by tests */
    public $context_factory = null;

    private $store = null;
    private $catalog = null;
    private $validator = null;
    private $auth = null;

    /**
     * @param object $site_config site configuration
     * @param Hm_MCP_Config|null $config MCP settings
     */
    public function __construct($site_config, $config = null) {
        $this->site_config = $site_config;
        $this->config = $config ?: new Hm_MCP_Config($site_config);
    }

    /**
     * @return Hm_MCP_Store
     */
    public function store() {
        if ($this->store === null) {
            $this->store = new Hm_MCP_Store($this->site_config);
        }
        return $this->store;
    }

    /**
     * @param Hm_MCP_Store $store replacement storage
     * @return void
     */
    public function set_store($store) {
        $this->store = $store;
    }

    /**
     * @return Hm_MCP_Catalog
     */
    public function catalog() {
        if ($this->catalog === null) {
            $this->catalog = new Hm_MCP_Catalog();
        }
        return $this->catalog;
    }

    /**
     * @return Mcp\Capability\Discovery\SchemaValidator
     */
    public function validator() {
        if ($this->validator === null) {
            $this->validator = new Mcp\Capability\Discovery\SchemaValidator();
        }
        return $this->validator;
    }

    /**
     * @return Hm_MCP_Auth
     */
    public function auth() {
        if ($this->auth === null) {
            $this->auth = new Hm_MCP_Auth($this->store(), $this->config);
        }
        return $this->auth;
    }

    /**
     * Mail context for a principal
     * @param Hm_MCP_Principal $principal authenticated principal
     * @return Hm_MCP_Context
     */
    public function context($principal) {
        if ($this->context_factory) {
            return ($this->context_factory)($principal);
        }
        return Hm_MCP_Context::load($this->site_config, $this->config, $this->store(), $principal);
    }

    /**
     * @param Hm_MCP_Principal $principal authenticated principal
     * @return Hm_MCP_Mail
     */
    public function mail($principal) {
        return new Hm_MCP_Mail($this, $principal);
    }

    /**
     * @param Hm_MCP_Principal $principal authenticated principal
     * @param string $channel mcp or rest
     * @return Hm_MCP_Executor
     */
    public function executor($principal, $channel) {
        return new Hm_MCP_Executor($this, $principal, $channel);
    }

    /**
     * @return Hm_MCP_Endpoint
     */
    public function mcp_endpoint() {
        return new Hm_MCP_Endpoint($this);
    }

    /**
     * @return Hm_MCP_Rest
     */
    public function rest() {
        return new Hm_MCP_Rest($this);
    }
}
