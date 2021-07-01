<?php

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

use App\Filter\Abstracts\AbstractSearchFilter;

/**
 * Class EmptyFilter
 * Use for distributed services just for Swagger/Graphql documentation.
 */
class EmptyFilter extends AbstractSearchFilter
{
    /**
     * {@inheritdoc}
     */
    protected function getQuery(string $property, array $values, ?string $nestedPath): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->getProperties($resourceClass) as $property) {
            [$type, $hasAssociation] = $this->getMetadata($resourceClass, $property);
            foreach ([$property, "${property}[]"] as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $property,
                    'type' => @$hasAssociation ?: 'string',
                    'required' => false,
                ];
            }
        }

        return $description;
    }
}
