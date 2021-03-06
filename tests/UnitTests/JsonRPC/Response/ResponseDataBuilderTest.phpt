<?php

declare(strict_types=1);

namespace Contributte\JsonRPC\UnitTests\JsonRPC\Response;

use Contributte\JsonRPC\Request\IRequest;
use Contributte\JsonRPC\Request\RequestCollection;
use Contributte\JsonRPC\Request\Type\ValidFormatRequest;
use Contributte\JsonRPC\Response\Enum\GenericCodes;
use Contributte\JsonRPC\Response\IResponse;
use Contributte\JsonRPC\Response\IResponseDataBuilder;
use Contributte\JsonRPC\Response\ResponseDataBuilder;
use Contributte\JsonRPC\Response\Type\SuccessResponse;
use Damejidlo\DateTimeFactory\DateTimeImmutableFactory;
use Mockery;
use Tester\Assert;
use Tester\TestCase;

require_once __DIR__ . '/../../../bootstrap.php';

/**
 * @testCase
 */
class ResponseDataBuilderTest extends TestCase
{

	private IResponseDataBuilder $responseDataBuilder;


	public function setUp(): void
	{
		$dateTimeImmutableFactory = new class() extends DateTimeImmutableFactory
		{

			public function getNow(): \DateTimeImmutable
			{
				return \DateTimeImmutable::createFromFormat(DATE_ATOM, '2018-03-24T11:36:19+01:00');
			}
		};

		$this->responseDataBuilder = new ResponseDataBuilder($dateTimeImmutableFactory);
	}


	public function testBuildParseError(): void
	{
		$expected = [
			'jsonrpc' => '2.0',
			'error' => [
				'code' => GenericCodes::CODE_PARSE_ERROR,
				'message' => 'Parse error',
				'data' => [
					'reason' => 'Foo',
				],
			],
			'id' => null,
			'time' => '2018-03-24T11:36:19+01:00',
		];

		Assert::same($expected, $this->responseDataBuilder->buildParseError('Foo'));
	}


	public function testUnknownResponseType(): void
	{
		$collection = $this->createSingleRequestCollection();

		Assert::exception(function() use ($collection): void {
			$this->responseDataBuilder->buildResponseBadge($collection);
		}, \InvalidArgumentException::class, 'Unknown response type');
	}


	public function testBuildSingleEmptyResponse(): void
	{
		$expected = [
			'jsonrpc' => '2.0',
			'result' => new \stdClass,
			'id' => 'iddddddddd',
			'time' => '2018-03-24T11:36:19+01:00',
		];

		$collection = $this->createSingleRequestCollection(
			new ValidFormatRequest('foo', new \stdClass(), 'iddddddddd'),
			new SuccessResponse(null)
		);

		$data = $this->responseDataBuilder->buildResponseBadge($collection);

		Assert::true(isset($data['result']));
		Assert::true($data['result'] instanceof \stdClass);

		unset($data['result'], $expected['result']);

		Assert::same($expected, $data);
	}


	public function testBuildSingleNonEmptyResponse(): void
	{
		$result = new \stdClass();
		$result->foo = 'bar';

		$expected = [
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => 'iddddddddd',
			'time' => '2018-03-24T11:36:19+01:00',
		];



		$collection = $this->createSingleRequestCollection(
			new ValidFormatRequest('foo', new \stdClass(), 'iddddddddd'),
			new SuccessResponse($result)
		);

		$data = $this->responseDataBuilder->buildResponseBadge($collection);

		Assert::same($expected, $data);
	}


	private function createSingleRequestCollection(
		?IRequest $request = null,
		?IResponse $response = null
	): RequestCollection
	{
		$request = $request ?: Mockery::mock(IRequest::class)
			->shouldReceive('getId()')->andReturn('iddddddddd')
			->getMock();

		$response = $response ?: Mockery::mock(IResponse::class);

		$collection = new RequestCollection();

		$collection->attach($request);
		$collection[$request] = $response;

		$collection->setIsBatchedRequest(false);

		return $collection;
	}
}

(new ResponseDataBuilderTest())->run();
