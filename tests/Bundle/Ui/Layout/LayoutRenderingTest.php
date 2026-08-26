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

namespace SolidWorx\Platform\Tests\Bundle\Ui\Layout;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Environment;

/**
 * Renders the layouts shipped by the UI bundle and asserts the Tabler structure they produce, which
 * is a public contract consumed by downstream apps.
 */
#[CoversNothing]
final class LayoutRenderingTest extends KernelTestCase
{
    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->session = new Session(new MockArraySessionStorage());

        $request = Request::create('/dashboard');
        $request->setSession($this->session);

        $this->requestStack()->push($request);
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        // Booting the kernel in debug mode registers a Symfony exception handler that it does not
        // remove; restore it so PHPUnit does not flag the test as risky.
        restore_exception_handler();
    }

    public function testAppLayoutRendersASidebarAndATopNavbar(): void
    {
        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">', $html);
        self::assertStringContainsString('<header class="navbar navbar-expand-md d-none d-lg-flex d-print-none">', $html);
        self::assertStringContainsString('<div class="page-wrapper">', $html);
        self::assertStringContainsString('<div class="page-body">', $html);
    }

    public function testAppLayoutRendersThePageHeaderFromBlocks(): void
    {
        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('<div class="page-pretitle">Overview</div>', $html);
        self::assertStringContainsString('<h2 class="page-title">Dashboard</h2>', $html);
        self::assertStringContainsString('<a href="/reports/new" class="btn btn-primary">Create report</a>', $html);
        self::assertStringContainsString('<div class="btn-list">', $html);
    }

    public function testThePageTitleBlockAlsoDrivesTheDocumentTitle(): void
    {
        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('<title>Dashboard · Acme Platform</title>', $html);
    }

    public function testDocumentTitleFallsBackToTheApplicationName(): void
    {
        $html = $this->render('@LayoutTest/no_title.html.twig');

        self::assertStringContainsString('<title>Acme Platform</title>', $html);
    }

    public function testAppLayoutRendersTheSidebarMenu(): void
    {
        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('<ul class="navbar-nav pt-lg-3">', $html);
        self::assertStringContainsString('href="/dashboard"', $html);
        self::assertStringContainsString('<span class="nav-link-title">', $html);
    }

    public function testAppLayoutRendersAFooterAndTheAssets(): void
    {
        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('<footer class="footer footer-transparent d-print-none">', $html);
        self::assertStringContainsString('Acme Platform', $html);
        self::assertStringContainsString('/build/platform.css', $html);
        self::assertStringContainsString('/build/platform.js', $html);
    }

    public function testLayoutOptionsSetByATemplateAreApplied(): void
    {
        $html = $this->render('@LayoutTest/app_customised.html.twig');

        self::assertStringContainsString('class="layout-fluid"', $html);
        self::assertStringContainsString('<div class="container-fluid">', $html);
        self::assertStringContainsString('sticky-top', $html);
        self::assertStringContainsString('<header class="navbar navbar-expand-md sticky-top d-none d-lg-flex d-print-none" data-bs-theme="dark">', $html);
        self::assertStringContainsString('navbar-end', $html);
        self::assertStringContainsString('navbar-transparent', $html);
        self::assertStringNotContainsString('<footer', $html);
    }

    public function testBrandBlocksAreOverridableFromThePage(): void
    {
        $html = $this->render('@LayoutTest/app_customised.html.twig');

        self::assertStringContainsString('<a href="/dashboard" aria-label="Acme Platform">', $html);
        self::assertStringContainsString('<img src="/logo.svg" alt="" class="navbar-brand-image" />', $html);
    }

    public function testNavbarBlocksAreOverridableFromThePage(): void
    {
        $html = $this->render('@LayoutTest/app_customised.html.twig');

        self::assertStringContainsString('id="navbar-search"', $html);
    }

    public function testDroppingTheNavbarLeavesTheSidebarOnly(): void
    {
        $html = $this->render('@LayoutTest/app_no_navbar.html.twig');

        self::assertStringContainsString('<aside class="navbar navbar-vertical', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertStringContainsString('class="layout-fluid"', $html);
    }

    public function testCondensedLayoutRendersAStandaloneNavbarWithoutASidebar(): void
    {
        $html = $this->render('@LayoutTest/condensed_page.html.twig');

        self::assertStringNotContainsString('<aside', $html);
        self::assertStringContainsString('<header class="navbar navbar-expand-md d-print-none">', $html);
        self::assertStringContainsString('class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3"', $html);
        self::assertStringContainsString('data-bs-target="#navbar-menu"', $html);
        self::assertStringContainsString('<div class="page-wrapper">', $html);
        self::assertStringContainsString('<p id="condensed-content">Condensed layout content</p>', $html);
    }

    public function testCondensedLayoutRendersTheNavbarMenuHorizontally(): void
    {
        $html = $this->render('@LayoutTest/condensed_page.html.twig');

        self::assertStringContainsString('<ul class="navbar-nav">', $html);
        self::assertStringNotContainsString('pt-lg-3', $html);
        self::assertStringContainsString('data-bs-auto-close="outside"', $html);
    }

    public function testCleanLayoutHasNoNavigation(): void
    {
        $html = $this->render('@LayoutTest/clean_page.html.twig');

        self::assertStringNotContainsString('<aside', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertStringContainsString('<div class="page-wrapper">', $html);
        self::assertStringContainsString('<h2 class="page-title">Not found</h2>', $html);
        self::assertStringContainsString('<p id="clean-content">Clean layout content</p>', $html);
    }

    public function testSecurityLayoutCentresTheContent(): void
    {
        $html = $this->render('@LayoutTest/security_page.html.twig');

        self::assertStringContainsString('<div class="page page-center">', $html);
        self::assertStringContainsString('<div class="container container-tight py-4">', $html);
        self::assertStringContainsString('<div class="navbar-brand navbar-brand-autodark">', $html);
        self::assertStringNotContainsString('page-wrapper', $html);
        self::assertStringContainsString('id="security-content"', $html);
    }

    public function testFlashMessagesAreRenderedByEveryLayout(): void
    {
        $this->session->getFlashBag()->add('error', 'Something went wrong');
        $this->session->getFlashBag()->add('success', 'Saved');

        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('class="alert alert-danger alert-dismissible no-print"', $html);
        self::assertStringContainsString('Something went wrong', $html);
        self::assertStringContainsString('class="alert alert-success alert-dismissible no-print"', $html);
        self::assertStringContainsString('Saved', $html);
    }

    public function testTheUserMenuIsOmittedForAnonymousVisitors(): void
    {
        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringNotContainsString('aria-label="Open user menu"', $html);
    }

    public function testTheUserMenuIsRenderedForAuthenticatedUsers(): void
    {
        $this->authenticate();

        $html = $this->render('@LayoutTest/app_page.html.twig');

        self::assertStringContainsString('aria-label="Open user menu"', $html);
        self::assertStringContainsString('<span class="avatar avatar-sm">US</span>', $html);
        self::assertStringContainsString(LayoutTestKernel::USERNAME, $html);
        self::assertStringContainsString('action="/logout"', $html);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new LayoutTestKernel('test', true);
    }

    private function authenticate(): void
    {
        $user = new InMemoryUser(LayoutTestKernel::USERNAME, 'password', ['ROLE_USER']);

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = self::getContainer()->get('security.token_storage');
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
    }

    private function render(string $template): string
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        return $twig->render($template);
    }

    private function requestStack(): RequestStack
    {
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');

        return $requestStack;
    }
}
