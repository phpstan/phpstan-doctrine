<?php declare(strict_types = 1);

namespace UnitOfWorkChangeSet\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ClassMetadata;

#[ORM\Entity]
#[ORM\Table(name: 'related_entities')]
class RelatedEntity
{

	/**
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
	private int $id;

	/**
	 */
	#[ORM\ManyToOne(targetEntity: SimpleEntity::class, inversedBy: 'relatedCollection')]
	private ?SimpleEntity $parent = null;

	public function setParent(?SimpleEntity $parent): void
	{
		$this->parent = $parent;
	}

	public static function loadMetadata(ClassMetadata $metadata): void
	{
		$metadata->setPrimaryTable(['name' => 'related_entities']);
		$metadata->mapField([
			'fieldName' => 'id',
			'type' => 'integer',
			'id' => true,
		]);
		$metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_AUTO);
		$metadata->mapManyToOne([
			'fieldName' => 'parent',
			'targetEntity' => SimpleEntity::class,
			'inversedBy' => 'relatedCollection',
			'joinColumns' => [[
				'name' => 'parent_id',
				'referencedColumnName' => 'id',
				'nullable' => true,
			]],
		]);
	}

}
