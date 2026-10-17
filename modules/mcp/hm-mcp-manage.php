<?php

/**
 * Folder, tag and contact management for the MCP server and REST API
 * @package modules
 * @subpackage mcp
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * Create, rename and delete folders, tag messages and save contacts. Tags and
 * contacts live in the Cypht settings of the user, which are saved right away.
 * @subpackage mcp/lib
 */
trait Hm_MCP_Manage {

    /* longest folder name, in characters */
    protected static $max_folder_name = 200;

    /* longest tag name, in characters */
    protected static $max_tag_name = 100;

    /* group of new contacts, the Cypht default */
    protected static $contact_group = 'Personal Addresses';

    /* ------------------------------------------------------------ folders */

    public function create_folder($args) {
        $ctx = $this->context();
        $account = $ctx->account($args['account_id']);
        $mailbox = $this->folder_mailbox($account['id']);
        $folders = Hm_MCP_Imap::folder_list($mailbox);
        $parent = '';
        if (($args['parent'] ?? '') !== '') {
            $parent = $ctx->resolve_folder($account['id'], $args['parent']);
            if (!isset($folders[$parent])) {
                throw new Hm_MCP_Error('not_found', 'The parent folder does not exist. Use a folder from list_folders.');
            }
        }
        $delim = Hm_MCP_Imap::delimiter($mailbox, $folders, $parent);
        $name = self::folder_segment($args['name'], $delim);
        $folder = $this->child_folder($mailbox, $parent, $name, $delim);
        if (self::folder_taken($folders, $folder)) {
            throw new Hm_MCP_Error('conflict', 'A folder with this name already exists.');
        }
        $connection = Hm_MCP_Imap::connection($mailbox);
        $created = Hm_MCP_Imap::quietly(function () use ($connection, $folder) { return $connection->create_mailbox($folder); });
        Hm_MCP_Imap::forget_folders($mailbox);
        if (!$created) {
            throw new Hm_MCP_Error('upstream_error', 'The mail server did not create the folder.', ['account_id' => $account['id']]);
        }
        $ctx->forget_special_folders($account['id']);
        $this->audit = ['account_id' => $account['id'], 'folder' => $folder];
        return $this->ok(sprintf('Folder "%s" created.', $folder),
            ['account_id' => $account['id'], 'folder' => $folder, 'name' => $name, 'parent' => $parent]);
    }

    public function rename_folder($args) {
        $ctx = $this->context();
        $account = $ctx->account($args['account_id']);
        $mailbox = $this->folder_mailbox($account['id']);
        $folders = Hm_MCP_Imap::folder_list($mailbox);
        $folder = $ctx->resolve_folder($account['id'], $args['folder']);
        list($delim, $inside) = $this->changeable_folder($account['id'], $mailbox, $folders, $folder);
        $position = $delim === false ? false : strrpos($folder, $delim);
        $parent = $position === false ? '' : substr($folder, 0, $position);
        if (array_key_exists('parent', $args)) {
            $parent = (string) $args['parent'] === '' ? '' : $ctx->resolve_folder($account['id'], $args['parent']);
            if ($parent !== '' && !isset($folders[$parent])) {
                throw new Hm_MCP_Error('not_found', 'The parent folder does not exist. Use a folder from list_folders.');
            }
            if ($parent === $folder || in_array($parent, $inside, true)) {
                throw new Hm_MCP_Error('invalid_argument', 'A folder cannot be moved into itself.');
            }
        }
        $name = self::folder_segment($args['name'], $delim);
        $target = $this->child_folder($mailbox, $parent, $name, $delim);
        $data = ['account_id' => $account['id'], 'folder' => $target, 'previous_folder' => $folder, 'name' => $name, 'parent' => $parent];
        if ($target === $folder) {
            return $this->ok('The folder already has this name.', $data);
        }
        if (self::folder_taken($folders, $target)) {
            throw new Hm_MCP_Error('conflict', 'A folder with this name already exists.');
        }
        $connection = Hm_MCP_Imap::connection($mailbox);
        $renamed = Hm_MCP_Imap::quietly(function () use ($connection, $folder, $target) { return $connection->rename_mailbox($folder, $target); });
        Hm_MCP_Imap::forget_folders($mailbox);
        if (!$renamed) {
            throw new Hm_MCP_Error('upstream_error', 'The mail server did not rename the folder.', ['account_id' => $account['id']]);
        }
        $ctx->forget_special_folders($account['id']);
        $this->audit = ['account_id' => $account['id'], 'folder' => $folder, 'destination' => $target];
        $this->update_tags(function ($tags) use ($account, $folder, $target, $delim) {
            return Hm_MCP_Tag_Refs::rename_folder($tags, $account['id'], $folder, $target, $delim);
        });
        return $this->ok(sprintf('Folder "%s" renamed to "%s". The messages in it have new ids: list the folder again to get them.', $folder, $target), $data);
    }

