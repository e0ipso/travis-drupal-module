#!/usr/bin/env bash
# Move up 3 levels since we are in <module>/.travis/scripts.
BASE_DIR="$(dirname $(dirname $(cd ${0%/*} && pwd)))"

COMPOSER="$(which composer)"
COMPOSER_BIN_DIR="$(composer config bin-dir)"
DOCROOT="web"
MODULE_NAME=$(basename ${TRAVIS_BUILD_DIR})
PACKAGE_NAME=$(cat ${TRAVIS_BUILD_DIR}/composer.json|jq '.name'|sed -e 's:"::g')

# Define the color scheme.
FG_C='\033[1;37m'
BG_C='\033[42m'
WBG_C='\033[43m'
EBG_C='\033[41m'
NO_C='\033[0m'

echo -e "\n"
if [ $1 ] ; then
  DEST_DIR="$1"
  echo $1
else
  DEST_DIR="$( dirname $BASE_DIR )/drupal"
  echo -e "${FG_C}${WBG_C} WARNING ${NO_C} No installation path provided.\nDrupal will be installed in $DEST_DIR."
  echo -e "${FG_C}${BG_C} USAGE ${NO_C} ${0} [install_path] # to install in a different directory."
fi

echo -e "\n\n\n"
echo -e "\t********************************"
echo -e "\t*   Installing Dependencies    *"
echo -e "\t********************************"
echo -e "\n\n\n"
echo -e "${FG_C}${BG_C} EXECUTING ${NO_C} ${COMPOSER} install\n\n"
${COMPOSER} install

echo -e "\n\n\n"
echo -e "\t********************************"
echo -e "\t*      Installing Drupal       *"
echo -e "\t********************************"
echo -e "\n\n\n"
echo -e "Installing to: ${DEST_DIR}\n"

if [ -d "${DEST_DIR}" ]; then
  echo -e "${FG_C}${WBG_C} WARNING ${NO_C} You are about to delete ${DEST_DIR} to install Drupal in that location."
  rm -Rf ${DEST_DIR}
fi

# update composer
${COMPOSER} self-update

echo "-----------------------------------------------"
echo " Downloading Drupal using composer "
echo "-----------------------------------------------"
echo -e "${FG_C}${BG_C} EXECUTING ${NO_C} ${COMPOSER} create-project drupal-composer/drupal-project:8.x-dev ${DEST_DIR} --stability dev --no-interaction --no-install\n\n"
${COMPOSER} create-project drupal-composer/drupal-project:8.x-dev ${DEST_DIR} --stability dev --no-interaction --no-install

if [ $? -ne 0 ]; then
  echo -e "${FG_C}${EBG_C} ERROR ${NO_C} There was a problem setting up Drupal using composer."
  echo "Please check your composer configuration and try again."
  exit 2
fi

cd ${DEST_DIR}
# Link the current module using composer. Otherwise the autoload will not have the necessary classes
# for the Unit tests.
echo -e "${FG_C}${BG_C} EXECUTING ${NO_C} ${COMPOSER} config repositories.${MODULE_NAME} path ${TRAVIS_BUILD_DIR}\n\n"
${COMPOSER} config repositories.${MODULE_NAME} path ${TRAVIS_BUILD_DIR}
cat ${DEST_DIR}/composer.json|jq ".repositories.${MODULE_NAME}"

# Link the module directory into a location Drupal can find it.
echo -e "${FG_C}${BG_C} EXECUTING ${NO_C} mkdir -p ${DEST_DIR}/${DOCROOT}/modules/contrib\n\n"
mkdir -p ${DEST_DIR}/${DOCROOT}/modules/contrib
mkdir -p ${DEST_DIR}/${DOCROOT}/sites/simpletest/browser_output

# This module depends on others. Since we are installing this manually (no composer) we need to pull
# in the dependencies. They are in the vendor directory.
for package in $(cat ${TRAVIS_BUILD_DIR}/composer.json|jq '.require'|grep ':'|sed -e 's:^[^\"]*\"::g' -e 's:\".*::g');
do
  # This will link all of the dependencies, not just the Drupal modules. It MAY be OK though.
  echo -e "${FG_C}${BG_C} EXECUTING ${NO_C} ln -s ${TRAVIS_BUILD_DIR}/vendor/${package} ${DEST_DIR}/${DOCROOT}/modules/contrib/$( basename ${package} )\n\n"
  ln -s ${TRAVIS_BUILD_DIR}/vendor/${package} ${DEST_DIR}/${DOCROOT}/modules/contrib/$( basename ${package} )
done
