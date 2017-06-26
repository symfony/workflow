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

use Symfony\Component\Workflow\Exception\InvalidArgumentException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Transition {
    private $name;
    private $froms;
    private $tos;
    private $roles;

    /**
     * @param string $name
     * @param string|string[] $froms
     * @param string|string[] $tos
     * @param string|string[] $roles optional
     */
    public function __construct( $name, $froms, $tos, $roles = array () ) {
        if ( ! preg_match( '{^[\w\d_-]+$}', $name ) ) {
            throw new InvalidArgumentException( sprintf( 'The transition "%s" contains invalid characters.', $name ) );
        }
        $this->name  = $name;
        $this->froms = (array) $froms;
        $this->tos   = (array) $tos;
        $this->roles = (array) $roles;
    }

    public function getName() {
        return $this->name;
    }

    public function getFroms() {
        return $this->froms;
    }

    public function getTos() {
        return $this->tos;
    }

    /**
     * @return array
     */
    public function getRoles() {
        return $this->roles;
    }


}
