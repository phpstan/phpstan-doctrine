<?php declare(strict_types = 1);

namespace UnitOfWorkChangeSet\Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;

#[ORM\Entity]
#[ORM\Table(name: 'simple_entities')]
class SimpleEntity
{

	/**
	 * @var Collection<int, RelatedEntity>
	 */
	#[ORM\OneToMany(targetEntity: RelatedEntity::class, mappedBy: 'parent')]
	private Collection $relatedCollection;

	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private int $id;

	#[ORM\Column(type: 'integer')]
	private int $foo = 0;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $nullableFoo = null;

	#[ORM\ManyToOne(targetEntity: RelatedEntity::class)]
	#[ORM\JoinColumn(nullable: true)]
	private ?RelatedEntity $related = null;

	public function __construct()
	{
		$this->relatedCollection = new ArrayCollection();
	}

	public function setFoo(int $foo): void
	{
		$this->foo = $foo;
	}

	public function setNullableFoo(?int $nullableFoo): void
	{
		$this->nullableFoo = $nullableFoo;
	}

	public function setRelated(?RelatedEntity $related): void
	{
		$this->related = $related;
	}

	/**
	 * @param Collection<int, RelatedEntity> $relatedCollection
	 */
	public function setRelatedCollection(Collection $relatedCollection): void
	{
		$this->relatedCollection = $relatedCollection;
	}

	public static function loadMetadata(ClassMetadata $metadata): void
	{
		$metadata->setPrimaryTable(['name' => 'simple_entities']);
		$metadata->mapField([
			'fieldName' => 'id',
			'type' => 'integer',
			'id' => true,
		]);
		$metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_AUTO);
		$metadata->mapField([
			'fieldName' => 'foo',
			'type' => 'integer',
		]);
		$metadata->mapField([
			'fieldName' => 'nullableFoo',
			'type' => 'integer',
			'nullable' => true,
		]);
		$metadata->mapManyToOne([
			'fieldName' => 'related',
			'targetEntity' => RelatedEntity::class,
			'joinColumns' => [[
				'name' => 'related_id',
				'referencedColumnName' => 'id',
				'nullable' => true,
			]],
			'inversedBy' => 'relatedCollection',
		]);
		$metadata->mapOneToMany([
			'fieldName' => 'relatedCollection',
			'targetEntity' => RelatedEntity::class,
			'mappedBy' => 'parent',
		]);
	}

}
