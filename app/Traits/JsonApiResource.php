<?php

namespace App\Traits;

use App\JsonApi\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Route;

trait JsonApiResource
{
    abstract public function toJsonApi(): array;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $showRoute = 'api.v1.'.$this->resource->getResourceType().'.show';
        $links = [];

        if (Route::has($showRoute)) {
            $links['self'] = route($showRoute, $this->resource);
        }

        if ($request->filled('include')) {
            $this->with['included'] = [];
            foreach ($this->getIncludes() as $resource) {
                if ($resource->resource instanceof MissingValue) {
                    continue;
                }
                $this->with['included'][] = $resource->toArray($request);
            }
        }

        return Document::type($this->resource->getResourceType())
            ->id($this->resource->getRouteKey())
            ->attributes($this->filterAttributes($this->toJsonApi()))
            ->relationshipLinks($this->getRelationshipLinks())
            ->links($links)->get('data');
    }

    public function getIncludes(): array
    {
        return [];
    }

    public function getRelationshipLinks(): array
    {
        return [];
    }

    public function withResponse($request, $response)
    {
        $showRoute = 'api.v1.'.$this->getResourceType().'.show';

        // Location solo al crear (201), como pide JSON:API. En un 200 PHP-FPM lo
        // convierte en un 302 hacia la misma URL y el navegador entra en bucle.
        if ($response->getStatusCode() === 201 && Route::has($showRoute)) {
            $response->header(
                'Location',
                route($showRoute, $this->resource)
            );
        }
    }

    public function filterAttributes(array $attributes): array
    {
        return array_filter($attributes, function ($value) {
            if (request()->isNotFilled('fields')) {
                return true;
            }

            $fields = explode(',', request('fields.'.$this->getResourceType()));

            if ($value === $this->getRouteKey()) {
                return in_array($this->getRouteKeyName(), $fields);
            }

            return $value;
        });
    }

    public static function collection($resources): AnonymousResourceCollection
    {
        $collection = parent::collection($resources);

        if (request()->filled('include')) {
            $included = [];
            foreach ($resources as $resource) {
                foreach ($resource->getIncludes() as $include) {
                    if ($include->resource instanceof MissingValue) {
                        continue;
                    }
                    $included[] = $include;
                }
            }
            $collection->additional(['included' => $included]);
        }

        // `Resource::collection()` a veces envuelve una colección normal, no
        // paginada (p. ej. el alta múltiple de la galería devuelve varios
        // recién creados de golpe): `path()` solo existe en los paginadores, y
        // llamarlo sin comprobar tumbaba esos endpoints con un 500.
        if (method_exists($resources, 'path')) {
            $collection->with['links'] = [
                'self' => $resources->path(),
            ];
        }

        return $collection;
    }

    public static function identifier($resource): array
    {
        return Document::type($resource->getResourceType())->id($resource->getRouteKey())->toArray();
    }
}
