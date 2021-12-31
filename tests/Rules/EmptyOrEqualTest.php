<?php

namespace Tests\QT\Import\Rules;

use Tests\QT\Import\Stubs\Foo;
use QT\Import\Rules\EmptyOrEqual;
use Illuminate\Database\Eloquent\Collection;

class EmptyOrEqualTest extends ValidateModelsTestCase
{
    /**
     * @return string
     */
    public function getRuleClass(): string
    {
        return EmptyOrEqual::class;
    }

    /**
     * @return void
     */
    public function testModelIsNull()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection());

        $rule = new EmptyOrEqual($query, ['id'], [], ['name']);

        $this->assertTrue($rule->validate([['id' => 1, 'name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testModelFieldIsNull()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1, 'name' => null]),
        ]));

        $rule = new EmptyOrEqual($query, ['id'], [], ['name']);

        $this->assertTrue($rule->validate([['id' => 1, 'name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testImportRowIsNull()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1, 'name' => 'foo']),
        ]));

        $rule = new EmptyOrEqual($query, ['id'], [], ['name']);

        $this->assertTrue($rule->validate([['id' => 1, 'name' => null]]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1, 'name' => 'foo']),
        ]));

        $rule = new EmptyOrEqual($query, ['id'], [], ['name']);
        $rule->validate([['id' => 1, 'name' => 'foo']]);

        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testNotEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1, 'name' => 'foo']),
        ]));

        $rule = new EmptyOrEqual($query, ['id'], [], ['name']);
        $rule->validate([['id' => 1, 'name' => 'bar']]);

        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: name 与系统已存在数据不一致,请重新填写'], $rule->errors()[0]);
    }
}
