#!/bin/bash
set -e
case "$1" in
  "web")
    echo "Starting web application"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/apache.conf
    ;;
  "horizon")
    echo "Starting Horizon"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/horizon.conf
    ;;
  *)
    echo "Starting web application"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/apache.conf
    ;;
esac