    public function delete_folder($args) {
        $ctx = $this->context();
        $account = $ctx->account($args['account_id']);
        $mailbox = $this->folder_mailbox($account['id']);
        $folders = Hm_MCP_Imap::folder_list($mailbox);
        $folder = $ctx->resolve_folder($account['id'], $args['folder']);
        list($delim, $inside) = $this->changeable_folder($account['id'], $mailbox, $folders, $folder);
        if ($inside || $folders[$folder]['has_kids']) {
            throw new Hm_MCP_Error('invalid_argument', 'This folder has folders inside it. Delete or move them first.');
        }
        $messages = 0;
        if (!$folders[$folder]['noselect']) {
            $status = Hm_MCP_Imap::quietly(function () use ($mailbox, $folder) { return $mailbox->get_folder_status($folder, false); });
            $messages = is_array($status) && isset($status['messages']) ? (int) $status['messages'] : null;
        }
        if ($messages !== 0 && !$this->principal->can('delete_permanent')) {
            throw new Hm_MCP_Error('permission_denied', $messages === null
                ? 'The messages in this folder could not be counted. Deleting it can delete messages for good, which needs the "Delete messages permanently" permission.'
                : sprintf('This folder has %d message(s). Deleting it deletes them for good, which needs the "Delete messages permanently" permission. Move the messages to another folder first to keep them.', $messages));
        }
        $connection = Hm_MCP_Imap::connection($mailbox);
        $deleted = Hm_MCP_Imap::quietly(function () use ($connection, $folder) { return $connection->delete_mailbox($folder); });
        Hm_MCP_Imap::forget_folders($mailbox);
        if (!$deleted) {
            throw new Hm_MCP_Error('upstream_error', 'The mail server did not delete the folder.', ['account_id' => $account['id']]);
        }
        $ctx->forget_special_folders($account['id']);
        $this->audit = ['account_id' => $account['id'], 'folder' => $folder, 'messages' => $messages];
        $this->update_tags(function ($tags) use ($account, $folder) {
            return Hm_MCP_Tag_Refs::drop_folder($tags, $account['id'], $folder);
        });
        $message = $messages ? sprintf('Folder "%s" deleted with its %d message(s).', $folder, $messages) : sprintf('Folder "%s" deleted.', $folder);
        return $this->ok($message, ['account_id' => $account['id'], 'folder' => $folder, 'deleted' => true, 'messages' => $messages]);
    }

    /**
     * Mailbox of an account, opened for writing, for folder changes
     * @param string $account_id account id
     * @return object Hm_Mailbox
     * @throws Hm_MCP_Error
     */
    protected function folder_mailbox($account_id) {
        $mailbox = $this->context()->mailbox($account_id, false);
        if (!$mailbox->is_imap() || !Hm_MCP_Imap::connection($mailbox)) {
            throw new Hm_MCP_Error('not_supported', 'Folders can only be changed here for IMAP accounts.');
        }
        return $mailbox;
    }

    /**
     * Full name of a new folder
     * @return string
     * @throws Hm_MCP_Error
     */
    protected function child_folder($mailbox, $parent, $name, $delim) {
        if ($parent !== '') {
            if ($delim === false) {
                throw new Hm_MCP_Error('not_supported', 'This mail server does not support folders inside folders.');
            }
            return $parent.$delim.$name;
        }
        /* some servers keep personal folders below a prefix such as INBOX. */
        $prefix = Hm_MCP_Imap::personal_namespace($mailbox)['prefix'];
        return $prefix !== '' && strpos($name, $prefix) !== 0 ? $prefix.$name : $name;
    }

