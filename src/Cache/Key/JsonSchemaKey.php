<?php

declare(strict_types=1);

namespace Gamee\JsonRPC\Cache\Key;

class JsonSchemaKey
{

	private const CACHE_KEY = 'json-schema:<project>';

	/**
	 * @var string
	 */
	protected $projectName;


	public function __construct(string $projectName)
	{
		$this->projectName = $projectName;
	}


	public function getKeyString(): string
	{
		return str_replace('<project>', $this->projectName, self::CACHE_KEY);
	}
}