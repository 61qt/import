<?php

namespace Tests\QT\Import\Rules;

use RuntimeException;
use QT\Import\Rules\Equal;
use Tests\QT\Import\Stubs\Bar;
use Tests\QT\Import\Stubs\Foo;
use Illuminate\Database\Eloquent\Collection;

class EqualTest extends ValidateModelsTestCase
{
    /**
     * @return void
     */
    public function testNotEloquentBuilder()
    {
        $query = $this->mockConnection()->query();

        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1, 'name' => 'foo']),
        ]));

        $rule = new Equal($query, ['id'], [], ['bar_name' => 'hasOneBar.name']);

        $this->assertFalse($rule->validate([['id' => 1, 'bar_name' => 'foo']]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: bar_name 与系统已存在数据不一致,请重新填写'], $rule->errors()[0]);
    }

    /**
     * @return string
     */
    public function getRuleClass(): string
    {
        return Equal::class;
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

        $rule = new Equal($query, ['id'], [], ['name']);

        $this->assertTrue($rule->validate([['id' => 1, 'name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testIndexFieldAndEqualFieldIsEmpty()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection([
            new Foo(['id' => 1, 'name' => 'foo']),
        ]));

        $rule = new Equal($query, ['id'], [], ['name']);

        $this->assertTrue($rule->validate([['id' => '', 'name' => '']]));
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

        $rule = new Equal($query, ['id'], [], ['name']);

        $this->assertFalse($rule->validate([['id' => 1, 'name' => 'bar']]));
        $this->assertCount(1, $rule->errors());
        $this->assertEquals([0 => '原表第0行: name 与系统已存在数据不一致,请重新填写'], $rule->errors()[0]);
    }

    /**
     * @return void
     */
    public function testEmptyModel()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);

        $query->method('get')->willReturn(new Collection());

        $rule = new Equal($query, ['id'], [], ['name']);

        $this->assertTrue($rule->validate([['id' => 1, 'name' => 'bar']]));
        $this->assertCount(0, $rule->errors());
    }

    /**
     * @return void
     */
    public function testBelongsToRelationEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('with')->with('belongsToBar')->willReturnSelf();
        $query->method('get')->willReturn(new Collection([
            (new Foo(['id' => 1]))->setRelation('belongsToBar', new Bar(['name' => 'foo'])),
        ]));

        $rule = new Equal($query, ['id'], [], ['bar_name' => 'belongsToBar.name']);

        $this->assertTrue($rule->validate([['id' => 1, 'bar_name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testHasOneRelationEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('with')->with('hasOneBar')->willReturnSelf();
        $query->method('get')->willReturn(new Collection([
            (new Foo(['id' => 1]))->setRelation('hasOneBar', new Bar(['name' => 'foo'])),
        ]));

        $rule = new Equal($query, ['id'], [], ['bar_name' => 'hasOneBar.name']);

        $this->assertTrue($rule->validate([['id' => 1, 'bar_name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testBelongsToManyRelationEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('with')->with('belongsToManyBar')->willReturnSelf();
        $query->method('get')->willReturn(new Collection([
            (new Foo(['id' => 1]))->setRelation('belongsToManyBar', new Collection([new Bar(['name' => 'foo'])])),
        ]));

        $rule = new Equal($query, ['id'], [], ['bar_name' => 'belongsToManyBar.0.name']);

        $this->assertTrue($rule->validate([['id' => 1, 'bar_name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testHasManyThroughRelationEqual()
    {
        $query = $this->mockModelQueryBuilder(Foo::class);
        $query->method('with')->with('hasManyThroughBar')->willReturnSelf();
        $query->method('get')->willReturn(new Collection([
            (new Foo(['id' => 1]))->setRelation('hasManyThroughBar', new Collection([new Bar(['name' => 'foo'])])),
        ]));

        $rule = new Equal($query, ['id'], [], ['bar_name' => 'hasManyThroughBar.0.name']);

        $this->assertTrue($rule->validate([['id' => 1, 'bar_name' => 'foo']]));
        $this->assertEmpty($rule->errors());
    }

    /**
     * @return void
     */
    public function testUnableGetRelationField()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("无法从Relation上获取关联字段");

        $query = $this->mockModelQueryBuilder(Foo::class);

        new Equal($query, ['id'], [], ['bar_name' => 'unknownRelation.name']);
    }
}
