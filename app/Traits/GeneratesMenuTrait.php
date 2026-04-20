<?php

namespace App\Traits;

trait GeneratesMenuTrait
{
    public function generateMenu($key, $user)
    {
        $new_items = [];

        $menu = \Menu::get($key);
        $items = $menu ? $menu->items->toArray() : [];

        foreach ($items as $data) {
            if ($user->checkAccess($data)) {
                $item = [
                    'title' => $data->title,
                    'link' => $data->link->path['url'],
                    'icon' => $data->data['icon'],
                    'name' => $data->data['name'],
                    'group' => $data->data['group'],
                ];
                if (!empty($data->data['custom_icon'])) {
                    $item['custom_icon'] = $data->data['custom_icon'];
                }
                if (!empty($data->data['custom_icon_active'])) {
                    $item['custom_icon_active'] = $data->data['custom_icon_active'];
                }
                if (!empty($data->data['external'])) {
                    $item['external'] = true;
                }
                if (!empty($data->data['action'])) {
                    $item['action'] = $data->data['action'];
                }
                $new_items[] = $item;
            }
        }

        return $new_items;
    }
}
