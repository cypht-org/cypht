<?php

namespace Services;

class ImapConnectionManager
{
    private array $connections;

    /**
     * Constructor initializes the manager with an optional list of connections.
     *
     * @param array $initialConnections Initial list of IMAP connections.
     */
    public function __construct(array $initialConnections = [])
    {
        $this->connections = $initialConnections;
    }

    /**
     * Returns all currently managed connections.
     *
     * @return array List of connections.
     */
    public function getAll(): array
    {
        return $this->connections;
    }

    /**
     * Adds a new connection if it does not already exist (identified by 'id').
     *
     * @param array $conn Connection data to add.
     * @return bool Returns true if the connection was added, false if a connection with the same 'id' already exists.
     */
    public function add(array $conn): bool
    {
        if ($this->exists($conn['id'] ?? '', 'id')) {
            return false;
        }
        $this->connections[] = $conn;
        return true;
    }

    /**
     * Edits an existing connection based on a specific column's value.
     * Merges existing data with the new data provided.
     *
     * @param string $column The column name to use as search criteria.
     * @param array $conn New data to merge with the existing connection.
     * @return bool Returns true if the connection was updated, false otherwise.
     */
    public function edit(string $column, array $conn): bool
    {
        if (!isset($conn[$column])) {
            return false;
        }

        foreach ($this->connections as &$existing) {
            if (isset($existing[$column]) && $existing[$column] === $conn[$column]) {
                $existing = array_merge($existing, $conn);
                return true;
            }
        }
        return false;
    }

    /**
     * Removes all connections where the value of a specific column matches the given value.
     *
     * @param string $column The column name used as removal criteria.
     * @param string $value The value to compare against for removal.
     * @return int Number of connections removed.
     */
    public function removeByColumn(string $column, string $value): int
    {
        $beforeCount = count($this->connections);
        $this->connections = array_filter(
            $this->connections,
            fn($c) => isset($c[$column]) && $c[$column] !== $value
        );
        return $beforeCount - count($this->connections);
    }

    /**
     * Checks if a connection exists based on a given value and column.
     *
     * @param string $value Value to search for.
     * @param string $column Column to search in (default is 'id').
     * @return bool Returns true if a matching connection is found, false otherwise.
     */
    public function exists(string $value, $column = 'id'): bool
    {
        foreach ($this->connections as $conn) {
            if (isset($conn[$column]) && $conn[$column] === $value) {
                return true;
            }
        }
        return false;
    }
}
