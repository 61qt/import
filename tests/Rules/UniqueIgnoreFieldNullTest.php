<?php

namespace Tests\QT\Import\Rules;

use Tests\QT\Import\Stubs\Foo;
use QT\Import\Rules\UniqueIgnoreFieldNull;
use Illuminate\Database\Eloquent\Collection;

class UniqueIgnoreFieldNullTest extends ValidateModelsTestCase
{
    /**
     * @return string
     */
    public function getRuleClass(): string
    {
        return UniqueIgnoreFieldNull::class;
    }

    /**
     * 数据存在时判断是否唯一
     */
    public function testNotUnique()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('get')->willReturn(new Collection([
            new Foo(['name' => 'foo', 'id' => 1])
        ]));

        $rule = new UniqueIgnoreFieldNull($query, ['name'], [], ['id' => true]);

        $this->assertFalse($rule->validate([['name' => 'foo', 'id' => 1]]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: name 已存在,请保证数据不重复后再次导入'], $rule->errors()[0]);
    }
}
