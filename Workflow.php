<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Workflow;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Symfony\Component\Workflow\MarkingStore\MultipleStateMarkingStore;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Workflow {
    private $definition;
    private $markingStore;
    private $dispatcher;
    private $authchecker;
    private $name;

    public function __construct( Definition $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, AuthorizationCheckerInterface $authchecker = null, $name = 'unnamed' ) {
        $this->definition   = $definition;
        $this->markingStore = $markingStore ?: new MultipleStateMarkingStore();
        $this->dispatcher   = $dispatcher;
        $this->authchecker  = $authchecker;
        $this->name         = $name;
    }

    /**
     * Returns the object's Marking.
     *
     * @param object $subject A subject
     *
     * @return Marking The Marking
     *
     * @throws LogicException
     */
    public function getMarking( $subject ) {
        $marking = $this->markingStore->getMarking( $subject );
        if ( ! $marking instanceof Marking ) {
            throw new LogicException( sprintf( 'The value returned by the MarkingStore is not an instance of "%s" for workflow "%s".', Marking::class, $this->name ) );
        }
        // check if the subject is already in the workflow
        if ( ! $marking->getPlaces() ) {
            if ( ! $this->definition->getInitialPlace() ) {
                throw new LogicException( sprintf( 'The Marking is empty and there is no initial place for workflow "%s".', $this->name ) );
            }
            $marking->mark( $this->definition->getInitialPlace() );
            // update the subject with the new marking
            $this->markingStore->setMarking( $subject, $marking );
        }
        // check that the subject has a known place
        $places = $this->definition->getPlaces();
        foreach ( $marking->getPlaces() as $placeName => $nbToken ) {
            if ( ! isset( $places[ $placeName ] ) ) {
                $message = sprintf( 'Place "%s" is not valid for workflow "%s".', $placeName, $this->name );
                if ( ! $places ) {
                    $message .= ' It seems you forgot to add places to the current workflow.';
                }
                throw new LogicException( $message );
            }
        }

        return $marking;
    }

    /**
     * Returns true if the transition is enabled.
     *
     * @param object $subject A subject
     * @param string $transitionName A transition
     *
     * @return bool true if the transition is enabled
     */
    public function can( $subject, $transitionName ) {
        $transitions = $this->getEnabledTransitions( $subject );
        foreach ( $transitions as $transition ) {
            if ( $transitionName === $transition->getName() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fire a transition.
     *
     * @param object $subject A subject
     * @param string $transitionName A transition
     *
     * @return Marking The new Marking
     *
     * @throws LogicException If the transition is not applicable
     * @throws LogicException If the transition does not exist
     */
    public function apply( $subject, $transitionName ) {
        $transitions = $this->getEnabledTransitions( $subject );
        // We can shortcut the getMarking method in order to boost performance,
        // since the "getEnabledTransitions" method already checks the Marking
        // state
        $marking = $this->markingStore->getMarking( $subject );
        $applied = false;
        foreach ( $transitions as $transition ) {
            if ( $transitionName !== $transition->getName() ) {
                continue;
            }
            if ( count( $transition->getRoles() ) > 0 ) {
                $notallowed = true;
                foreach ( $transition->getRoles() as $role ) {
                    if ( $this->authchecker->isGranted( $role ) ) {
                        $notallowed = false;
                    }
                }
                if ( $notallowed ) {
                    continue;
                }
            }
            $applied = true;
            $this->leave( $subject, $transition, $marking );
            $this->transition( $subject, $transition, $marking );
            $this->enter( $subject, $transition, $marking );
            $this->markingStore->setMarking( $subject, $marking );
            $this->entered( $subject, $transition, $marking );
            $this->announce( $subject, $transition, $marking );
        }
        if ( ! $applied ) {
            throw new LogicException( sprintf( 'Unable to apply transition "%s" for workflow "%s".', $transitionName, $this->name ) );
        }

        return $marking;
    }

    /**
     * Returns all enabled transitions.
     *
     * @param object $subject A subject
     *
     * @return Transition[] All enabled transitions
     */
    public function getEnabledTransitions( $subject ) {
        $enabled = array ();
        $marking = $this->getMarking( $subject );
        foreach ( $this->definition->getTransitions() as $transition ) {
            if ( $this->doCan( $subject, $marking, $transition ) ) {
                $enabled[] = $transition;
            }
        }

        return $enabled;
    }

    public function getName() {
        return $this->name;
    }

    /**
     * @return Definition
     */
    public function getDefinition() {
        return $this->definition;
    }

    private function doCan( $subject, Marking $marking, Transition $transition ) {
        foreach ( $transition->getFroms() as $place ) {
            if ( ! $marking->has( $place ) ) {
                return false;
            }
        }
        if ( true === $this->guardTransition( $subject, $marking, $transition ) ) {
            return false;
        }

        return true;
    }

    /**
     * @param object $subject
     * @param Marking $marking
     * @param Transition $transition
     *
     * @return bool|void boolean true if this transition is guarded, ie you cannot use it
     */
    private function guardTransition( $subject, Marking $marking, Transition $transition ) {
        if ( null === $this->dispatcher ) {
            return;
        }
        $event = new GuardEvent( $subject, $marking, $transition, $this->name );
        $this->dispatcher->dispatch( 'workflow.guard', $event );
        $this->dispatcher->dispatch( sprintf( 'workflow.%s.guard', $this->name ), $event );
        $this->dispatcher->dispatch( sprintf( 'workflow.%s.guard.%s', $this->name, $transition->getName() ), $event );

        return $event->isBlocked();
    }

    private function leave( $subject, Transition $transition, Marking $marking ) {
        $places = $transition->getFroms();
        if ( null !== $this->dispatcher ) {
            $event = new Event( $subject, $marking, $transition, $this->name );
            $this->dispatcher->dispatch( 'workflow.leave', $event );
            $this->dispatcher->dispatch( sprintf( 'workflow.%s.leave', $this->name ), $event );
            foreach ( $places as $place ) {
                $this->dispatcher->dispatch( sprintf( 'workflow.%s.leave.%s', $this->name, $place ), $event );
            }
        }
        foreach ( $places as $place ) {
            $marking->unmark( $place );
        }
    }

    private function transition( $subject, Transition $transition, Marking $marking ) {
        if ( null === $this->dispatcher ) {
            return;
        }
        $event = new Event( $subject, $marking, $transition, $this->name );
        $this->dispatcher->dispatch( 'workflow.transition', $event );
        $this->dispatcher->dispatch( sprintf( 'workflow.%s.transition', $this->name ), $event );
        $this->dispatcher->dispatch( sprintf( 'workflow.%s.transition.%s', $this->name, $transition->getName() ), $event );
    }

    private function enter( $subject, Transition $transition, Marking $marking ) {
        $places = $transition->getTos();
        if ( null !== $this->dispatcher ) {
            $event = new Event( $subject, $marking, $transition, $this->name );
            $this->dispatcher->dispatch( 'workflow.enter', $event );
            $this->dispatcher->dispatch( sprintf( 'workflow.%s.enter', $this->name ), $event );
            foreach ( $places as $place ) {
                $this->dispatcher->dispatch( sprintf( 'workflow.%s.enter.%s', $this->name, $place ), $event );
            }
        }
        foreach ( $places as $place ) {
            $marking->mark( $place );
        }
    }

    private function entered( $subject, Transition $transition, Marking $marking ) {
        if ( null === $this->dispatcher ) {
            return;
        }
        $event = new Event( $subject, $marking, $transition, $this->name );
        $this->dispatcher->dispatch( 'workflow.entered', $event );
        $this->dispatcher->dispatch( sprintf( 'workflow.%s.entered', $this->name ), $event );
        foreach ( $transition->getTos() as $place ) {
            $this->dispatcher->dispatch( sprintf( 'workflow.%s.entered.%s', $this->name, $place ), $event );
        }
    }

    private function announce( $subject, Transition $initialTransition, Marking $marking ) {
        if ( null === $this->dispatcher ) {
            return;
        }
        $event = new Event( $subject, $marking, $initialTransition );
        foreach ( $this->getEnabledTransitions( $subject ) as $transition ) {
            $this->dispatcher->dispatch( sprintf( 'workflow.%s.announce.%s', $this->name, $transition->getName() ), $event );
        }
    }
}
