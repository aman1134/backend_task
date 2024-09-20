#!/bin/bash

php bin/console doctrine:schema:update --force

php bin/console messenger:consume async --time-limit=3600