#!/usr/bin/env bash

DOCROOT=$1
MODULE_NAME=$(basename ${TRAVIS_BUILD_DIR})
PHPUNIT=$(dirname ${DOCROOT})/$(composer config bin-dir)/phpunit
COMPOSER="$(which composer)"
PACKAGE_NAME=$(cat ${TRAVIS_BUILD_DIR}/composer.json|jq '.name'|sed -e 's:"::g')

# Define the color scheme.
FG_C='\033[1;37m'
BG_C='\033[42m'
WBG_C='\033[43m'
EBG_C='\033[41m'
NO_C='\033[0m'

# Link the current module using composer. Otherwise the autoload will not have the necessary classes
# for the Unit tests.
cd ${DOCROOT}/.. || exit 2
echo -e "${FG_C}${BG_C} EXECUTING ${NO_C} ${COMPOSER} require ${PACKAGE_NAME} \"phpunit/phpunit:^6.5\" --no-interaction --no-progress --no-suggest\n\n"
${COMPOSER} require ${PACKAGE_NAME} "phpunit/phpunit:^6.5" --no-interaction --no-progress --no-suggest

# Execute the static code analysis tasks.
cd ${TRAVIS_BUILD_DIR} || exit 2
/usr/local/share/chromedriver --port=4444 &
php ./vendor/bin/grumphp run || exit 1

# Execute all the tests.
cd ${DOCROOT} || exit 2
perl -pe "s:\{DRUPAL_ROOT\}:${DOCROOT}:g" ${DOCROOT}/modules/contrib/${MODULE_NAME}/phpunit.xml.dist > ${DOCROOT}/modules/contrib/${MODULE_NAME}/phpunit.xml
MINK_DRIVER_ARGS_WEBDRIVER='["chrome", {"chrome": {"switches":["headless"]}}, "http://localhost:4444"]' php ${PHPUNIT} \
  --configuration ${DOCROOT}/modules/contrib/${MODULE_NAME}/phpunit.xml \
  --verbose \
  --debug \
  --printer="\Drupal\Tests\Listeners\HtmlOutputPrinter" \
  --stop-on-skipped \
  --fail-on-warning \
  --fail-on-risky \
  ${DOCROOT}/modules/contrib/${MODULE_NAME}/tests
