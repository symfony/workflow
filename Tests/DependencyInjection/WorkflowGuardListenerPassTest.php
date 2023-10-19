<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\DependencyInjection\WorkflowGuardListenerPass;

class WorkflowGuardListenerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private WorkflowGuardListenerPass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new WorkflowGuardListenerPass();
    }

    public function testNoExeptionIfParameterIsNotSet()
    {
        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
    }

    public function testNoExeptionIfAllDependenciesArePresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);
        $this->container->register('validator', ValidatorInterface::class);

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasParameter('workflow.has_guard_listeners'));
    }

    public function testExceptionIfTheTokenStorageServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "security.token_storage" service is needed to be able to use the workflow guard listener.');

        $this->compilerPass->process($this->container);
    }

    public function testExceptionIfTheAuthorizationCheckerServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "security.authorization_checker" service is needed to be able to use the workflow guard listener.');

        $this->compilerPass->process($this->container);
    }

    public function testExceptionIfTheAuthenticationTrustResolverServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.role_hierarchy', RoleHierarchy::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "security.authentication.trust_resolver" service is needed to be able to use the workflow guard listener.');

        $this->compilerPass->process($this->container);
    }

    public function testExceptionIfTheRoleHierarchyServiceIsNotPresent()
    {
        $this->container->setParameter('workflow.has_guard_listeners', true);
        $this->container->register('security.token_storage', TokenStorageInterface::class);
        $this->container->register('security.authorization_checker', AuthorizationCheckerInterface::class);
        $this->container->register('security.authentication.trust_resolver', AuthenticationTrustResolverInterface::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "security.role_hierarchy" service is needed to be able to use the workflow guard listener.');

        $this->compilerPass->process($this->container);
    }
}
