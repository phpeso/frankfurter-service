<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\Tests;

use ArrayObject;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Responses\ErrorResponse;
use Peso\Services\FrankfurterService;
use PHPUnit\Framework\TestCase;

final class EdgeCasesTest extends TestCase
{
    public function testInvalidObject(): void
    {
        $service = new FrankfurterService();

        $response = $service->send(new ArrayObject());
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals(
            'Unsupported request type: "ArrayObject"',
            $response->exception->getMessage(),
        );
    }
}
