<?php
namespace Neos\Neos\Ui\Domain\Service;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\MvcPropertyMappingConfiguration;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\TypeConverter\PersistentObjectConverter;
use Neos\Utility\Exception\InvalidTypeException;
use Neos\Utility\TypeHandling;

/**
 * @Flow\Scope("singleton")
 */
class NodePropertyConversionService
{
    /**
     * @Flow\Inject
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     * @var PropertyMapper
     */
    protected $propertyMapper;

    /**
     * Convert raw property values to the correct type according to a node type configuration
     *
     * @param string|array<int|string,mixed>|null $rawValue
     */
    public function convert(NodeType $nodeType, string $propertyName, string|array|null $rawValue): mixed
    {
        // WORKAROUND: $nodeType->getPropertyType() is missing the "initialize" call,
        // so we need to trigger another method beforehand.
        $nodeType->getFullConfiguration();
        $propertyType = $nodeType->getPropertyType($propertyName);

        if (is_null($rawValue)) {
            return null;
        }

        switch ($propertyType) {
            case 'string':
                return $rawValue;

            case 'reference':
                throw new \Exception("not implemented here, must be handled outside.");

            case 'references':
                throw new \Exception("not implemented here, must be handled outside.");

            case 'DateTime':
                return $this->convertDateTime($rawValue);

            case 'integer':
                return $this->convertInteger($rawValue);

            case 'boolean':
                return $this->convertBoolean($rawValue);

            case 'array':
                return $this->convertArray($rawValue);

            default:
                $innerType = $propertyType;
                if ($propertyType !== null) {
                    try {
                        $parsedType = TypeHandling::parseType($propertyType);
                        $innerType = $parsedType['elementType'] ?: $parsedType['type'];
                    } catch (InvalidTypeException $exception) {
                    }
                }

                if ($this->objectManager->isRegistered($innerType) && $rawValue !== '') {
                    $propertyMappingConfiguration = new MvcPropertyMappingConfiguration();
                    $propertyMappingConfiguration->allowOverrideTargetType();
                    $propertyMappingConfiguration->allowAllProperties();
                    $propertyMappingConfiguration->skipUnknownProperties();
                    $propertyMappingConfiguration->setTypeConverterOption(
                        PersistentObjectConverter::class,
                        PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED,
                        true
                    );
                    $propertyMappingConfiguration->setTypeConverterOption(
                        PersistentObjectConverter::class,
                        PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED,
                        true
                    );

                    return $this->propertyMapper->convert($rawValue, $propertyType, $propertyMappingConfiguration);
                } else {
                    if ($rawValue === '') {
                        // bugfix for https://github.com/neos/neos-development-collection/issues/4062
                        return null;
                    }
                    return $rawValue;
                }
        }
    }

    /**
     * Convert raw value to \DateTime
     *
     * @param string|array<int|string,mixed> $rawValue
     */
    protected function convertDateTime(string|array $rawValue): ?\DateTime
    {
        if (is_string($rawValue) && $rawValue !== '') {
            return (\DateTime::createFromFormat(\DateTime::W3C, $rawValue) ?: null)
                ?->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        return null;
    }

    /**
     * Convert raw value to integer
     *
     * @param string|array<int|string,mixed> $rawValue
     */
    protected function convertInteger(string|array $rawValue): ?int
    {
        if (is_numeric($rawValue)) {
            return (int) $rawValue;
        }

        return null;
    }

    /**
     * Convert raw value to boolean
     *
     * @param string|array<int|string,mixed> $rawValue
     */
    protected function convertBoolean(string|array $rawValue): bool
    {
        if (is_string($rawValue) && strtolower($rawValue) === 'false') {
            return false;
        }

        return (bool)$rawValue;
    }

    /**
     * Convert raw value to array
     *
     * @param string|array<int|string,mixed> $rawValue
     * @return array<int|string,mixed>
     */
    protected function convertArray(string|array $rawValue): array
    {
        if (is_string($rawValue)) {
            return json_decode($rawValue, true);
        }

        return $rawValue;
    }
}
