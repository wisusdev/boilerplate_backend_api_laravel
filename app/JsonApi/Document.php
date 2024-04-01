<?php

namespace App\JsonApi;

use Illuminate\Support\Collection;

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
        foreach ($relationships as $key => $value) {
            $this->items['data']['relationships'][$value]['links'] = [
                'self' => route("api.v1.{$this->items['data']['type']}.relationships.{$value}", $this->items['data']['id']),
                'related' => route("api.v1.{$this->items['data']['type']}.{$value}", $this->items['data']['id']),
            ];
        }
        return $this;
    }
}
