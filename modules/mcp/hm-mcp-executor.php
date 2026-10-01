<?php

/**
 * Runs catalog operations for MCP and REST requests
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Single entry point for operations: checks the permission and the arguments,
 * runs the operation, maps failures to client errors and records the activity
 * @subpackage mcp/lib
 */
class Hm_MCP_Executor {

    /* operations allowed per connection in the rate limit window */
    const RATE_LIMIT = 600;
    const RATE_WINDOW = 600;

    protected $services;
    protected $principal;
    protected $channel;
    protected $mail = null;

    /**
     * @param Hm_MCP_Services $services services
     * @param Hm_MCP_Principal $principal authenticated principal
     * @param string $channel mcp or rest
     */
    public function __construct($services, $principal, $channel) {
        $this->services = $services;
        $this->principal = $principal;
        $this->channel = $channel;
    }

    /**
     * @return Hm_MCP_Mail
     */
    protected function mail() {
        if ($this->mail === null) {
            $this->mail = $this->services->mail($this->principal);
        }
        return $this->mail;
    }

    /**
     * Run an operation
     * @param string $name operation name
     * @param array $args arguments
     * @return array ['ok' => bool, 'result' => array|null, 'error' => Hm_MCP_Error|null]
     */
    public function run($name, $args) {
        $op = $this->services->catalog()->get($name);
        $mail = null;
        try {
            if (!$op) {
                throw new Hm_MCP_Error('not_found', 'Unknown operation.');
            }
            if (!$this->principal->can($op['permission'])) {
                throw new Hm_MCP_Error('permission_denied', sprintf('This connection does not have the "%s" permission. It can be enabled in Cypht under Settings, API and MCP.',
                    Hm_MCP_Permissions::label($op['permission'])));
            }
            $args = self::apply_defaults($op['input'], is_array($args) ? $args : []);
            $errors = $this->services->validator()->validateAgainstJsonSchema($args, Hm_MCP_Catalog::normalize($op['input']));
            if ($errors) {
                throw new Hm_MCP_Error('invalid_argument', 'Invalid arguments: '.self::describe_errors($errors));
            }
            if (!$this->services->store()->rate_limit('ops:'.$this->principal->connection_id(), self::RATE_LIMIT, self::RATE_WINDOW)) {
                throw new Hm_MCP_Error('rate_limited', 'Too many requests for this connection. Wait a few minutes and try again.');
            }
            $mail = $this->mail();
            $mail->audit = [];
            $result = $mail->{$op['handler']}($args);
            $this->log($name, $op, 'ok', $mail->audit);
            return ['ok' => true, 'result' => $result, 'error' => null];
        } catch (Hm_MCP_Error $e) {
            $outcome = in_array($e->error_code, ['permission_denied', 'account_not_allowed'], true) ? 'denied' : 'error';
            $this->log($name, $op, $outcome, ['error' => $e->error_code]);
            return ['ok' => false, 'result' => null, 'error' => $e];
        } catch (Throwable $e) {
            Hm_Debug::add(sprintf('MCP operation %s failed: %s in %s:%d', $name, $e->getMessage(), $e->getFile(), $e->getLine()), 'danger');
            $this->log($name, $op, 'error', ['error' => 'internal_error']);
            return ['ok' => false, 'result' => null,
                'error' => new Hm_MCP_Error('internal_error', 'The operation failed because of a server error.')];
        } finally {
            if ($mail !== null) {
                $mail->finish();
                $this->mail = null;
            }
        }
    }

    /**
     * Fill in default values of top level arguments
     * @param array $schema input schema
     * @param array $args arguments
     * @return array
     */
    public static function apply_defaults($schema, $args) {
        foreach ($schema['properties'] ?? [] as $name => $property) {
            if (!array_key_exists($name, $args) && array_key_exists('default', $property)) {
                $args[$name] = $property['default'];
            }
        }
        return $args;
    }

    /**
     * @param array $errors validation errors
     * @return string short description
     */
    public static function describe_errors($errors) {
        $parts = [];
        foreach (array_slice($errors, 0, 3) as $error) {
            $pointer = $error['pointer'] ?? '';
            $parts[] = ($pointer !== '' && $pointer !== '/' ? ltrim($pointer, '/').': ' : '').($error['message'] ?? 'invalid value');
        }
        return implode('; ', $parts);
    }

    /**
     * Record an operation in the activity log. Only counts and identifiers, never content.
     * @return void
     */
    protected function log($name, $op, $outcome, $summary) {
        $this->services->store()->log_activity([
            'username' => $this->principal->username,
            'connection_id' => $this->principal->connection_id(),
            'connection_name' => $this->principal->connection_name(),
            'channel' => $this->channel,
            'operation' => $op ? $name : 'unknown',
            'permission' => $op['permission'] ?? null,
            'outcome' => $outcome,
            'summary' => $summary ?: null,
        ]);
    }
}
