/**
 * End-to-end specs, limited to what the unit tests structurally cannot reach:
 * the browser. Everything about generated output is tested in PHPUnit, where it
 * runs in milliseconds and needs no Joomla install.
 *
 * Expects a Joomla site with com_pluggen installed. Configuration comes from
 * the git-ignored .env through cypress.config.js.
 */

const ADMIN = '/administrator/index.php';
const NEW_BLUEPRINT = `${ADMIN}?option=com_pluggen&task=blueprint.add`;

const login = () => {
  cy.session('admin', () => {
    cy.visit(ADMIN);
    cy.get('#mod-login-username').type(Cypress.env('adminUser'));
    cy.get('#mod-login-password').type(Cypress.env('adminPassword'), { log: false });
    cy.get('#btn-login-submit').click();
    cy.get('#content').should('exist');
  });
};

// The type fieldsets are rendered as tabs, so a field can be present, enabled by
// showon, and still not visible because its tab is not the open one.
const openTab = (label) => cy.contains('joomla-tab button', label).click();

describe('Plug-gen blueprint editing', () => {
  beforeEach(login);

  // Every plugin group is listed, but only the ones with a type bundle can be
  // chosen. Showing the rest greyed out says what the generator cannot do yet,
  // instead of pretending those plugin types do not exist.
  it('lists every plugin group and disables the ones without a generator', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_plugin_type option').should('have.length.greaterThan', 20);

    ['finder', 'task', 'workflow'].forEach((group) => {
      cy.get(`#jform_plugin_type option[value="${group}"]`).should('not.be.disabled');
    });

    ['content', 'system', 'user'].forEach((group) => {
      cy.get(`#jform_plugin_type option[value="${group}"]`).should('be.disabled');
    });
  });

  // The one thing only a browser can check: showon actually swaps the
  // type-specific fields when the plugin type changes. Each type contributes a
  // tab of its own, and only the selected type's fields are shown.
  it('shows the fields of the selected type and hides the others', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_plugin_type').select('finder');
    openTab('Finder Settings');
    cy.get('#jform_config_finder_context').should('be.visible');
    cy.get('#jform_config_workflow_contexts').should('not.be.visible');

    openTab('General');
    cy.get('#jform_plugin_type').select('workflow');
    openTab('Workflow Settings');
    cy.get('#jform_config_workflow_contexts').should('be.visible');
    cy.get('#jform_config_finder_context').should('not.be.visible');

    // A subform renders no input of its own, so the label is what to look for.
    openTab('General');
    cy.get('#jform_plugin_type').select('task');
    openTab('Task Settings');
    cy.get('#jform_config_task_routines-lbl').should('be.visible');
    cy.get('#jform_config_workflow_contexts').should('not.be.visible');
  });

  // Slots belong to a type as much as its settings do, so they follow the same
  // rule and get a tab of their own per type.
  it('gives each type its own custom code tab', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_plugin_type').select('finder');
    openTab('Finder: Custom Code');
    cy.get('#jform_slots_finder_finder_index_elements').should('be.visible');

    openTab('General');
    cy.get('#jform_plugin_type').select('task');
    openTab('Task: Custom Code');
    cy.get('#jform_slots_task_task_class_members').should('be.visible');
    cy.get('#jform_slots_finder_finder_index_elements').should('not.be.visible');
  });

  it('saves a blueprint and stores it as a model', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_title').clear().type('Recipes finder');
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_element').clear().type('recipes');
    cy.get('#jform_namespace').clear().type('Acme\\Plugin\\Finder\\Recipes');
    cy.get('#jform_className').clear().type('Recipes');

    openTab('Finder Settings');
    cy.get('#jform_config_finder_context').clear().type('Recipes');
    cy.get('#jform_config_finder_extension').clear().type('com_recipes');
    cy.get('#jform_config_finder_itemName').clear().type('recipe');
    cy.get('#jform_config_finder_table').clear().type('#__recipes');

    cy.get('joomla-toolbar-button[task="blueprint.apply"] button').click();

    cy.get('#system-message-container').should('contain', 'saved');
    cy.get('#jform_element').should('have.value', 'recipes');

    // The blueprint survives a round trip through the stored model: the type
    // and its own fields come back as they were entered.
    cy.get('#jform_plugin_type').should('have.value', 'finder');
    openTab('Finder Settings');
    cy.get('#jform_config_finder_itemName').should('have.value', 'recipe');
  });

  // The list view links to Generate with a GET request, while the edit toolbar
  // posts the form. Both have to pass the token check, and only a real request
  // shows whether they do: this one came back as an HTML error page until the
  // controller stopped looking for the token in POST alone.
  it('generates an installable zip from the list view', () => {
    cy.visit(`${ADMIN}?option=com_pluggen&view=blueprints`);

    cy.get('#blueprintList tbody tr')
      .contains('th a', 'Recipes finder')
      .parents('tr')
      .find('a.btn')
      .invoke('attr', 'href')
      .then((href) => {
        // The link is root-relative; cy.request() would prefix baseUrl onto it.
        const absolute = new URL(href, Cypress.config('baseUrl')).toString();

        cy.request(absolute).then((response) => {
          expect(response.status).to.eq(200);
          expect(response.headers['content-type']).to.contain('application/zip');
          expect(response.headers['content-disposition']).to.contain('plg_finder_recipes.zip');
          // PK: the archive really is an archive.
          expect(response.body.slice(0, 2)).to.eq('PK');
        });
      });
  });

  // An element name that could escape the output directory must be refused
  // before anything is written. This is the path-safety boundary, so it gets an
  // end-to-end check as well as a unit test.
  it('refuses an element name that could escape the output directory', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_title').clear().type('Hostile');
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_element').clear().type('../../evil');
    cy.get('#jform_namespace').clear().type('Acme\\Plugin\\Finder\\Evil');
    cy.get('#jform_className').clear().type('Evil');

    openTab('Finder Settings');
    cy.get('#jform_config_finder_context').clear().type('Evil');
    cy.get('#jform_config_finder_extension').clear().type('com_evil');
    cy.get('#jform_config_finder_itemName').clear().type('evil');
    cy.get('#jform_config_finder_table').clear().type('#__evil');

    cy.get('joomla-toolbar-button[task="blueprint.apply"] button').click();

    // Saving may well succeed - the model is stored, not executed. The gate
    // that matters is generation, which must refuse to write such a plugin.
    cy.get('body').then(($body) => {
      if ($body.find('joomla-toolbar-button[task="blueprint.generate"]').length) {
        cy.get('joomla-toolbar-button[task="blueprint.generate"] button').click();
        cy.get('#system-message-container').should('contain', 'element');
      }
    });
  });
});
