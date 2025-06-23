<?php

declare(strict_types=1);

/*
 * This file is part of SolidWorx Platform project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidWorx\Platform\PlatformBundle\Security\TwoFactor;

use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use function array_merge;

final readonly class TwoFactorFormRenderer implements TwoFactorFormRendererInterface
{
    /**
     * @param array<string,mixed> $templateVars
     */
    public function __construct(
        private Environment $twigEnvironment,
        private string $template,
        private array $templateVars = [],
    ) {
    }

    /**
     * @param array<string,mixed> $templateVars
     *
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function renderForm(Request $request, array $templateVars): Response
    {
        $content = $this->twigEnvironment->render($this->template, array_merge($this->templateVars, $templateVars));
        $response = new Response();
        $response->setContent($content);

        return $response;
    }
}
