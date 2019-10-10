#!/usr/bin/env bash

cd ${TRAVIS_BUILD_DIR}

DOCROOT=$1
MODULE_NAME=$(basename ${TRAVIS_BUILD_DIR})
PHPUNIT=$(dirname ${DOCROOT})/$(composer config bin-dir)/phpunit

/usr/local/share/chromedriver --port=4444 &
php ./vendor/bin/grumphp run || exit 1
cd ${DOCROOT}
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
