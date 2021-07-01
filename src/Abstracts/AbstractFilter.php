<?php

declare(strict_types=1);

namespace Imaximius\ElasticFilterBundle\Abstracts;

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Bridge\Elasticsearch\DataProvider\Filter\FilterInterface;
use ApiPlatform\Core\Exception\PropertyNotFoundException;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Imaximius\ElasticFilterBundle\Util\FieldDatatypeTrait;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Abstract class with helpers for easing the implementation of a filter.
 *
 * @experimental
 *
 * @author Baptiste Meyer <baptiste.meyer@gmail.com>
 */
abstract class AbstractFilter implements FilterInterface
{
    use FieldDatatypeTrait { getNestedFieldPath as protected; }

    /** @var array|null */
    protected $properties;

    /** @var PropertyNameCollectionFactoryInterface */
    protected $propertyNameCollectionFactory;

    /** @var NameConverterInterface|null */
    protected $nameConverter;

    /**
     * AbstractFilter constructor.
     */
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, ?array $properties = null)
    {
        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
        $this->resourceClassResolver = $resourceClassResolver;
        $this->nameConverter = $nameConverter;
        $this->properties = $properties;
    }

    /**
     * Gets all enabled properties for the given resource class.
     */
    protected function getProperties(string $resourceClass): \Traversable
    {
        if (null !== $this->properties) {
            return yield from array_keys($this->properties);
        }

        try {
            yield from $this->propertyNameCollectionFactory->create($resourceClass);
        } catch (ResourceClassNotFoundException $e) {
        }
    }

    /**
     * Is the given property enabled?
     */
    protected function hasProperty(string $resourceClass, string $property): bool
    {
        return \in_array($property, iterator_to_array($this->getProperties($resourceClass)), true);
    }

    /**
     * Gets info about the decomposed given property for the given resource class.
     *
     * Returns an array with the following info as values:
     *   - the {@see Type} of the decomposed given property
     *   - is the decomposed given property an association?
     *   - the resource class of the decomposed given property
     *   - the property name of the decomposed given property
     */
    protected function getMetadata(string $resourceClass, string $property): array
    {
        $noop = [null, null, null, null];

        if (!$this->hasProperty($resourceClass, $property)) {
            return $noop;
        }

        $properties = explode('.', $property);
        $totalProperties = \count($properties);
        $currentResourceClass = $resourceClass;
        $hasAssociation = false;
        $currentProperty = null;
        $type = null;

        foreach ($properties as $index => $currentProperty) {
            try {
                $propertyMetadata = $this->propertyMetadataFactory->create($currentResourceClass, $currentProperty);
            } catch (PropertyNotFoundException $e) {
                return $noop;
            }

            if (null === $type = $propertyMetadata->getType()) {
                return $noop;
            }

            ++$index;

            $builtinType = $type->getBuiltinType();
            if (Type::BUILTIN_TYPE_OBJECT !== $builtinType && Type::BUILTIN_TYPE_ARRAY !== $builtinType) {
                if ($totalProperties === $index) {
                    break;
                }

                return $noop;
            }

            if (!empty($type->getClassName())) {
                $className = $type->getClassName();
            } elseif (!empty($type->getCollectionValueType())) {
                $className = $type->getCollectionValueType()->getClassName();
            } elseif (Type::BUILTIN_TYPE_ARRAY == $builtinType && $totalProperties === $index) {
                break;
            } else {
                return $noop;
            }

            $currentResourceClass = $className;
            $hasAssociation = $totalProperties === $index;
        }

        return [$type, $hasAssociation, $currentResourceClass, $currentProperty];
    }
}
