#!/usr/bin/env bash
#
# Creates ./test-app — a throwaway Symfony application wired to this platform checkout.
#
#   scripts/test-app.sh [--force]
#
# The platform is installed as a composer *path* repository, so composer symlinks this
# checkout into test-app/vendor/solidworx/platform: PHP and Twig changes are live.
# test-app lives inside the platform directory, so node resolution walks up into the
# platform's node_modules — the frontend uses the platform's Encore and its live
# `@solidworx/platform` workspace symlink rather than copies of its own.

set -euo pipefail

# Xdebug's step debugger only slows composer down here.
export XDEBUG_MODE=off

PLATFORM_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$PLATFORM_PATH/test-app"
USER_EMAIL="test@example.com"
USER_PASSWORD="password"

if [[ "${1:-}" == "--force" ]]; then
    rm -rf "$APP_DIR"
elif [[ -e "$APP_DIR" ]]; then
    echo "test-app already exists. Re-run with --force to recreate it." >&2
    exit 1
fi

for bin in symfony composer bun; do
    command -v "$bin" >/dev/null || { echo "$bin is required but was not found in PATH." >&2; exit 1; }
done

echo "==> Creating the Symfony skeleton"
symfony new "$APP_DIR" --version=next --no-git
cd "$APP_DIR"
[[ -f "$PLATFORM_PATH/.php-version" ]] && cp "$PLATFORM_PATH/.php-version" .php-version

echo "==> Linking the platform ($PLATFORM_PATH)"
# The platform tracks symfony/*:8.2.x-dev, so the app has to accept dev packages too.
symfony composer config minimum-stability dev
symfony composer config prefer-stable true
symfony composer config extra.symfony.require "8.2.*"
symfony composer config repositories.solidworx/platform path "$PLATFORM_PATH"

# `*@dev`, not dev-main: a path repository takes its version from the branch that happens
# to be checked out. --no-scripts because cache:clear cannot boot until the wiring below
# is in place.
symfony composer require -n -W --no-scripts solidworx/platform:"*@dev" symfony/twig-bundle doctrine/doctrine-bundle
symfony composer require -n --no-scripts --dev symfony/web-profiler-bundle symfony/maker-bundle

echo "==> Writing the platform wiring"

cat > platform.yaml <<'YAML'
# yaml-language-server: $schema=./vendor/solidworx/platform/platform-schema.json
platform:
  name: 'Platform Test App'
  version: '1.0.0'

  security:
    two_factor:
      # Flip to true to test the 2FA flow — the kernel registers SchebTwoFactorBundle itself.
      enabled: false
      base_template: '@Ui/Layout/base.html.twig'

  doctrine:
    types:
      enable_utc_date: true

  models:
    user: App\Entity\User

ui:
  icon_pack: tabler

# The SaaS bundle needs entities of its own (a Subscription implementing SubscribableInterface,
# a trial user entity) so it is left out of config/bundles.php. Add it back there together with
# a `saas:` section here to test subscriptions — see docs/configuration/index.md.
YAML

cat > src/Kernel.php <<'PHP'
<?php

declare(strict_types=1);

namespace App;

use SolidWorx\Platform\PlatformBundle\Kernel as PlatformKernel;

final class Kernel extends PlatformKernel
{
}
PHP

cat > src/Entity/User.php <<'PHP'
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use SolidWorx\Platform\PlatformBundle\Model\User as PlatformUser;
use SolidWorx\Platform\PlatformBundle\Repository\UserRepository;

// The repository is what the platform_user provider loads users through: it implements
// UserLoaderInterface, so leaving it off breaks login with a Doctrine provider error.
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User extends PlatformUser
{
}
PHP

cat > src/Controller/HomeController.php <<'PHP'
<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
PHP

mkdir -p src/Menu
cat > src/Menu/SidebarMenu.php <<'PHP'
<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\ItemInterface;
use SolidWorx\Platform\PlatformBundle\Attributes\Menu\MenuBuilder;
use SolidWorx\Platform\PlatformBundle\Menu\Options;
use SolidWorx\Platform\PlatformBundle\Menu\UserMenu;

final class SidebarMenu
{
    #[MenuBuilder(name: 'sidebar')]
    public function sidebar(ItemInterface $menu): void
    {
        $menu->addChild('Dashboard', Options::create()->route('app_home')->icon('home')->build());
    }

    #[MenuBuilder(name: UserMenu::NAME)]
    public function userMenu(ItemInterface $menu): void
    {
        $menu->addChild('Home', Options::create()->route('app_home')->icon('home')->build());
    }
}
PHP

mkdir -p src/Command
cat > src/Command/CreateUserCommand.php <<'PHP'
<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Creates a user to log in with')]
final readonly class CreateUserCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The email address')] string $email = 'test@example.com',
        #[Argument('The plain-text password')] string $password = 'password',
    ): int {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setEnabled(true);
        $user->setVerified(true);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Created %s / %s', $email, $password));

        return Command::SUCCESS;
    }
}
PHP

mkdir -p templates/home
cat > templates/home/index.html.twig <<'TWIG'
{% extends ui_layout_app %}

