<?php

/**
 * Tags modules
 * @package modules
 * @subpackage tags
 */

if (!defined('DEBUG_MODE')) { die(); }

require_once APP_PATH . 'modules/tags/hm-tags.php';

/**
 * @subpackage tags/handler
 */
class Hm_Handler_mod_env extends Hm_Handler_Module {
    public function process() {
        $this->out('mod_support', array_filter(array(
            $this->module_is_supported('imap') ? 'imap' : false,
            $this->module_is_supported('feeds') ? 'feeds' : false,
            $this->module_is_supported('github') ? 'github' : false,
            $this->module_is_supported('wordpress') ? 'wordpress' : false
        )));
    }
}

/**
 * @subpackage tags/handler
 */
class Hm_Handler_tag_edit_data extends Hm_Handler_Module {
    public function process() {
        $id = false;
        if (array_key_exists('tag_id', $this->request->get)) {
            $id = $this->request->get['tag_id'];
        }
        $folders = $this->get('tag_folders');

        foreach ($folders as $folder) {
            if ($folder['id'] == $id) {
                $folder = $folder;
            }
        }

        if ($id !== false) {
            $this->out('edit_tag', $folder);
            $this->out('edit_tag_id', $id);
        }
        else {
            $this->out('new_tag_id', count($folders));
        }
    }
}
/**
 * @subpackage tags/handler
 */
class Hm_Handler_process_tag_update extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('tag_delete', $this->request->post)) {
            return;
        }
        list($success, $form) = $this->process_form(array('tag_name'));// 'tag_id', parent_tag
        return ['form' => $form];
        if (!$success) {
            return;
        }
        // var_dump($form);die();
        return ['form' => $form];
        $tag = array(
            'name' => html_entity_decode($form['tag_name'], ENT_QUOTES),
            'parent' => $form['parent'] ?? null
        );
        if (Hm_Tags::get($form['tag_id'])) {
            $tag['id'] = $form['tag_id'];
            Hm_Tags::edit($form['tag_id'], $tag);
        } else {
            Hm_Tags::add($tag);
        }
    }
}

/**
 * @subpackage profile/handler
 */
class Hm_Handler_process_tag_delete extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('tag_delete', 'tag_id'));
        if (!$success) {
            return;
        }

        if (($tag = Hm_Tags::get($form['tag_id']))) {
            Hm_Tags::del($form['tag_id']);
            Hm_Msgs::add('Tag Deleted');
        } else {
            Hm_Msgs::add('ERRTag ID not found');
            return;
        }
    }
}

/**
 * @subpackage tags/handler
 */
class Hm_Handler_tag_data extends Hm_Handler_Module {
    public function process() {
        Hm_Tags::init($this);
        $this->out('tag_folders', Hm_Tags::getAll());
    }
}

