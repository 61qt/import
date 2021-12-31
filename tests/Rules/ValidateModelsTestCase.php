<?php

namespace Tests\QT\Import\Rules;

use Closure;
use Mockery;
use Illuminate\Database\Connection;
use QT\Import\Contracts\Validatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * ValidateModelsTestCase
 *
 * @package Tests\QT\Import\Rules
 */
abstract class ValidateModelsTestCase extends BaseTestCase
{
    /**
     * @return string
     */
    abstract public function getRuleClass(): string;

    /**
     * 测试是否实现了validatable接口
     */
    public function testRuleImplementsValidatable(): void
    {
        $class = $this->getRuleClass();

        $this->assertTrue(is_subclass_of($class, Validatable::class));
    }

    /**
     * 获取基础 Eloquent query builder
     *
     * @param $class
     * @return \PHPUnit\Framework\MockObject\MockObject|Builder
     */
    protected function mockModelQueryBuilder($class)
    {
        $model = tap(new $class, function ($model) {
            $resolver = Mockery::mock(ConnectionResolverInterface::class);
            $resolver->shouldReceive('connection')->andReturn($this->mockConnection());

            $model->setConnectionResolver($resolver);
        });

        $query = $this->getMockBuilder(Builder::class)
            ->setConstructorArgs([$model->toBase()])
            ->getMock();

        $whereCallback = function (...$args) use ($query) {
            // 触发回调函数
            $func = fn($arg) => $arg instanceof Closure && $arg($query);

            return tap($query, fn() => array_walk($args, $func));
        };

        $query->method('__call')->willReturn($query);
        $query->method('getModel')->willReturn($model);
        $query->method('where')->willReturnCallback($whereCallback);
        $query->method('orWhere')->willReturnCallback($whereCallback);

        return $query;
    }

    /**
     * Mock Database connection
     */
    protected function mockConnection()
    {
        $grammar   = new Grammar;
        $processor = Mockery::mock(Processor::class);
        $processor->shouldReceive('processSelect')->andReturnUsing(function ($query, $result) {
            return $result;
        });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getName')->andReturn('name');
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $connection->shouldReceive('query')->andReturnUsing(function () use ($connection, $grammar, $processor) {
            return $this->mockQueryBuilder($connection, $grammar, $processor);
        });

        return $connection;
    }

    /**
     * Mock Query builder
     *
     * @param $connection
     * @param $grammar
     * @param $processor
     */
    protected function mockQueryBuilder($connection, $grammar, $processor)
    {
        return $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$connection, $grammar, $processor])
            ->getMock();
    }
}
