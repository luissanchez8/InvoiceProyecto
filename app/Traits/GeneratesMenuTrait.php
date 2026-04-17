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
                $new_items[] = $item;
            }
        }

        return $new_items;
    }
}
