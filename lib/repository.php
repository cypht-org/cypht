<?php

trait Hm_Repository {

    protected static $name;
    protected static $user_config;
    protected static $session;
    protected static $entities;

    protected static function initRepo($name, $user_config, $session, &$entities, callable $init = null) {
        self::$name = $name;
        self::$user_config = $user_config;
        self::$session = $session;
        self::$entities = &$entities;
        $initial = self::$user_config->get(self::$name, []);
        if ($init) {
            $init($initial);
        } else {
            foreach ($initial as $entity) {
                self::add($entity, false);
            }
        }
    }

    protected static function generateId() {
        return uniqid();
    }

    public static function save() {
        self::$user_config->set(self::$name, self::$entities);
        self::$session->set('user_data', self::$user_config->dump());
    }

    public static function add($entity, $save = true) {
        if (is_array($entity)) {
            if (! array_key_exists('id', $entity)) {
                $entity['id'] = self::generateId();
            }
            $id = $entity['id'];
        } elseif (method_exists($entity, 'value')) {
            if (! $entity->value('id')) {
                $entity->update('id', self::generateId());
            }
            $id = $entity->value('id');
        } else {
            throw new Exception('Unrecognized entity found in the repository.');
        }
        self::$entities[$id] = $entity;
        if ($save) {
            self::save();
        }
        return $id;
    }

    public static function edit($id, $entity) {
        if (array_key_exists($id, self::$entities)) {
            self::$entities[$id] = $entity;
            self::save();
            return true;
        }
        return false;
    }

    public static function del($id) {
        if (array_key_exists($id, self::$entities)) {
            unset(self::$entities[$id]);
            self::save();
            return true;
        }
        return false;

    }

    public static function get($id) {
        if (array_key_exists($id, self::$entities)) {
            return self::$entities[$id];
        }
        return false;
    }

    public static function getAll() {
        return self::$entities;
    }

    public static function count() {
        return count(self::$entities);
    }
}
