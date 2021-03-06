<?php

namespace FuckingSmallTest;

use FuckingSmall\IoC\Container;
use FuckingSmall\IoC\Reference;
use FuckingSmall\IoC\TaggedReference;
use FuckingSmallTest\Fixture\SimpleService;
use FuckingSmallTest\Fixture\SimpleServiceInterface;
use FuckingSmallTest\Fixture\ComplexService;
use FuckingSmallTest\Fixture\ParentService;
use FuckingSmallTest\Fixture\ChildService;
use FuckingSmallTest\Fixture\ServiceWithCall;
use FuckingSmallTest\Fixture\Manager;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testCanAttacheAndResolveService()
    {
        $container = new Container();

        $container->attach('some_service', function() {
            $service = new \StdClass();
            $service->foo = 'bar';

            return $service;
        });

        $service = $container->resolve('some_service');

        $this->assertEquals('bar', $service->foo);
    }

    public function testCanAutoResolve()
    {
        $container = new Container();

        $service = $container->resolve(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testCanAutoResolveWithDependencies()
    {
        $container = new Container();

        $service = $container->resolve(ComplexService::class);

        $this->assertInstanceOf(ComplexService::class, $service);
    }

    public function testCanStoreASingleton()
    {
        $container = new Container();

        $container->attach('some_singleton', function() {
            static $object;

            if (null === $object) {
                $object = new \StdClass();
            }

            return $object;
        });

        $s1 = $container->resolve('some_singleton');
        $s2 = $container->resolve('some_singleton');

        $this->assertSame($s1, $s2);
    }

    public function testCanAutoResolveAlias()
    {
        $container = new Container();

        $container->alias('foo', SimpleService::class);
        $service = $container->resolve('foo');

        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testCanAutoResolveInterfaceAlias()
    {
        $container = new Container();

        $container->alias(simpleServiceInterface::class, SimpleService::class);
        $service = $container->resolve(SimpleServiceInterface::class);

        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testCanResolveAliasFromContainer()
    {
        $container = new Container();

        $container->alias('foo', 'bar', function () {
            return new \StdClass();
        });

        $foo = $container->resolve('foo');
        $bar = $container->resolve('bar');

        $this->assertInstanceOf(\StdClass::class, $foo);
        $this->assertInstanceOf(\StdClass::class, $bar);
    }

    public function testCanResolveParamsFromTemplate()
    {
        $container = new Container();

        $container->template(ParentService::class, ['foo' => 'bar']);

        $child = $container->resolve(ChildService::class);

        $this->assertEquals('bar', $child->getFoo());
    }

    public function testCanFindServicesByAttributes()
    {
        $container = new Container();

        $container->attach('foo', function() {}, ['tags' => ['foo']]);
        $container->attach('bar', function() {}, ['tags' => ['foo']]);
        $container->attach('bob', function() {}, ['tags' => ['foo']]);

        $services = $container->findByAttribute('tags', 'foo');

        $this->assertCount(3, $services);
    }

    public function testCanEditServiceAttributes()
    {
        $container = new Container();

        $container->attach('foo', function() {}, ['tags' => ['foo']]);

        $tags = $container->getAttribute('foo', 'tags');

        $this->assertEquals(['foo'], $tags);

        $tags[] = 'bar';

        $container->setAttribute('foo', 'tags', $tags);

        $tags = $container->getAttribute('foo', 'tags');

        $this->assertEquals(['foo', 'bar'], $tags);
    }

    public function testCallAttributeWithSingleArgument()
    {
        $container = new Container();

        $container->attach(ServiceWithCall::class, function() {
            return new ServiceWithCall();
        }, ['calls' => ['setSomething' => [['what']]]]);

        $service = $container->resolve(ServiceWithCall::class);

        $this->assertEquals('what', $service->getSomething());
    }

    public function testCallAttributeWithMultipleArguments()
    {
        $container = new Container();

        $container->attach(ServiceWithCall::class, function() {
            return new ServiceWithCall();
        }, ['calls' => ['setSomethingElse' => [['what', 'the']]]]);

        $service = $container->resolve(ServiceWithCall::class);

        $this->assertEquals('whatthe', $service->getSomethingElse());
    }

    public function testCallAttributeWithNoArguments()
    {
        $container = new Container();

        $container->attach(ServiceWithCall::class, function() {
            return new ServiceWithCall();
        }, ['calls' => ['set']]);

        $service = $container->resolve(ServiceWithCall::class);

        $this->assertEquals('has been set', $service->get());
    }

    public function testCanReferenceTaggedServices()
    {
        $container = new Container();

        $container->attach('foo', function() { return new \StdClass(); }, ['tags' => ['foo']]);
        $container->attach('bar', function() { return new \StdClass(); }, ['tags' => ['foo']]);
        $container->attach('bob', function() { return new \StdClass(); }, ['tags' => ['foo']]);

        $container->attach(Manager::class, function() {
            return new Manager();
        });

        $services = $container->findByAttribute('tags', 'foo');

        $calls = [];
        foreach ($services as $service) {
            $calls[] = [new Reference($service)];
        }

        $container->setAttribute(Manager::class, 'calls', ['addService' => $calls]);

        $manager = $container->resolve(Manager::class);

        $this->assertCount(3, $manager->getServices());
    }

    public function testCanUseTaggedReferenceServices()
    {
        $container = new Container();

        $container->attach('foo', function() { return new \StdClass(); }, ['tags' => ['foo']]);
        $container->attach('bar', function() { return new \StdClass(); }, ['tags' => ['foo']]);
        $container->attach('bob', function() { return new \StdClass(); }, ['tags' => ['foo']]);

        $container->attach(Manager::class, function() {
            return new Manager();
        }, ['calls' => ['AddService' => new TaggedReference('foo')]]);

        $manager = $container->resolve(Manager::class);

        $this->assertCount(3, $manager->getServices());
    }
}