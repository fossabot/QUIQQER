<?php

/**
 * This file contains \QUI\Interfaces\Template\EngineInterface
 */

namespace QUI\Interfaces\Template;

/**
 * Interface of a template engine
 *
 * @author  www.pcsg.de (Henning Leutz)
 * @package com.pcsg.qui.interface.template
 * @licence For copyright and license information, please view the /README.md
 */
interface EngineInterface
{
    /**
     * Return the complete template
     *
     * @param string $template - path to the template
     *
     * @return string
     */
    public function fetch($template);

    /**
     * Assign a Variable to the engine
     *
     * @param array|string $var
     * @param mixed $value - optional
     */
    public function assign($var, $value = false);

    /**
     * Return the value of a template variabel
     *
     * @param string $variableName
     * @return mixed
     */
    public function getTemplateVariable($variableName);
}