class Hm_Output_tags_heading extends Hm_Output_Module {
    /**
     */
    protected function output() {
        return '<div class="content_title">'.$this->trans('Tags').'</div>';
    }
}
class Hm_Output_tags_table extends Hm_Output_Module {
    /**
     */
    protected function output() {
        if ($this->format == 'HTML5') {
        $count = count($this->get('tags', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        $tag = $this->get('tag_profile');
        $id = $this->get('edit_tag_id');
        return '<div class="tags_table mt-3 col-lg-8 col-md-8 col-sm-12">
                    <div class="card m-3 mr-0">
                        <div class="card-body">
                            <div class="table-responsive-lg">
                                <table class="table table-striped">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">First</th>
                                            <th scope="col">Last</th>
                                            <th scope="col">Handle</th>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th scope="row">1</th>
                                            <td>Mark</td>
                                            <td>Otto</td>
                                            <td>@mdo</td>
                                            <th scope="row">
                                                <a href="" class="btn btn-primary btn-sm tags_action_btn"><i class="bi bi-pencil-square"></i></a>
                                                <a href="" class="btn btn-danger btn-sm tags_action_btn"><i class="bi bi-trash3-fill"></i></a>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row">2</th>
                                            <td>Jacob</td>
                                            <td>Thornton</td>
                                            <td>@fat</td>
                                            <th scope="row">
                                                <a href="" class="btn btn-primary btn-sm tags_action_btn"><i class="bi bi-pencil-square"></i></a>
                                                <a href="" class="btn btn-danger btn-sm tags_action_btn"><i class="bi bi-trash3-fill"></i></a>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row">3</th>
                                            <td>Larry</td>
                                            <td>the Bird</td>
                                            <td>@twitter</td>
                                            <th scope="row">
                                                <a href="" class="btn btn-primary btn-sm tags_action_btn"><i class="bi bi-pencil-square"></i></a>
                                                <a href="" class="btn btn-danger btn-sm tags_action_btn"><i class="bi bi-trash3-fill"></i></a>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row">3</th>
                                            <td>Larry</td>
                                            <td>the Bird</td>
                                            <td>@twitter</td>
                                            <th scope="row">
                                                <a href="" class="btn btn-primary btn-sm tags_action_btn"><i class="bi bi-pencil-square"></i></a>
                                                <a href="" class="btn btn-danger btn-sm tags_action_btn"><i class="bi bi-trash3-fill"></i></a>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row">3</th>
                                            <td>Larry</td>
                                            <td>the Bird</td>
                                            <td>@twitter</td>
                                            <th scope="row">
                                                <a href="" class="btn btn-primary btn-sm tags_action_btn"><i class="bi bi-pencil-square"></i></a>
                                                <a href="" class="btn btn-danger btn-sm tags_action_btn"><i class="bi bi-trash3-fill"></i></a>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row">3</th>
                                            <td>Larry</td>
                                            <td>the Bird</td>
                                            <td>@twitter</td>
                                            <th scope="row">
                                                <a href="" class="btn btn-primary btn-sm tags_action_btn"><i class="bi bi-pencil-square"></i></a>
                                                <a href="" class="btn btn-danger btn-sm tags_action_btn"><i class="bi bi-trash3-fill"></i></a>
                                            </th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>';
        }
    }
}

class Hm_Output_tags_form extends Hm_Output_Module {
    /**
     */
    protected function output() {
        if ($this->format == 'HTML5') {
        $count = count($this->get('tags', array()));
        $count = sprintf($this->trans('%d configured'), $count);
        $tag = $this->get('tag_profile');
        $id = $this->get('edit_tag_id');
        $parent_tag = $this->get('parent_tag');
        return '<div class="tags_table mt-3 col-lg-4 col-md-4 col-sm-12">
                    <div class="card m-4">
                        <div class="card-body">
                            <form class="add_tag me-0" method="POST" action="?page=tags">
                                <input type="hidden" name="hm_page_key" id="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />

                                <div class="subtitle mt-4">'.$this->trans('Add an tag/label').'</div>

                                <div class="form-floating mb-3">
                                    <input type="hidden" id="hm_ajax_hook" name="hm_ajax_hook" class="txt_fld form-control" value="ajax_process_tag_update">
                                    <input type="hidden" id="tag_id" name="tag_id" class="txt_fld form-control" value="'.$this->html_safe($id).'" placeholder="'.$this->trans('Tag id').'">
                                    <input type="hidden" id="parent_tag" name="parent_tag" class="txt_fld form-control" value="'.$this->html_safe($parent_tag).'" placeholder="'.$this->trans('Pare t Tag id').'">
                                    <input required type="text" id="tag_name" name="tag_name" class="txt_fld form-control" value="'.$this->html_safe($tag['name']).'" placeholder="'.$this->trans('Tag name').'">
                                    <label class="" for="tag_name">'.$this->trans('Tag name').'</label>
                                </div>

                                <input type="submit" class="btn btn-primary px-5" value="'.$this->trans('Add').'" name="submit_tag" />
                            </form>
                        </div>
                    </div>
                </div>';
        }
    }
}

/**
 * @subpackage tags/output
 */
class Hm_Output_tag_folders extends hm_output_module {
    protected function output() {
        $res = '';
        $folders = [
            'EvoluData',
            'Notes',
            'Personal',
        ];
        // $folders = $this->get('tag_folders', array());
        // var_dump($folders);die();
        if (is_array($folders) && !empty($folders)) {
            if(count($this->get('tags', array()))  > 1) {
                $res .= '<li class="menu_tags"><a class="unread_link" href="?page=message_list&amp;list_path=tags">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-rss-fill fs-5 me-2"></i>';
                }
                $res .= $this->trans('All');
                $res .= '</a> <span class="unread_tag_count"></span></li>';
            }
            foreach ($folders as $id => $folder) {
                $res .= '<li class="tags_'.$this->html_safe($id).'">'.
                    '<a data-id="tags_'.$this->html_safe($id).'" href="?page=message_list&list_path=tags_'.$this->html_safe($id).'">';
                if (!$this->get('hide_folder_icons')) {
                    $res .= '<i class="bi bi-folder-fill fs-5 me-2"></i>';
                }
                $res .= $this->html_safe($folder).'</a>';
                $res .= '<button class="btn btn-sm btn-tag-options" data-bs-toggle="dropdown" aria-expanded="false"> <i class="bi bi-three-dots"></i> </button>';
                $res .= '<ul class="dropdown-menu dropdown-menu-lg-end" aria-labelledby="dropdownMicroProcessorTrigger">';
                $res .= '<li><a href="?page=servers#tags_section" class="dropdown-item"> <i class="bi bi-pencil"></i> Edit </a></li>';
                $res .= '<li><a href="?page=servers#tags_section" class="dropdown-item"> <i class="bi bi-plus"></i> Add sublabel</a></li>';
                $res .= '<li><a href="?page=servers#tags_section" class="dropdown-item"> <i class="bi bi-trash"></i> Delete</a></li>';
                $res .= '</ul>';
                $res .= '</li>';
            }
        }
        $res .= '<li class="tags_add_new"><a href="?page=tags">';
        if (!$this->get('hide_folder_icons')) {
            $res .= '<i class="bi bi-plus-square fs-5 me-2"></i>';
        }
        $res .= $this->trans('Add label').'</a></li>';
        $this->append('folder_sources', array('tags_folders', $res));
    }
}

/**
 * @subpackage tags/output
 */
class Hm_Output_tag_bar extends hm_output_module {
    protected function output() {
        $headers = $this->get('msg_headers');
        if (is_string($headers)) {
            $this->out('msg_headers', $headers.'<i class="bi bi-tags-fill fs-4 tag_icon refresh_list"></i>');
        }
    }
}

// class Hm_Output_add_tag_dialog extends Hm_Output_Module {
//     protected function output() {
//         if ($this->format == 'HTML5') {
//             $count = count($this->get('tags', array()));
//             $count = sprintf($this->trans('%d configured'), $count);
//             $tag = $this->get('tag_profile');
//             $id = $this->get('edit_tag_id');

//             return '<div class="tag_server_setup">
//                         <div data-target=".tags_section" class="server_section border-bottom cursor-pointer px-1 py-3 pe-auto">
//                             <a href="#" class="pe-auto">
//                                 <i class="bi bi-tag-fill me-3"></i>
//                                 <b> '.$this->trans('Tags').'</b>
//                             </a>
//                             <div class="server_count">'.$count.'</div>
//                         </div>

//                         <div class="tags_section px-4 pt-3 me-0">
//                             <div class="row">
//                                 <div class="col-12 col-lg-4 mb-4">
//                                     <form class="add_tag me-0" method="POST" action="?page=servers">
//                                         <input type="hidden" name="hm_page_key" id="hm_page_key" value="'.$this->html_safe(Hm_Request_Key::generate()).'" />

//                                         <div class="subtitle mt-4">'.$this->trans('Add an tag/label').'</div>

//                                         <div class="form-floating mb-3">
//                                             <input type="hidden" id="tag_id" name="tag_id" class="txt_fld form-control" value="'.$this->html_safe($id).'" placeholder="'.$this->trans('Tag id').'">
//                                             <input required type="text" id="tag_name" name="tag_name" class="txt_fld form-control" value="'.$this->html_safe($tag['name']).'" placeholder="'.$this->trans('Tag name').'">
//                                             <label class="" for="tag_name">'.$this->trans('Tag name').'</label>
//                                         </div>

//                                         <input type="submit" class="btn btn-primary px-5" value="'.$this->trans('Add').'" name="submit_tag" />
//                                     </form>
//                                 </div>';
//         }
//     }
// }

class Hm_Output_display_configured_tags extends Hm_Output_Module {
    protected function output() {
        $res = '';
        if ($this->format == 'HTML5') {
            foreach ($this->get('feeds', array()) as $index => $vals) {
                $res .= '<div class="configured_server col-12 col-lg-4 mb-2"><div class="card card-body">';
                $res .= sprintf('<div class="server_title"><b>%s</b></div><div title="%s" class="server_subtitle">%s</div>',
                    $this->html_safe($vals['name']), $this->html_safe($vals['server']), $this->html_safe($vals['server']));
                $res .= '<form class="feed_connect d-flex gap-2" method="POST">';
                $res .= '<input type="hidden" name="feed_id" value="'.$this->html_safe($index).'" />';
                $res .= '<input type="submit" value="'.$this->trans('Test').'" class="test_feed_connect btn btn-primary btn-sm" />';
                $res .= '<input type="submit" value="'.$this->trans('Delete').'" class="feed_delete btn btn-outline-danger btn-sm" />';
                $res .= '<input type="hidden" value="ajax_feed_debug" name="hm_ajax_hook" />';
                $res .= '</form></div></div>';
            }
            $res .= '<br class="clear_float" /></div></div>';
        }
        return $res;
    }
}
