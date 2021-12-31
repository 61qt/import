<?php

namespace Tests\QT\Import\Rules;

use QT\Import\Rules\Unique;
use Tests\QT\Import\Stubs\Foo;
use Illuminate\Database\Eloquent\Collection;

class UniqueTest extends ValidateModelsTestCase
{
    /**
     * @return string
     */
    public function getRuleClass(): string
    {
        return Unique::class;
    }

    /**
     * 数据存在但是未匹配成功
     */
    public function testUnique()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 2])
        ]));

        $rule = new Unique($query, ['id']);

        $this->assertTrue($rule->validate([['id' => 1]]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * 数据不存在时判断是否唯一
     */
    public function testEmptyModelsUnique()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('get')->willReturn(new Collection());

        $rule = new Unique($query, ['id']);

        $this->assertTrue($rule->validate([['id' => 1]]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * 数据存在时判断是否唯一
     */
    public function testNotUnique()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1]),
        ]));

        $rule = new Unique($query, ['id']);

        $this->assertFalse($rule->validate([['id' => 1]]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: id 已存在,请保证数据不重复后再次导入'], $rule->errors()[0]);
    }
}
