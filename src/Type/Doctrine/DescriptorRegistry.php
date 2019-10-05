<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;

class DescriptorRegistry
{

	/** @var array<string, \PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor> */
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
		if (!isset($this->descriptors[$type])) {
			throw new \PHPStan\Type\Doctrine\DescriptorNotRegisteredException();
		}
		return $this->descriptors[$type];
	}

}
