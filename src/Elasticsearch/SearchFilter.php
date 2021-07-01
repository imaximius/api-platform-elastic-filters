<?php

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

use Imaximius\ElasticFilterBundle\Abstracts\AbstractSearchFilter;

class SearchFilter extends AbstractSearchFilter
{
    /** @var mixed */
    protected $searchParameterName = 'search';

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
            $matches[] = ['wildcard' => [implode('.', $properties) => $value]];
        }

        $matchesQuery = isset($matches[1]) ? ['bool' => ['should' => $matches]] : $matches;
        if (null !== $nestedPath) {
            $matchQuery[] = ['nested' => ['path' => $nestedPath, 'query' => $matchesQuery]];
        } else {
            $matchQuery = $matchesQuery;
        }

        return ['bool' => ['must' => ['split_to_numeric_index' => ['bool' => ['should' => $matchQuery]]]]];
    }

    /**
     * Replace text template key from array and reindex it.
     *
     * @param array  $array               source array to replace key
     * @param string $replacedKeyTemplate literal template to be replaced with numeral index
     *
     * @return array array with replaced template key
     */
    public static function getRecurciveKeyReplaced($array, $replacedKeyTemplate = 'split_to_numeric_index')
    {
        $resultArray = [];
        $wasReplacedFlag = false;
        foreach ($array as $key => $value) {
            if ($key == $replacedKeyTemplate) {
                $resultArray[] = is_array($value) ? self::getRecurciveKeyReplaced($value, $replacedKeyTemplate) : $value;
                $wasReplacedFlag = true;
            } else {
                $resultArray[$key] = is_array($value) ? self::getRecurciveKeyReplaced($value, $replacedKeyTemplate) : $value;
            }
        }
        if ($wasReplacedFlag) {
            $resultArray = array_values($resultArray);
        }

        return $resultArray;
    }

    /**
     * Apply filter.
     */
    public function apply(array $clauseBody, string $resourceClass, ?string $operationName = null, array $context = []): array
    {
        if (!\is_array($properties = $context['filters'][$this->searchParameterName] ?? [])) {
            return $clauseBody;
        }

        $context['filters'] = array_merge($context['filters'], $properties);
        $result = parent::apply($clauseBody, $resourceClass, $operationName, $context);

        return self::getRecurciveKeyReplaced($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getProperties($resourceClass) as $property) {
            [$type] = $this->getMetadata($resourceClass, $property);

            if (!$type) {
                continue;
            }

            $description["$this->searchParameterName[$property]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }
}
