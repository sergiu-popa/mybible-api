#!/bin/bash
set -e
case "$1" in
  "web")
    echo "Starting web application"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/apache.conf
    ;;
  "worker")
    echo "Starting queue worker"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/worker.conf
    ;;
  *)
    echo "Starting web application"
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/apache.conf
    ;;
esac