    /**
     * Check a folder name given by the client
     * @param mixed $value name without the parent path
     * @param string|false $delim hierarchy delimiter
     * @return string
     * @throws Hm_MCP_Error
     */
    protected static function folder_segment($value, $delim) {
        $name = trim((string) $value);
        if ($name === '' || !mb_check_encoding($name, 'UTF-8')) {
            throw new Hm_MCP_Error('invalid_argument', 'The folder name is empty or not valid text.');
        }
        if (mb_strlen($name) > self::$max_folder_name) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('Folder names can have at most %d characters.', self::$max_folder_name));
        }
        if (preg_match('/[\x00-\x1F\x7F*%"\\\\]/', $name) || in_array($name, ['.', '..'], true)) {
            throw new Hm_MCP_Error('invalid_argument', 'Folder names cannot contain control characters, *, %, " or \\.');
        }
        if ($delim !== false && strpos($name, $delim) !== false) {
            throw new Hm_MCP_Error('invalid_argument', sprintf('Folder names cannot contain "%s" on this server. Use parent to put a folder inside another one.', $delim));
        }
        return $name;
    }

    /**
     * @param array $folders value of Hm_MCP_Imap::folder_list()
     * @param string $name folder name
     * @return bool a folder with this name exists, INBOX in any case
     */
    protected static function folder_taken($folders, $name) {
        if (strcasecmp($name, 'INBOX') === 0 || isset($folders[$name])) {
            return true;
        }
        return false;
    }

    /**
     * Folders that keep their name: the inbox, special folders and the folders Cypht uses
     * @param string $account_id account id
     * @param object $mailbox mailbox
     * @return array folder => role
     */
    protected function kept_folders($account_id, $mailbox) {
        $res = ['INBOX' => 'inbox', Hm_MCP_Imap::SNOOZED_FOLDER => 'snoozed', Hm_MCP_Imap::SCHEDULED_FOLDER => 'scheduled'];
        foreach ($this->context()->special_folders($account_id, $mailbox) as $role => $folder) {
            if ((string) $folder !== '' && !isset($res[(string) $folder])) {
                $res[(string) $folder] = $role;
            }
        }
        return $res;
    }

    /**
     * Check that a folder can be renamed or deleted
     * @return array [hierarchy delimiter, folders inside it]
     * @throws Hm_MCP_Error
     */
    protected function changeable_folder($account_id, $mailbox, $folders, $folder) {
        if (!isset($folders[$folder])) {
            throw new Hm_MCP_Error('not_found', 'Folder not found. Use a folder from list_folders.');
        }
        $delim = Hm_MCP_Imap::delimiter($mailbox, $folders, $folder);
        $inside = Hm_MCP_Imap::descendants($folders, $folder, $delim);
        foreach ($this->kept_folders($account_id, $mailbox) as $kept => $role) {
            if ($kept === $folder || ($role === 'inbox' && strcasecmp($folder, 'INBOX') === 0)) {
                throw new Hm_MCP_Error('invalid_argument', sprintf('This is the %s folder, which mail programs and Cypht rely on. It cannot be renamed or deleted.', $role));
            }
            if (in_array((string) $kept, $inside, true)) {
                throw new Hm_MCP_Error('invalid_argument', sprintf('This folder contains the %s folder, which mail programs and Cypht rely on. It cannot be renamed or deleted.', $role));
            }
        }
        return [$delim, $inside];
    }

    /**
     * Apply a change to the Cypht tags, when the tags module set is on and the change
     * touches them
     * @param callable $change function(array $tags): [tags, changed]
     * @return void
     */
    protected function update_tags($change) {
        $ctx = $this->context();
        if (!$ctx->module_is_supported('tags') || !$change($ctx->user_config->get('tags', []))[1]) {
            return;
        }
        try {
            $ctx->update_user_settings(function ($config) use ($change) {
                list($tags, $changed) = $change($config->get('tags', []));
                if (!$changed) {
                    return [];
                }
                $config->set('tags', $tags);
                return ['tags'];
            });
            $this->audit['tags_updated'] = true;
        } catch (Exception $e) {
            Hm_Debug::add('MCP could not update tags: '.$e->getMessage(), 'warning');
            $this->audit['tags_updated'] = false;
        }
    }

    /* --------------------------------------------------------------- tags */

    public function tag_messages($args) {
        $ctx = $this->context();
        if (!$ctx->module_is_supported('tags')) {
            throw new Hm_MCP_Error('not_supported', 'Tags are not enabled on this server.');
        }
        $add = self::tag_refs($args['add'] ?? []);
        $remove = self::tag_refs($args['remove'] ?? []);
        if (!$add && !$remove) {
            throw new Hm_MCP_Error('invalid_argument', 'Give tags to add, tags to remove, or both.');
        }
        $found = [];
        $results = $this->each_folder($args['message_ids'], function ($mailbox, $account, $folder, $uids) use (&$found) {
            $existing = Hm_MCP_Imap::existing($mailbox, $folder, array_keys($uids));
            $res = [];
            foreach ($uids as $uid => $id) {
                if (isset($existing[(string) $uid])) {
                    $found[$id] = [$account['id'], $folder, (string) $uid];
                    $res[$id] = self::result($id, 'unchanged', ['folder' => $folder]);
                } else {
                    $res[$id] = self::not_found_result($id);
                }
            }
            return $res;
        }, true);
        $changed = [];
        $involved = [];
        if ($found) {
            $ctx->update_user_settings(function ($config) use ($add, $remove, $found, &$changed, &$involved) {
                $tags = $config->get('tags', []);
                $tags = is_array($tags) ? $tags : [];
                list($add_ids, $created) = self::resolve_tags($tags, $add, true);
                list($remove_ids) = self::resolve_tags($tags, $remove, false);
                if (array_intersect($add_ids, $remove_ids)) {
                    throw new Hm_MCP_Error('invalid_argument', 'A tag cannot be added and removed at the same time.');
                }
                foreach ($found as $id => list($account_id, $folder, $uid)) {
                    foreach ($add_ids as $tag_id) {
                        $list = self::tagged_uids($tags, $tag_id, $account_id, $folder);
                        if (!in_array($uid, $list, true)) {
                            $list[] = $uid;
                            $tags[$tag_id]['server'][$account_id][$folder] = $list;
                            $changed[$id] = true;
                        }
                    }
                    foreach ($remove_ids as $tag_id) {
                        $list = self::tagged_uids($tags, $tag_id, $account_id, $folder);
                        if (in_array($uid, $list, true)) {
                            $tags[$tag_id]['server'][$account_id][$folder] = array_values(array_diff($list, [$uid]));
                            $changed[$id] = true;
                        }
                    }
                }
                foreach (array_merge($add_ids, $remove_ids) as $tag_id) {
                    $involved[] = ['id' => (string) $tag_id, 'name' => Hm_MCP_Format::text($tags[$tag_id]['name'] ?? ''),
                        'created' => in_array($tag_id, $created, true)];
                }
                if (!$changed && !$created) {
                    return [];
                }
                $config->set('tags', $tags);
                return ['tags'];
            });
        }
        foreach ($results as $index => $result) {
            if (isset($changed[$result['message_id']])) {
                $results[$index]['status'] = 'ok';
            }
        }
        $result = $this->batch_result($results, 'updated');
        $result['data']['tags'] = $involved;
        $this->audit['tags'] = count($involved);
        $this->audit['tags_created'] = count(array_filter(array_column($involved, 'created')));
        return $result;
    }

    /**
     * @param mixed $values tag names or ids
     * @return array cleaned values without duplicates
     * @throws Hm_MCP_Error
     */
    protected static function tag_refs($values) {
        $res = [];
        foreach ((array) $values as $value) {
            $value = Hm_MCP_Mime::header_text($value);
            if ($value === '') {
                throw new Hm_MCP_Error('invalid_argument', 'Tag names cannot be empty.');
            }
            if (mb_strlen($value) > self::$max_tag_name) {
                throw new Hm_MCP_Error('invalid_argument', sprintf('Tag names can have at most %d characters.', self::$max_tag_name));
            }
            $res[mb_strtolower($value)] = $value;
        }
        return array_values($res);
    }

    /**
     * Find tags by id or by name, creating missing ones when asked
     * @param array $tags tags, changed when a tag is created
     * @param array $refs tag ids or names
     * @param bool $create create tags that do not exist
     * @return array [tag ids, created tag ids]
     * @throws Hm_MCP_Error
     */
    protected static function resolve_tags(&$tags, $refs, $create) {
        $ids = [];
        $created = [];
        foreach ($refs as $ref) {
            if (isset($tags[$ref]) && is_array($tags[$ref])) {
                $ids[] = (string) $ref;
                continue;
            }
            $matches = [];
            foreach ($tags as $id => $tag) {
                if (is_array($tag) && mb_strtolower(trim((string) ($tag['name'] ?? ''))) === mb_strtolower($ref)) {
                    $matches[] = (string) $id;
                }
            }
            if (count($matches) > 1) {
                throw new Hm_MCP_Error('invalid_argument', sprintf('Several tags are named "%s". Use the id of one from list_tags: %s.', $ref, implode(', ', $matches)));
            }
            if ($matches) {
                $ids[] = $matches[0];
            } elseif ($create) {
                $id = self::new_id();
                $tags[$id] = ['id' => $id, 'name' => $ref, 'parent' => null,
                    'color' => class_exists('Hm_Tags') ? Hm_Tags::defaultColor() : '#5f6368', 'server' => []];
                $ids[] = $id;
                $created[] = $id;
            } else {
                throw new Hm_MCP_Error('not_found', sprintf('There is no tag "%s". Use list_tags to see the tags.', $ref));
            }
        }
        return [array_values(array_unique($ids)), $created];
    }

    /**
     * Message uids a tag has in a folder, normalizing the stored value
     * @return array
     */
    protected static function tagged_uids(&$tags, $tag_id, $account_id, $folder) {
        if (!is_array($tags[$tag_id]['server'] ?? null)) {
            $tags[$tag_id]['server'] = [];
        }
        if (!is_array($tags[$tag_id]['server'][$account_id] ?? null)) {
            $tags[$tag_id]['server'][$account_id] = [];
        }
        $list = $tags[$tag_id]['server'][$account_id][$folder] ?? [];
        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    /**
     * @return string new id in the format Cypht uses for tags and contacts
     */
    protected static function new_id() {
        return substr(bin2hex(random_bytes(8)), 0, 13);
    }

    /* ----------------------------------------------------------- contacts */

    public function save_contact($args) {
        $ctx = $this->context();
        if (!$ctx->module_is_supported('local_contacts')) {
            throw new Hm_MCP_Error('not_supported', 'Local contacts are not enabled on this server.');
        }
        $id = trim((string) ($args['contact_id'] ?? ''));
        $email = null;
        if (array_key_exists('email', $args)) {
            $email = trim((string) $args['email']);
            if (!Hm_MCP_Mime::valid_email($email)) {
                throw new Hm_MCP_Error('invalid_argument', 'The email address is not valid.');
            }
        } elseif ($id === '') {
            throw new Hm_MCP_Error('invalid_argument', 'Give the email address of the contact, or the contact_id of the contact to change.');
        }
        $fields = [];
        if ($email !== null) {
            $fields['email_address'] = $email;
        }
        if (array_key_exists('name', $args)) {
            $fields['display_name'] = mb_substr(Hm_MCP_Mime::header_text($args['name']), 0, 200);
        }
        if (array_key_exists('phone', $args)) {
            $phone = Hm_MCP_Mime::header_text($args['phone']);
            if ($phone !== '' && !preg_match('/^[0-9+()\-.\s\/#*,;xX]{1,50}$/', $phone)) {
                throw new Hm_MCP_Error('invalid_argument', 'The phone number can only have digits, spaces and + ( ) - . / # * , ; x.');
            }
            $fields['phone_number'] = $phone;
        }
        if (array_key_exists('group', $args)) {
            $group = mb_substr(Hm_MCP_Mime::header_text($args['group']), 0, 100);
            $fields['group'] = $group === '' ? self::$contact_group : $group;
        }
        $saved = null;
        $created = false;
        $ctx->update_user_settings(function ($config) use ($id, $email, $fields, &$saved, &$created) {
            $contacts = $config->get('contacts', []);
            $contacts = is_array($contacts) ? $contacts : [];
            $key = null;
            foreach ($contacts as $index => $contact) {
                if (!is_array($contact)) {
                    continue;
                }
                if ($id !== '' ? (string) ($contact['id'] ?? $index) === $id : strcasecmp((string) ($contact['email_address'] ?? ''), $email) === 0) {
                    $key = $index;
                    break;
                }
            }
            if ($key === null && $id !== '') {
                throw new Hm_MCP_Error('not_found', 'Contact not found. Use an id from search_contacts.');
            }
            if ($key === null) {
                $key = self::new_id();
                $contacts[$key] = array_merge(['id' => $key, 'source' => 'local', 'type' => 'local', 'email_address' => $email,
                    'display_name' => '', 'group' => self::$contact_group], $fields);
                $created = true;
            } else {
                $contacts[$key] = array_merge($contacts[$key], $fields);
            }
            $config->set('contacts', $contacts);
            $saved = $contacts[$key] + ['id' => (string) $key];
            return ['contacts'];
        });
        $contact = [
            'id' => (string) ($saved['id'] ?? ''),
            'name' => Hm_MCP_Format::text($saved['display_name'] ?? ''),
            'email' => Hm_MCP_Format::text($saved['email_address'] ?? ''),
            'phone' => Hm_MCP_Format::text($saved['phone_number'] ?? ''),
            'group' => Hm_MCP_Format::text($saved['group'] ?? ''),
        ];
        $this->audit = ['contacts' => 1, 'new_contact' => $created];
        return $this->ok(sprintf('Contact %s %s.', $contact['email'], $created ? 'added' : 'updated'), ['contact' => $contact, 'created' => $created]);
    }
}
