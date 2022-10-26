<?php

namespace Bug375;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use function PHPStan\Testing\assertType;

class Foo
{

	/** @var Collection<int, Bar> */
	private $shippingOptions;

	public function __construct()
	{
		$this->shippingOptions = new ArrayCollection();
	}

	public function doFoo(): void
	{
		if ($this->shippingOptions->isEmpty()) {
			return;
		}

		assertType(Bar::class, $this->shippingOptions->first());
	}

}

class Bar
{

}
