<?php

namespace App\JsonApi;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class Document extends Collection
{

    static public function type(string $type): self
    {
        return new self([
            'data' => [
                'type' => $type,
            ]
        ]);
    }

    public function id(string $id): self
    {
        if ($id) {
            $this->items['data']['id'] = (string)$id;
        }
        return $this;
    }

    public function attributes(array $attributes): self
    {
        unset($attributes['_relationships']);

        $this->items['data']['attributes'] = $attributes;
        return $this;
    }

    public function links(array $links): self
    {
        $this->items['data']['links'] = $links;
        return $this;
    }

    public function relationshipData(array $relationships): self
    {
        foreach ($relationships as $key => $value) {
            $this->items['data']['relationships'][$key]['data'] = [
                'type' => $value->getResourceType(),
                'id' => $value->getRouteKey(),
            ];
        }
        return $this;
    }

    public function relationshipLinks(array $relationships): self
    {
        foreach ($relationships as $rel) {
            $links = [];
            
            $selfRoute = "{$this->items['data']['type']}.relationships.{$rel['name']}";
            if (Route::has($selfRoute)) {
                $links['self'] = route($selfRoute, $this->items['data']['id']);
            }
            
            if (Route::has($rel['route'])) {
                $links['related'] = route($rel['route'], $rel['params']);
            }
            
            if (!empty($links)) {
                $this->items['data']['relationships'][$rel['name']]['links'] = $links;
            }
        }
        
        return $this;
    }
}