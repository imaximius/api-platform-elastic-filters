<?php

declare(strict_types=1);

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Bridge\Elasticsearch\DataProvider\Filter\AbstractFilter;
use ApiPlatform\Core\Bridge\Elasticsearch\DataProvider\Filter\SortFilterInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Order the collection by given properties.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-sort.html
 *
 * @experimental
 *
 * @author Baptiste Meyer <baptiste.meyer@gmail.com>
 */
final class EmptyOrderFilter extends AbstractFilter implements SortFilterInterface
{
    /** @var string */
    private $orderParameterName;

    /**
     * {@inheritdoc}
     */
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, string $orderParameterName = 'order', ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);

        $this->orderParameterName = $orderParameterName;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(array $clauseBody, string $resourceClass, ?string $operationName = null, array $context = []): array
    {
        return $clauseBody;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];
        foreach ($this->getProperties($resourceClass) as $property) {
            $description["$this->orderParameterName[$property]"] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }
}
