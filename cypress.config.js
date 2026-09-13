require('dotenv').config();

const { defineConfig } = require('cypress');

// The site URL and the Super User credentials live in the git-ignored .env,
// next to the ones the install script uses, so there is one place to change
// them and nothing to commit by accident.
module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.CYPRESS_baseUrl || process.env.SITE_URL || 'http://localhost/pluggen-test/joomla',
    supportFile: false,
    video: false,
    screenshotOnRunFailure: false,
    defaultCommandTimeout: 8000,
  },
  env: {
    adminUser: process.env.CYPRESS_adminUser || process.env.ADMIN_USER || 'admin',
    adminPassword: process.env.CYPRESS_adminPassword || process.env.ADMIN_PASSWORD || '',
  },
});
