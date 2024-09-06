<?php declare(strict_types = 1);

namespace PHPStan\Classes;

use Doctrine\Persistence\Proxy;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

class DoctrineProxyForbiddenClassNamesExtension implements ForbiddenClassNameExtension
{

	private ObjectMetadataResolver $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getClassPrefixes(): array
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return [];
		}

		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';

		if (!$objectManager instanceof $entityManagerInterface) {
			return [];
		}

		return [
			'Doctrine' => $objectManager->getConfiguration()->getProxyNamespace() . '\\' . Proxy::MARKER,
		];
	}

}
