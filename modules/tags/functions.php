<?php

if (!defined('DEBUG_MODE')) { die(); }

if (!hm_exists('add_label')) {
    function add_label($name, $parent = null) {
        $tag = array(
            'name' => $name,
            'parent' => $parent,
        );

        Hm_Tags::add($tag);
    }
}

if (!hm_exists('generate_tree_view')) {
    function generate_tree_view($folders, $parentId = null) {
        static $counter = 0;
        $ulClass = $parentId !== null ? 'list-group pt-2' : 'list-group';
        $html = '<ul class="' . $ulClass . '">';
        foreach ($folders as $folderId => $folder) {
            $counter++;
            $hasChildren = isset($folder['children']) && !empty($folder['children']);
            $toggleIcon = $hasChildren ? '<i class="bi bi-caret-right"></i>' : '<i class="bi bi-folder"></i>';

            $html .= '<li class="list-group-item justify-content-between align-items-center">';
            $html .= '<span>';
            $html .= '<span data-bs-toggle="collapse" data-bs-target="#collapse-' . $counter . '" aria-expanded="false" aria-controls="collapse-' . $counter . '" role="button">';
            $html .= $toggleIcon . ' ';
            $html .= htmlspecialchars($folder['name']);
            if($hasChildren) {
                $html .= '<span class="badge rounded-pill text-bg-primary ms-2 px-1">'.count($folder['children']).'</span>';
            }
            $html .= '</span>';
            $html .= '</span>';
            
            $html .= '<a href="#" class="float-end"><i class="bi bi-pencil-square"></i></a>';
            
            if ($hasChildren) {
                $html .= '<div class="collapse" id="collapse-' . $counter . '">';
                $html .= generate_tree_view($folder['children'], $counter);
                $html .= '</div>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
