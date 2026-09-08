const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.CYPRESS_baseUrl || 'http://localhost/joomla6',
    supportFile: false,
    video: false,
    defaultCommandTimeout: 8000,
  },
  env: {
    adminUser: process.env.CYPRESS_adminUser || 'admin',
    adminPassword: process.env.CYPRESS_adminPassword || '',
  },
});
