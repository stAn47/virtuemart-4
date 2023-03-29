#!/usr/bin/env bash

# Exit if any command fails
set -eo pipefail

RELEASE_VERSION=$1

FILENAME_PREFIX="Plugin_VirtueMart-4_"
FOLDER_PREFIX="vm_plugin"
RELEASE_FOLDER=".dist"

# If tag is not supplied, latest tag is used
if [ -z "$RELEASE_VERSION" ]
then
  RELEASE_VERSION=$(git describe --tags --abbrev=0)
fi

# Remove old folder
rm -rf "$RELEASE_FOLDER"

# Create release
mkdir "$RELEASE_FOLDER"
git archive --format zip -9 --prefix="$FOLDER_PREFIX"/ --output "$RELEASE_FOLDER"/"$FILENAME_PREFIX""$RELEASE_VERSION".zip "$RELEASE_VERSION"

# Unzip for generating composer autoloader
cd "$RELEASE_FOLDER"
unzip "$FILENAME_PREFIX""$RELEASE_VERSION".zip
rm "$FILENAME_PREFIX""$RELEASE_VERSION".zip

# Change to the extension folder
cd "$FOLDER_PREFIX"

# Install composer without dev dependencies
# composer install --no-dev

# Zip everything excluding some specific files
# zip -9 -r "$FILENAME_PREFIX""$RELEASE_VERSION".zip ./* -x "composer.json" -x "composer.lock" -x "modman"
zip -9 -r "$FILENAME_PREFIX""$RELEASE_VERSION".zip ./*

# Move the zip file to the root of the release folder
mv "$FILENAME_PREFIX""$RELEASE_VERSION".zip ../

# Remove the temporal directory to build the release
cd ../
rm -rf "$FOLDER_PREFIX"
