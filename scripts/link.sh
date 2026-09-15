#!/usr/bin bash

# This script links the platform to a local project.
# It should be run from the root of the project
#
# Usage:/path/to/platform/scripts/link


# It adds the current path to the "repositories" configuration in composer.json if it doesn't exist yet.
# It also adds the platform as a requirement in composer.json if it doesn't exist yet.
# Finally, it runs "composer update" to install the platform.

# Get the current path

CURRENT_PATH=$(pwd)
PLATFORM_PATH=$(dirname "$(dirname "$(realpath "$0")")")
PLATFORM_NAME="solidworx/platform"
PLATFORM_VERSION="dev-main"
COMPOSER_FILE="$CURRENT_PATH/composer.json"

# Check if composer.json exists
if [ ! -f "$COMPOSER_FILE" ]; then
    echo "composer.json not found in the current directory."
    exit 1
fi

# Check if the symfony binary is installed
if ! command -v symfony &> /dev/null; then
    echo "symfony could not be found. Please install symfony binary to run this script."
    exit 1
fi

symfony composer config repositories."$PLATFORM_NAME" path "$PLATFORM_PATH"
symfony composer require "$PLATFORM_NAME:$PLATFORM_VERSION" --no-update
symfony composer update "$PLATFORM_NAME"
echo "Platform linked successfully."
