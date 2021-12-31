<?php

namespace Tests\QT\Import\Rules;

use QT\Import\Rules\Exists;
use Tests\QT\Import\Stubs\Foo;
use Illuminate\Database\Eloquent\Collection;

class ExistsTest extends ValidateModelsTestCase
{
    /**
     * @return string
     */
    public function getRuleClass(): string
    {
        return Exists::class;
    }

    /**
     * 测试数据存在时
     */
    public function testExists()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1]),
        ]));

        $rule = new Exists($query, ['id']);

        $this->assertTrue($rule->validate([['id' => 1]]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * 测试数据存在时
     */
    public function testNotExists()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('get')->willReturn(new Collection());

        $rule = new Exists($query, ['id']);

        $this->assertFalse($rule->validate([['id' => 1]]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: id 不存在,保证数据已存在时再尝试导入'], $rule->errors()[0]);
    }
}
