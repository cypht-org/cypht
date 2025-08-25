<?php

trait Searchable {
    /**
     * Return items matching $match in column $column.
     *
     * @param mixed  $match
     * @param string $column
     * @param bool   $returnFirst
     * @return array|null
     */
    public static function getBy($match, $column = 'id', $returnFirst = false) {
        $results = [];
        foreach (static::getDataset() as $item) {
            if (isset($item[$column]) && $item[$column] === $match) {
                if ($returnFirst) {
                    return $item;
                }
                $results[] = $item;
            }
        }
        return $returnFirst ? null : $results;
    }

    // Each class must implement this
    abstract protected static function getDataset();
}
