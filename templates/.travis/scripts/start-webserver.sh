#!/usr/bin/env bash
echo -e "\n"
if [ $1 ] ; then
  DRUPAL_ROOT="$1"
  echo $1
else
  # Move up 3 levels since we are in <module>/.travis/scripts.
  BASE_DIR="$(dirname $(dirname $(cd ${0%/*} && pwd)))"
  DRUPAL_ROOT="$( dirname $BASE_DIR )/drupal/web"
  echo -e "WARNING: No installation path provided.\nDrupal will be installed in $DRUPAL_ROOT."
  echo -e "USAGE: ${0} [install_path] # to install in a different directory."
fi

echo "-------------------------------------"
echo " Initializing local PHP server "
echo "-------------------------------------"
echo -e "INFO: Server started.\n"
SIMPLETEST_BASE_HOST=$(echo $SIMPLETEST_BASE_URL|sed -s 's:^http\://::g')
echo "Starting the test server at ${SIMPLETEST_BASE_HOST}"
echo "EXECUTING: php -S $SIMPLETEST_BASE_HOST -t ${DRUPAL_ROOT} &\n\n"
php -S $SIMPLETEST_BASE_HOST -t ${DRUPAL_ROOT} &
until curl -sS $SIMPLETEST_BASE_URL; do sleep 2; done >> /dev/null
