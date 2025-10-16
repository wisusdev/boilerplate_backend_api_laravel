<?php

namespace App\Traits;

use App\JsonApi\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\MissingValue;

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
            ->links([
                'self' => route('api.v1.' . $this->resource->getResourceType() . '.show', $this->getRouteParameters())
            ])->get('data');
    }

	public function getIncludes(): array
	{
		return [];
	}

	public function getRelationshipLinks(): array
	{
		return [];
	}

	protected function getRouteParameters()
	{
		return $this->resource;
	}

	public function withResponse($request, $response): void
	{
		$response->header(
			'Location',
			route('api.v1.' . $this->resource->getResourceType() . '.show', $this->getRouteParameters())
		);
	}

	public function filterAttributes(array $attributes): array
	{
		return array_filter($attributes, function ($value) {
			if (request()->isNotFilled('fields')) {
				return true;
			}

			$fields = explode(',', request('fields.' . $this->getResourceType()));

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
			$uniqueIncludes = [];

			foreach ($resources as $resource) {
				foreach ($resource->getIncludes() as $include) {
					if ($include->resource instanceof MissingValue) {
						continue;
					}

					$key = $include->resource->getResourceType() . '-' . $include->resource->getRouteKey();

					if (!isset($uniqueIncludes[$key])) {
						$uniqueIncludes[$key] = $include;
						$included[] = $include;
					}
				}
			}

			$collection->additional(['included' => $included]);
		}

		$collection->with['links'] = [
			'self' => $resources->path()
		];

		return $collection;
	}

	public static function identifier($resource): array
	{
		return Document::type($resource->getResourceType())->id($resource->getRouteKey())->toArray();
	}
}
