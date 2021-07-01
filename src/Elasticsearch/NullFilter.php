<?php

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

use Imaximius\ElasticFilterBundle\Abstracts\AbstractSearchFilter;

class NullFilter extends AbstractSearchFilter
{
    /** @var mixed */
    protected $emptyFilter = 'is_not_empty';

    /** @var mixed */
    protected $operation = 'must';

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

        $isNotNull = [];
        $isNotNull[] = ['exists' => ['field' => implode('.', $properties)]];

        if (null !== $nestedPath) {
            $isNotNullQuery = !empty($isNotNull) ? [['nested' => ['path' => $nestedPath, 'query' => $isNotNull]]] : null;
        } else {
            $isNotNullQuery = $isNotNull;
        }
        $responceNotNull = !empty($isNotNullQuery) ? ['bool' => [$this->operation => $isNotNullQuery]] : [];

        return $responceNotNull;
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getProperties($resourceClass) as $property) {
            [$type] = $this->getMetadata($resourceClass, $property);

            if (!$type) {
                continue;
            }
            $description[sprintf('%s[%s]', $this->emptyFilter, $property)] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }

    public function apply(array $clauseBody, string $resourceClass, ?string $operationName = null, array $context = []): array
    {
        if (!\is_array($properties = $context['filters'][$this->emptyFilter] ?? [])) {
            return $clauseBody;
        }

        $searches = [];

        foreach ($properties as $property => $direction) {
            [$type] = $this->getMetadata($resourceClass, $property);
            if (!$type) {
                continue;
            }

            $property = null === $this->nameConverter ? $property : $this->nameConverter->normalize($property, $resourceClass, null, $context); /** @phpstan-ignore-line */
            $nestedPath = $this->getNestedFieldPath($resourceClass, $property);
            $nestedPath = null === $nestedPath || null === $this->nameConverter ? $nestedPath : $this->nameConverter->normalize($nestedPath, $resourceClass, null, $context); /* @phpstan-ignore-line */

            $searches[] = $this->getQuery($property, [], $nestedPath);
        }

        if (!$searches) {
            return $clauseBody;
        }

        foreach ($searches as $search) {
            $clauseBody = array_merge_recursive($clauseBody, $search);
        }

        return $clauseBody;
    }
}
