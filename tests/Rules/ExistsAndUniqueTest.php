<?php

namespace Tests\QT\Import\Rules;

use Tests\QT\Import\Stubs\Foo;
use QT\Import\Rules\ExistsAndUnique;
use Illuminate\Database\Eloquent\Collection;

class ExistsAndUniqueTest extends ValidateModelsTestCase
{
    /**
     * @return string
     */
    public function getRuleClass(): string
    {
        return ExistsAndUnique::class;
    }

    /**
     * 测试数据存在时
     */
    public function testExistsRecord()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['name' => 'foo']),
        ]));

        $rule = new ExistsAndUnique($query, ['name']);

        $this->assertTrue($rule->validate([['name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * 测试数据不存在
     */
    public function testNotExistsRecord()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection());

        $rule = new ExistsAndUnique($query, ['name'], [], [], [], [], '未匹配到相同的名称');

        $this->assertFalse($rule->validate([['name' => 'foo']]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: 未匹配到相同的名称'], $rule->errors()[0]);
    }

    /**
     * 测试数据存在多条
     */
    public function testMultipleExistsRecord()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['name' => 'foo']),
            new Foo(['name' => 'foo']),
        ]));

        $rule = new ExistsAndUnique($query, ['name'], [], [], [], [], null, '匹配到有多条名称一致的记录');

        $this->assertFalse($rule->validate([['name' => 'foo']]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: 匹配到有多条名称一致的记录'], $rule->errors()[0]);
    }

    /**
     * 测试存在多条部分一致,但是唯一键不一致的数据
     */
    public function testMultipleExistsRecordButUniqueKeyNotEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['name' => 'foo', 'id' => 1]),
            new Foo(['name' => 'foo', 'id' => 2]),
        ]));

        $rule = new ExistsAndUnique($query, ['name'], ['id']);

        $this->assertTrue($rule->validate([['name' => 'foo', 'id' => 1]]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * 测试存在多条部分一致并且唯一键匹配失败的数据
     */
    public function testMultipleExistsRecordButUniqueKeyMatchFailed()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['name' => 'foo', 'id' => 1]),
            new Foo(['name' => 'foo', 'id' => 2]),
        ]));

        $rule = new ExistsAndUnique($query, ['name'], ['id'], [], [], [], '未匹配到相同的名称');

        $this->assertFalse($rule->validate([['name' => 'foo', 'id' => 3]]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: 未匹配到相同的名称'], $rule->errors()[0]);
    }

    /**
     * 测试存在多条完全一致的数据
     */
    public function testMultipleCompletelyEqualRecord()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['name' => 'foo', 'id' => 2]),
            new Foo(['name' => 'foo', 'id' => 2]),
        ]));

        $rule = new ExistsAndUnique($query, ['name'], ['id'], [], [], [], null, '匹配到有多条名称与id完全一致的记录');

        $this->assertFalse($rule->validate([['name' => 'foo', 'id' => 2]]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: 匹配到有多条名称与id完全一致的记录'], $rule->errors()[0]);
    }
}
