<?php

declare(strict_types=1);

namespace Inilim\Test\Ast;

use Inilim\SqliteParser\Ast\Script;
use Inilim\SqliteParser\Ast\Statement;
use JsonSerializable;
use PHPUnit\Framework\TestCase;

final class ScriptTest extends TestCase
{
    public function testEmptyScriptConstructs(): void
    {
        $script = new Script([]);

        $this->assertSame([], $script->statements);
        $this->assertSame(['statements' => []], $script->attributes);
    }

    public function testScriptWithStatements(): void
    {
        /** @var Statement $stmt */
        $stmt = new class extends Statement {};

        $script = new Script([$stmt]);

        $this->assertCount(1, $script->statements);
        $this->assertSame([$stmt], $script->statements);
        $this->assertArrayHasKey('statements', $script->attributes);
        $this->assertSame([$stmt], $script->attributes['statements']);
    }

    public function testScriptImplementsJsonSerializable(): void
    {
        $script = new Script([]);

        $this->assertInstanceOf(JsonSerializable::class, $script);
    }

    public function testJsonSerializeReturnsAttributes(): void
    {
        $script = new Script([]);

        $this->assertSame($script->attributes, $script->jsonSerialize());
        $this->assertSame('{"statements":[]}', json_encode($script, JSON_THROW_ON_ERROR));
    }

    public function testAttributesDoesNotContainItself(): void
    {
        $script = new Script([]);

        $this->assertArrayNotHasKey('attributes', $script->attributes);
    }
}