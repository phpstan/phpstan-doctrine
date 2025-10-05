<?php

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\Entity()
 */
class EntityWithSymfonyUid
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Column(type="uuid")
	 * @var Uuid
	 */
	private $uuid;

	/**
	 * @ORM\Column(type="uuid")
	 * @var string
	 */
	private $uuidInvalidType;

	/**
	 * @ORM\Column(type="ulid")
	 * @var Ulid
	 */
	private $ulid;

	/**
	 * @ORM\Column(type="ulid")
	 * @var string
	 */
	private $ulidInvalidType;
}