{% block page_pretitle %}Test app{% endblock %}
{% block page_title %}Dashboard{% endblock %}

{% block content %}
    <twig:Ui:Card title="It works">
        <p>This app runs against the platform checkout it lives in — edit the platform's PHP or
            Twig and reload the page.</p>
    </twig:Ui:Card>
{% endblock %}
TWIG

# The platform builds the entire security config, and registers SchebTwoFactorBundle itself
# from platform.yaml — the recipes' versions of both would fight with it.
rm -f config/packages/security.yaml config/packages/scheb_2fa.yaml
cat > config/packages/security.php <<'PHP'
<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SolidWorx\Platform\PlatformBundle\DependencyInjection\Extension\LoginExtension;

return App::config(LoginExtension::defaultFormLoginConfig([
    'access_control' => [
        ['path' => '^/(_(profiler|wdt)|static)/', 'roles' => ['PUBLIC_ACCESS']],
        ['path' => '^/login', 'roles' => ['PUBLIC_ACCESS']],
        ['path' => '^/', 'roles' => ['ROLE_USER']],
    ],
]));
PHP

# The platform's Encore config builds into public/static, not public/build.
cat > config/packages/webpack_encore.yaml <<'YAML'
webpack_encore:
    output_path: '%kernel.project_dir%/public/static'
YAML

php -r '
$file = "config/bundles.php";
$bundles = file_get_contents($file);

// The kernel registers SchebTwoFactorBundle when platform.yaml enables 2FA, and the SaaS
// bundle has required config of its own — neither belongs here by default.
$bundles = preg_replace("/^.*(SchebTwoFactorBundle|SolidWorxPlatformSaasBundle).*\n/m", "", $bundles);

if (!str_contains($bundles, "SolidWorxPlatformUiBundle")) {
    $bundles = preg_replace("/\];\s*$/", "    SolidWorx\\Platform\\UiBundle\\SolidWorxPlatformUiBundle::class => [\"all\" => true],\n];\n", $bundles);
}

file_put_contents($file, $bundles);'

echo "==> Configuring the database (SQLite)"
cat >> .env.local <<'ENV'
DATABASE_URL="sqlite:///%kernel.project_dir%/var/app.db"
ENV

echo "==> Writing the frontend config"
cat > webpack.config.js <<'JS'
// The platform ships the whole Encore setup: the `_platform_ui` entry (Tabler, the Stimulus
// app, the platform controllers), Sass, PostCSS, TypeScript, ESLint and jQuery. Chain your
// own entries onto it — see docs/frontend/index.md.
import Encore from '@solidworx/platform/webpack.config.js';

export default Encore
    .enableStimulusBridge('./assets/controllers.json')
    .getWebpackConfig();
JS

cat > eslint.config.js <<'JS'
// The platform's Encore config runs ESLint over every build; reuse the platform's rules.
export { default } from '../eslint.config.js';
JS

# The skeleton's own entry starts a second Stimulus application, which the platform already
# starts in `_platform_ui`. Nothing here is built, so the files only invite that mistake.
rm -rf assets/app.js assets/stimulus_bootstrap.js assets/styles assets/controllers/hello_controller.js assets/controllers/csrf_protection_controller.js
mkdir -p assets/controllers

php -r '
$file = "package.json";
$pkg = json_decode(file_get_contents($file), true);

// Everything the build needs already sits in the platform node_modules above this directory,
// including the live `@solidworx/platform` symlink. A `file:` dependency is *copied*, so
// keeping these here would shadow the checkout with a stale copy and a second Encore.
foreach (["@solidworx/platform", "@symfony/webpack-encore", "webpack", "webpack-cli"] as $dep) {
    unset($pkg["devDependencies"][$dep], $pkg["dependencies"][$dep]);
}

$pkg["type"] = "module";
$pkg["scripts"] = [
    "dev" => "../node_modules/.bin/encore dev",
    "watch" => "../node_modules/.bin/encore dev --watch",
    "build" => "../node_modules/.bin/encore production",
];

file_put_contents($file, json_encode($pkg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");'

if [[ ! -d "$PLATFORM_PATH/node_modules" ]]; then
    echo "==> Installing the platform's node modules"
    (cd "$PLATFORM_PATH" && bun install)
fi

echo "==> Creating the database"
symfony console cache:clear
symfony console doctrine:schema:create
symfony console app:create-user "$USER_EMAIL" "$USER_PASSWORD"

echo "==> Building assets"
bun install
assets_built=yes
"$PLATFORM_PATH/node_modules/.bin/encore" dev || assets_built=no

cat <<EOF

Done — the app is in $APP_DIR

    cd test-app
    symfony server:start -d && symfony open:local

Log in with $USER_EMAIL / $USER_PASSWORD
Rebuild the frontend after platform asset changes with: bun run watch
EOF

if [[ "$assets_built" == "no" ]]; then
    cat >&2 <<EOF

The asset build failed, so every page that renders the platform UI will error until it
passes. The platform's own build uses the same pipeline — check it with:

    (cd "$PLATFORM_PATH/assets" && bun run dev)

EOF
fi
