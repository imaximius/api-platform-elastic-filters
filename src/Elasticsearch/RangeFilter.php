<?php

namespace Imaximius\ElasticFilterBundle\Elasticsearch;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Core\Bridge\Elasticsearch\Api\IdentifierExtractorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use Imaximius\ElasticFilterBundle\Abstracts\AbstractSearchFilter;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class RangeFilter extends AbstractSearchFilter implements DateFilterInterface
{
    /** @var mixed */
    protected $searchParameterName = 'range';

    private LoggerInterface $logger;

    public function __construct(
        PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        PropertyMetadataFactoryInterface $propertyMetadataFactory,
        ResourceClassResolverInterface $resourceClassResolver,
        IdentifierExtractorInterface $identifierExtractor,
        IriConverterInterface $iriConverter,
        PropertyAccessorInterface $propertyAccessor,
        LoggerInterface $logger,
        ?NameConverterInterface $nameConverter = null,
        ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $identifierExtractor, $iriConverter, $propertyAccessor, $nameConverter, $properties);

        $this->logger = $logger;
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuery(string $property, array $values, ?string $nestedPath): array
    {
        $operators = [self::PARAMETER_BEFORE, self::PARAMETER_STRICTLY_BEFORE, self::PARAMETER_AFTER, self::PARAMETER_STRICTLY_AFTER];
        foreach ($values as $operator => $value) {
            if (!\in_array($operator, $operators, true)) {
                unset($values[$operator]);
            }
        }

        if (empty($values)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one valid operator ("%s") is required for "%s" property', implode('", "', $operators), $property)),
            ]);

            return [];
        }

        $properties = [];
        $propertyParts = explode('.', $property);
        foreach ($propertyParts as $part) {
            $properties[] = $part;
        }

        $matches = [];
        foreach ($values as $operation => $value) {
            switch ($operation) {
                case self::PARAMETER_BEFORE:
                    $matches[] = ['range' => [implode('.', $properties) => ['lte' => $value]]];
                    break;
                case self::PARAMETER_STRICTLY_BEFORE:
                    $matches[] = ['range' => [implode('.', $properties) => ['lt' => $value]]];
                    break;
                case self::PARAMETER_AFTER:
                    $matches[] = ['range' => [implode('.', $properties) => ['gte' => $value]]];
                    break;
                case self::PARAMETER_STRICTLY_AFTER:
                    $matches[] = ['range' => [implode('.', $properties) => ['gt' => $value]]];
                    break;
            }
        }

        $matchQuery = ['bool' => ['must' => $matches]];

        return $matchQuery;
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
            $description += $this->getFilterDescription($property, self::PARAMETER_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_AFTER);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_AFTER);
        }

        return $description;
    }

    /**
     * Gets filter description.
     */
    protected function getFilterDescription(string $property, string $period): array
    {
        $propertyName = $this->normalizePropertyName($property);

        return [
            sprintf('%s[%s]', $propertyName, $period) => [
                'property' => $propertyName,
                'type' => \DateTimeInterface::class,
                'required' => false,
            ],
        ];
    }

    /**
     * @param mixed $property
     *
     * @return string
     */
    protected function normalizePropertyName($property)
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map([$this->nameConverter, 'normalize'], explode('.', (string) $property)));
    }

    /**
     * Normalize the value.
     *
     * @return int|float|null
     */
    private function normalizeValue(string $value, string $operator)
    {
        if (!is_numeric($value)) {
            $this->getLogger()->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
            ]);

            return null;
        }

        return $value + 0; // coerce $value to the right type.
    }

    public function apply(array $clauseBody, string $resourceClass, ?string $operationName = null, array $context = []): array
    {
        $searches = [];

        foreach ($context['filters'] ?? [] as $property => $values) {
            [$type, $hasAssociation, $nestedResourceClass, $nestedProperty] = $this->getMetadata($resourceClass, $property);

            if (!$type) {
                continue;
            }

            if (!$this->hasValidValues($values, $type)) {
                continue;
            }

            $property = null === $this->nameConverter ? $property : $this->nameConverter->normalize($property, $resourceClass, null, $context); /** @phpstan-ignore-line */
            $nestedPath = $this->getNestedFieldPath($resourceClass, $property);
            $nestedPath = null === $nestedPath || null === $this->nameConverter ? $nestedPath : $this->nameConverter->normalize($nestedPath, $resourceClass, null, $context); /* @phpstan-ignore-line */

            $searches[] = $this->getQuery($property, $values, $nestedPath);
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
