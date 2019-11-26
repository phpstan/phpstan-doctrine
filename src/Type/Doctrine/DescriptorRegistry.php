<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\DBAL\Types\Type;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;

class DescriptorRegistry
{

	/** @var array<class-string<\Doctrine\DBAL\Types\Type>, \PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor> */
	private $descriptors = [];

	/**
	 * @param \PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor[] $descriptors
	 */
	public function __construct(array $descriptors)
	{
		foreach ($descriptors as $descriptor) {
			$this->descriptors[$descriptor->getType()] = $descriptor;
		}
	}

	public function get(string $type): DoctrineTypeDescriptor
	{
		$typesMap = Type::getTypesMap();
		if (!isset($typesMap[$type])) {
			throw new \PHPStan\Type\Doctrine\DescriptorNotRegisteredException();
		}

		/** @var class-string<\Doctrine\DBAL\Types\Type> $typeClass */
		$typeClass = $typesMap[$type];
		if (!isset($this->descriptors[$typeClass])) {
			throw new \PHPStan\Type\Doctrine\DescriptorNotRegisteredException();
		}
		return $this->descriptors[$typeClass];
	}

}
