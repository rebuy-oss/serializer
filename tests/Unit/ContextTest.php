<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Unit;

use Liip\Serializer\Context;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class ContextTest extends TestCase
{
    public function testDuplicateGroups(): void
    {
        $context = new Context();
        $context->setGroups(['a', 'b', 'a']);
        self::assertSame(['a', 'b'], $context->getGroups());
    }
}
