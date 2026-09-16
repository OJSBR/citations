#!/bin/bash

set -e

npx cypress run  --headless --browser chrome  --config '{"specPattern":["plugins/generic/citations/cypress/tests/functional/*.cy.js"]}'
