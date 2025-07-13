<?php

trait Hm_Repository {

    protected static $name;
    protected static $user_config;
    protected static $session;
    protected static $entities;

    protected static function initRepo($name, $user_config, $session, &$entities, ? callable $init = null) {
        self::$name = $name;
        self::$user_config = $user_config;
        self::$session = $session;
        self::$entities = &$entities;
        self::migrateFromIntegerIds();
        $initial = self::$user_config->get(self::$name, []);
        if ($init) {
            $init($initial);
        } else {
            foreach ($initial as $key => $entity) {
                if (! array_key_exists('id', $entity)) {
                    $entity['id'] = $key;
                }
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
            if (is_array($entity)) {
                self::$entities[$id] = array_merge(self::$entities[$id], $entity);
            } else {
                self::$entities[$id] = $entity;
            }
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
        if (is_array(self::$entities) && array_key_exists($id, self::$entities)) {
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

    protected static function migrateFromIntegerIds() {
        $config = self::$user_config->dump();
        $replacements = [
            'imap' => [],
            'smtp' => [],
        ];
        $changed = false;
        if (! empty($config['imap_servers'])) {
            $replacements['imap'] = self::replaceIntegerIds($config['imap_servers']);
            $changed = $changed || ! empty($replacements['imap']);
        }
        if (! empty($config['smtp_servers'])) {
            $replacements['smtp'] = self::replaceIntegerIds($config['smtp_servers']);
            $changed = $changed || ! empty($replacements['smtp']);
        }
        if (! empty($config['feeds'])) {
            $result = self::replaceIntegerIds($config['feeds']);
            $changed = $changed || ! empty($result);
        }
        if (! empty($config['profiles'])) {
            $result = self::replaceIntegerIds($config['profiles']);
            $changed = $changed || ! empty($result);
            foreach ($config['profiles'] as $id => $profile) {
                if (isset($profile['smtp_id']) && is_numeric($profile['smtp_id']) && isset($replacements['smtp'][$profile['smtp_id']])) {
                    $config['profiles'][$id]['smtp_id'] = $replacements['smtp'][$profile['smtp_id']];
                    $changed = true;
                }
            }
        }
        if (! empty($config['special_imap_folders'])) {
            foreach ($config['special_imap_folders'] as $id => $special) {
                if (is_numeric($id)) {
                    if (isset($replacements['imap'][$id])) {
                        $config['special_imap_folders'][$replacements['imap'][$id]] = $special;
                    }
                    unset($config['special_imap_folders'][$id]);
                    $changed = true;
                }
            }
        }
        if (! empty($config['custom_imap_sources'])) {
            foreach ($config['custom_imap_sources'] as $id => $val) {
                if (preg_match('/^imap_(\d+)_([0-9a-z]+)$/', $id, $m)) {
                    $old_id = $m[1];
                    if (isset($replacements['imap'][$old_id])) {
                        $config['custom_imap_sources']['imap_' . $replacements['imap'][$old_id] . '_' . $m[2]] = $val;
                    }
                    unset($config['custom_imap_sources'][$id]);
                    $changed = true;
                }
            }
        }
        if ($changed) {
            self::$user_config->reload($config, self::$session->get('username'));
            self::$session->set('user_data', self::$user_config->dump());
        }
    }

    protected static function replaceIntegerIds(&$list) {
        $replacements = [];
        foreach ($list as $id => $server) {
            if (is_numeric($id)) {
                $new_id = self::generateId();
                $server['id'] = $new_id;
                $list[$new_id] = $server;
                unset($list[$id]);
                $replacements[$id] = $new_id;
            }
        }
        return $replacements;
    }
}
