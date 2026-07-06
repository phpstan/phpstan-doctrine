<?php declare(strict_types = 1);

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use QueryResult\MultipleEntityManagers\Main\User;
use QueryResult\MultipleEntityManagers\Tenant\App;

$createObjectManager = static function (): ObjectManager {
	return new class () implements ObjectManager {

		public function find(string $className, $id)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function persist(object $object)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function remove(object $object)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function clear()
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function detach(object $object)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function refresh(object $object)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function flush()
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function getRepository(string $className)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function getClassMetadata(string $className)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function getMetadataFactory()
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function initializeObject(object $obj)
		{
			throw new LogicException('Not used by this fixture.');
		}

		public function contains(object $object)
		{
			throw new LogicException('Not used by this fixture.');
		}

	};
};

$defaultManager = $createObjectManager();
$tenantManager = $createObjectManager();

return new class ($defaultManager, $tenantManager) implements ManagerRegistry {

	private ObjectManager $defaultManager;

	private ObjectManager $tenantManager;

	public function __construct(ObjectManager $defaultManager, ObjectManager $tenantManager)
	{
		$this->defaultManager = $defaultManager;
		$this->tenantManager = $tenantManager;
	}

	public function getDefaultConnectionName()
	{
		return 'default';
	}

	public function getConnection($name = null)
	{
		return new stdClass();
	}

	public function getConnections()
	{
		return [
			'default' => new stdClass(),
			'tenant' => new stdClass(),
		];
	}

	public function getConnectionNames()
	{
		return [
			'default' => 'default',
			'tenant' => 'tenant',
		];
	}

	public function getDefaultManagerName()
	{
		return 'default';
	}

	public function getManager($name = null)
	{
		if ($name === 'tenant') {
			return $this->tenantManager;
		}

		return $this->defaultManager;
	}

	public function getManagers()
	{
		return [
			'default' => $this->defaultManager,
			'tenant' => $this->tenantManager,
		];
	}

	public function resetManager($name = null)
	{
		return $this->getManager($name);
	}

	public function getManagerNames()
	{
		return [
			'default' => 'default',
			'tenant' => 'tenant',
		];
	}

	public function getRepository($persistentObject, $persistentManagerName = null)
	{
		throw new LogicException('Not used by this fixture.');
	}

	public function getManagerForClass($class)
	{
		if ($class === App::class) {
			return $this->tenantManager;
		}

		if ($class === User::class) {
			return $this->defaultManager;
		}

		return null;
	}

};
