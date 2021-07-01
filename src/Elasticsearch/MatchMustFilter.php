<?php

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

use Imaximius\ElasticFilterBundle\Abstracts\AbstractSearchFilter;

class MatchMustFilter extends AbstractSearchFilter
{
    /**
     * {@inheritdoc}
     */
    protected function getQuery(string $property, array $values, ?string $nestedPath): array
    {
        $properties = [];
        $propertyParts = explode('.', $property);
        foreach ($propertyParts as $part) {
            $properties[] = $part;
        }

        $matches = [];
        foreach ($values as $value) {
            $matches[] = ['match' => [implode('.', $properties) => $value]];
        }

        $matchesQuery = isset($matches[1]) ? [['bool' => ['should' => $matches]]] : $matches;

        if (null !== $nestedPath) {
            $matchQuery = [['nested' => ['path' => $nestedPath, 'query' => ['bool' => ['must' => $matchesQuery]]]]];
        } else {
            $matchQuery = $matchesQuery;
        }

        return ['bool' => ['must' => $matchQuery]];
    }
}
