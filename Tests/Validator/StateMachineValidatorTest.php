<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\StateMachineValidator;

class StateMachineValidatorTest extends TestCase
{
    public function testWithMultipleTransitionWithSameNameShareInput()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition from a place/state must have an unique name.');
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t1', 'a', 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // The graph looks like:
        //
        //   +----+     +----+     +---+
        //   | a  | --> | t1 | --> | b |
        //   +----+     +----+     +---+
        //    |
        //    |
        //    v
        //  +----+     +----+
        //  | t1 | --> | c  |
        //  +----+     +----+
    }

    public function testWithMultipleTos()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition in StateMachine can only have one output.');
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', 'a', ['b', 'c']);
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // The graph looks like:
        //
        // +---+     +----+     +---+
        // | a | --> | t1 | --> | b |
        // +---+     +----+     +---+
        //             |
        //             |
        //             v
        //           +----+
        //           | c  |
        //           +----+
    }

    public function testWithMultipleFroms()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition in StateMachine can only have one input.');
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', ['a', 'b'], 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // The graph looks like:
        //
        // +---+     +----+     +---+
        // | a | --> | t1 | --> | c |
        // +---+     +----+     +---+
        //             ^
        //             |
        //             |
        //           +----+
        //           | b  |
        //           +----+
    }

    public function testValid()
    {
        $places = ['a', 'b', 'c'];
        $transitions[] = new Transition('t1', 'a', 'b');
        $transitions[] = new Transition('t2', 'a', 'c');
        $definition = new Definition($places, $transitions);

        (new StateMachineValidator())->validate($definition, 'foo');

        // the test ensures that the validation does not fail (i.e. it does not throw any exceptions)
        $this->addToAssertionCount(1);

        // The graph looks like:
        //
        // +----+     +----+     +---+
        // | a  | --> | t1 | --> | b |
        // +----+     +----+     +---+
        //   |
        //   |
        //   v
        // +----+     +----+
        // | t2 | --> | c  |
        // +----+     +----+
    }

    public function testWithTooManyInitialPlaces()
    {
        $places = range('a', 'c');
        $transitions = [];
        $definition = new Definition($places, $transitions, ['a', 'b']);

        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('The state machine "foo" cannot store many places. But the definition has 2 initial places. Only one is supported.');

        (new StateMachineValidator())->validate($definition, 'foo');
    }
}
