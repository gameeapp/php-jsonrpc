<?php

declare(strict_types=1);

namespace Gamee\JsonRPC\Cache;

use Gamee\JsonRPC\Cache\Exception\InvalidKeyException;
use Gamee\JsonRPC\Cache\Exception\NotImplementedException;
use Gamee\JsonRPC\Cache\Key\JsonSchemaMemberKey;
use Predis\Client;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class RedisPool implements CacheItemPoolInterface
{

	/**
	 * @var int
	 */
	private $ttlInSeconds;

	/**
	 * @var Client
	 */
	private $predisClient;

	/**
	 * @var array|CacheItemInterface[]
	 */
	private $deferrerItems = [];


	public function __construct(
		int $ttlInSeconds,
		Client $predisClient
	) {
		$this->ttlInSeconds = $ttlInSeconds;
		$this->predisClient = $predisClient;
	}


	/**
	 * {@inheritDoc}
	 */
	public function getItem($key)
	{
		$redisKey = $this->createKey($key);

		if (!$this->hasItem($key)) {
			return new SchemaCacheItem($redisKey, '', new \DateTimeImmutable(), false, false);
		}

		$value = $this->predisClient->hget(
			$redisKey->getKeyString(),
			$redisKey->getMemberKey()
		);

		$expiration = (new \DateTimeImmutable())->add(
			new \DateInterval(sprintf('PT%sS', $this->ttlInSeconds))
		);

		return new SchemaCacheItem($redisKey, $value, $expiration, true, true);
	}


	/**
	 * {@inheritDoc}
	 */
	public function getItems(array $keys = [])
	{
		$items = [];

		foreach ($keys as $key) {
			$items[] = $this->getItem($key);
		}

		return $items;
	}


	/**
	 * {@inheritDoc}
	 */
	public function hasItem($key)
	{
		$redisKey = $this->createKey($key);

		return $this->predisClient->hexists(
			$redisKey->getKeyString(),
			$redisKey->getMemberKey()
		) === 1;
	}


	/**
	 * {@inheritDoc}
	 */
	public function clear()
	{
		throw new NotImplementedException();
	}


	/**
	 * {@inheritDoc}
	 */
	public function deleteItem($key): bool
	{
		$redisKey = $this->createKey($key);

		$this->predisClient->hdel($redisKey->getKeyString(), [$redisKey->getMemberKey()]);

		return true;
	}


	/**
	 * {@inheritDoc}
	 */
	public function deleteItems(array $keys)
	{
		foreach ($keys as $key) {
			$this->deleteItem($key);
		}
	}


	/**
	 * {@inheritDoc}
	 */
	public function save(CacheItemInterface $item)
	{
		$redisKey = $this->createKey($item->getKey());

		$this->predisClient->hset(
			$redisKey->getKeyString(),
			$redisKey->getMemberKey(),
			$item->get()
		);
	}


	/**
	 * {@inheritDoc}
	 */
	public function saveDeferred(CacheItemInterface $item)
	{
		$this->deferrerItems[] = $item;
	}


	/**
	 * {@inheritDoc}
	 */
	public function commit()
	{
		foreach ($this->deferrerItems as $item) {
			$this->save($item);
		}

		return true;
	}


	private function createKey(string $key): JsonSchemaMemberKey
	{
		$matched = preg_match('/.+:.+/u', $key);

		if ($matched !== 1) {
			throw new InvalidKeyException('Key must be in format project:endpoint');
		}

		[$project, $endpoint] = explode(':', $key);

		return new JsonSchemaMemberKey($project, $endpoint);
	}
}