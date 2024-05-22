<?php

namespace App\JsonApi;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Testing\Assert as PHPUnit;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\ExpectationFailedException;

class JsonApiTestResponse
{
    public function assertJsonApiValidationErrors(): Closure
    {
        return function ($field) {
            /** @var TestResponse $this */

            $pointer = "/data/attributes/{$field}";

            if (Str::of($field)->startsWith('data')) {
                $pointer = "/" . str_replace('.', '/', $field);
            } elseif (Str::of($field)->startsWith('relationships')) {
                $pointer = "/data/" . str_replace('.', '/', $field) . "/data/id";
            }

            try {
                $this->assertJsonFragment([
                    'source' => ['pointer' => $pointer]
                ]);
            } catch (ExpectationFailedException $e) {
                PHPUnit::fail("Failed to find a validation error for {$field}." . PHP_EOL . PHP_EOL . $e->getMessage());
            }

            try {
                $this->assertJsonStructure([
                    'errors' => [
                        ['title', 'detail', 'source' => ['pointer']]
                    ]
                ]);
            } catch (ExpectationFailedException $e) {
                PHPUnit::fail("Failed to find the correct validation error message for {$field}." . PHP_EOL . PHP_EOL . $e->getMessage());
            }

            $this->assertHeader('Content-Type', 'application/vnd.api+json')->assertStatus(422);
        };
    }

    public function assertJsonApiResource(): Closure
    {
        return function ($model, array $attributes = []) {
            /** @var TestResponse $this */

            return $this->assertJson([
                'data' => [
                    'type' => $model->getResourceType(),
                    'id' => (string)$model->getRouteKey(),
                    'attributes' => $attributes,
                    'links' => [
                        'self' => route('api.v1.' . $model->getResourceType() . '.show', $model)
                    ]
                ],
            ])->assertHeader(
                'Location',
                route('api.v1.' . $model->getResourceType() . '.show', $model)
            );
        };
    }

    public function assertJsonApiRelationshipLinks(): Closure
    {
        return function ($model, $relationships) {
            /** @var TestResponse $this */
            foreach ($relationships as $relationship) {
                $this->assertJsonFragment([
                    'links' => [
                        'self' => route('api.v1.' . $model->getResourceType() . '.relationships.' . $relationship, $model),
                        'related' => route('api.v1.' . $model->getResourceType() . '.' . $relationship, $model)
                    ]
                ]);
            }

            return $this;
        };
    }

    public function assertJsonApiResourceCollection(): Closure
    {
        return function ($models, array $attributesKeys = []) {
            /** @var TestResponse $this */
            $this->assertJsonStructure([
                'data' => [
                    '*' => [
                        'attributes' => $attributesKeys,
                    ]
                ]
            ]);
            foreach ($models as $model) {
                $this->assertJsonFragment([
                    'id' => (string)$model->getRouteKey(),
                    'type' => $model->getResourceType(),
                    'links' => [
                        'self' => route('api.v1.' . $model->getResourceType() . '.show', $model)
                    ]
                ]);
            }

            return $this;
        };
    }

    public function assertJsonApiError(): Closure
    {
        return function ($status = null, $title = null, $detail = null) {
            /** @var TestResponse $this */
            try {
                $this->assertJsonStructure([
                    'errors' => [
                        '*' => ['title', 'detail']
                    ]
                ]);
            } catch (ExpectationFailedException $e) {
                PHPUnit::fail(
                    'Error objects MUST be returned as an array keyed by errors in the lop level of a JSON API document.'
                    . PHP_EOL . PHP_EOL . $e->getMessage()
                );
            }

            $title && $this->assertJsonFragment(['title' => $title]);
            $detail && $this->assertJsonFragment(['detail' => $detail]);
            $status && $this->assertJsonFragment(['status' => (string)$status])->assertStatus((int) $status);

            return $this;
        };
    }
}
