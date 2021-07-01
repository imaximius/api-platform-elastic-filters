<?php

declare(strict_types=1);

namespace Imaximius\ElasticFilterBundle\Util;

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Exception\PropertyNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Field datatypes helpers.
 *
 * @experimental
 *
 * @author Baptiste Meyer <baptiste.meyer@gmail.com>
 */
trait FieldDatatypeTrait
{
    /**
     * @var PropertyMetadataFactoryInterface
     */
    private $propertyMetadataFactory;

    /**
     * @var ResourceClassResolverInterface
     */
    private $resourceClassResolver;

    /**
     * Is the decomposed given property of the given resource class potentially mapped as a nested field in Elasticsearch?
     */
    private function isNestedField(string $resourceClass, string $property): bool
    {
        return null !== $this->getNestedFieldPath($resourceClass, $property);
    }

    /**
     * Get the nested path to the decomposed given property (e.g.: foo.bar.baz => foo.bar).
     */
    private function getNestedFieldPath(string $resourceClass, string $property): ?string
    {
        $properties = explode('.', $property);
        $currentProperty = array_shift($properties);

        if (!$properties) {
            return null;
        }

        try {
            $propertyMetadata = $this->propertyMetadataFactory->create($resourceClass, $currentProperty);
        } catch (PropertyNotFoundException $e) {
            return null;
        }

        if (null === $type = $propertyMetadata->getType()) {
            return null;
        }

        if (
            Type::BUILTIN_TYPE_OBJECT === $type->getBuiltinType()
            && null !== ($nextResourceClass = $type->getClassName())
        ) {
            $nestedPath = $this->getNestedFieldPath($nextResourceClass, implode('.', $properties));

            return null === $nestedPath ? $currentProperty : "$currentProperty.$nestedPath";
        }

        if (
            null !== ($type = $type->getCollectionValueType())
            && Type::BUILTIN_TYPE_OBJECT === $type->getBuiltinType()
            && null !== ($className = $type->getClassName())
        ) {
            return $currentProperty;
        }

        return null;
    }
}
