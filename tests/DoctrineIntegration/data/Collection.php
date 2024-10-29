<?php

namespace Bug621;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use function PHPStan\Testing\assertType;

class Foo
{

	/** @var Collection<int, Item> */
	private $items;

	public function __construct()
	{
		/** @var ArrayCollection<int, int> $numbers */
		$numbers = new ArrayCollection([1, 2, 3]);

		$filteredNumbers = $numbers->filter(function (int $number): bool {
			return $number % 2 === 1;
		});
		assertType('Doctrine\Common\Collections\ArrayCollection<int, int>', $filteredNumbers);

		$items = $filteredNumbers->map(static function (int $number): Item {
			return new Item();
		});
		assertType('Doctrine\Common\Collections\ArrayCollection<int, Bug621\Item>', $items);

		$this->items = $items;
	}

	public function removeOdd(): void
	{
		$this->items = $this->items->filter(function (Item $item, int $idx): bool {
			return $idx % 2 === 1;
		});
		assertType('Doctrine\Common\Collections\Collection<int, Bug621\Item>', $this->items);
	}

	public function __clone()
	{
		$this->items = $this->items->map(
			static function (Item $item): Item {
				return clone $item;
			}
		);
		assertType('Doctrine\Common\Collections\Collection<int, Bug621\Item>', $this->items);
	}

}

class Item
{

}
