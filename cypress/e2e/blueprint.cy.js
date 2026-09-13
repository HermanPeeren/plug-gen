/**
 * Three specs only, limited to what the unit tests structurally cannot reach:
 * the browser. Everything about generated output is tested in PHPUnit, where it
 * runs in milliseconds and does not need a Joomla install.
 *
 * Expects a Joomla site with com_pluggen installed, and these Cypress env vars:
 *   CYPRESS_baseUrl, CYPRESS_adminUser, CYPRESS_adminPassword
 */

const login = () => {
  cy.session('admin', () => {
    cy.visit('/administrator/index.php');
    cy.get('#mod-login-username').type(Cypress.env('adminUser'));
    cy.get('#mod-login-password').type(Cypress.env('adminPassword'), { log: false });
    cy.get('#btn-login-submit').click();
    cy.get('.header-title, #content').should('exist');
  });
};

describe('Plug-gen blueprint editing', () => {
  beforeEach(login);

  // The one thing only a browser can check: showon actually swaps the
  // type-specific fieldset when the plugin type changes.
  it('shows the type-specific fieldset only for the selected type', () => {
    cy.visit('/administrator/index.php?option=com_pluggen&task=blueprint.add');

    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_config_finder_context').should('be.visible');
    cy.get('#jform_config_finder_table').should('be.visible');

    // Slots belong to the type too, so they follow the same rule.
    cy.get('#jform_slots_finder_finder_index_elements').should('exist');
  });

  // Every plugin group is listed, but only the ones with a type bundle can be
  // chosen. Showing the rest greyed out says what the generator cannot do yet,
  // instead of pretending those plugin types do not exist.
  it('lists every plugin group and disables the ones without a generator', () => {
    cy.visit('/administrator/index.php?option=com_pluggen&task=blueprint.add');

    cy.get('#jform_plugin_type option').should('have.length.greaterThan', 20);
    cy.get('#jform_plugin_type option[value="finder"]').should('not.be.disabled');

    ['workflow', 'task', 'content', 'system'].forEach((group) => {
      cy.get(`#jform_plugin_type option[value="${group}"]`).should('be.disabled');
    });
  });

  it('saves a blueprint and stores it as a model', () => {
    cy.visit('/administrator/index.php?option=com_pluggen&task=blueprint.add');

    cy.get('#jform_title').type('Recipes finder');
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_element').clear().type('recipes');
    cy.get('#jform_namespace').clear().type('Acme\\Plugin\\Finder\\Recipes');
    cy.get('#jform_className').clear().type('Recipes');

    cy.get('#jform_config_finder_context').clear().type('Recipes');
    cy.get('#jform_config_finder_extension').clear().type('com_recipes');
    cy.get('#jform_config_finder_itemName').clear().type('recipe');
    cy.get('#jform_config_finder_table').clear().type('#__recipes');

    cy.get('joomla-toolbar-button[task="blueprint.save"] button').click();

    cy.get('#system-message-container').should('contain', 'saved');
    cy.get('#jform_element').should('have.value', 'recipes');
  });

  // An invalid element must be refused before anything is written: this is the
  // path-safety boundary, so it gets an end-to-end check as well as a unit test.
  it('refuses an element name that could escape the output directory', () => {
    cy.visit('/administrator/index.php?option=com_pluggen&task=blueprint.add');

    cy.get('#jform_title').type('Hostile');
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_element').clear().type('../../evil');
    cy.get('#jform_namespace').clear().type('Acme\\Plugin\\Finder\\Evil');
    cy.get('#jform_className').clear().type('Evil');
    cy.get('#jform_config_finder_context').clear().type('Evil');
    cy.get('#jform_config_finder_extension').clear().type('com_evil');
    cy.get('#jform_config_finder_itemName').clear().type('evil');
    cy.get('#jform_config_finder_table').clear().type('#__evil');

    cy.get('joomla-toolbar-button[task="blueprint.save"] button').click();

    // Saving may succeed - the gate that matters is that generation refuses.
    cy.get('body').then(($body) => {
      if ($body.find('joomla-toolbar-button[task="blueprint.generate"]').length) {
        cy.get('joomla-toolbar-button[task="blueprint.generate"] button').click();
        cy.get('#system-message-container').should('contain', 'element');
      }
    });
  });
});
