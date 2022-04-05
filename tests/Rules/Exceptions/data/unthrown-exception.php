<?php

namespace UnthrownException;

class FooFacade
{

	/** @var \Doctrine\ORM\EntityManager */
	private $entityManager;

	public function doFoo(): void
	{
		try {
			$this->entityManager->flush();
		} catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
			// pass
		}
	}

}
