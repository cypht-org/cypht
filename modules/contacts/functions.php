<?php

/**
 * Get the initials from a name
 * It gets deleted if already configured
 *
 * @param string $name name to get initials from
 * @return string initials
 */
if (!function_exists('get_initials')) {
function get_initials(string $name): string {
    $normalized = preg_replace('/[.@_\-]+/', ' ', trim($name));
    $initials = implode('', array_map(
        fn($part) => mb_substr($part, 0, 1),
        array_filter(explode(' ', $normalized))
    ));
    return mb_strtoupper(mb_substr($initials, 0, 2));
}}

/**
 * Get a color for the avatar based on the id
 * It gets deleted if already configured
 *
 * @param string $id identifier to get a color from
 * @return string CSS gradient color
 */
if (!hm_exists('get_avatar_color')) {
function get_avatar_color(string $id): string {
    $colors = [
        'linear-gradient(135deg, #3b82f6 0%, #1e40af 100%)',
        'linear-gradient(135deg, #328E92 0%, #1e5f5f 100%)',
        'linear-gradient(135deg, #10b981 0%, #065f46 100%)',
        'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'linear-gradient(135deg, #8b5cf6 0%, #5b21b6 100%)',
        'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)',
        'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
        'linear-gradient(135deg, #84cc16 0%, #65a30d 100%)',
    ];
    $hash = is_numeric($id) ? intval($id) : crc32($id);
    return $colors[$hash % count($colors)];
}}