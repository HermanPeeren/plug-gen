/**
 * End-to-end specs, limited to what the unit tests structurally cannot reach:
 * the browser. Everything about generated output is tested in PHPUnit, where it
 * runs in milliseconds and needs no Joomla install.
 *
 * Expects a Joomla site with com_pluggen installed. Configuration comes from
 * the git-ignored .env through cypress.config.js.
 */

// Every blueprint these specs create carries this marker in its name, so the
// cleanup can delete exactly what the suite made and nothing else.
const MARKER = '[cypress] ';

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

// There is one Type Settings tab and one Custom Code tab for every plugin type,
// so a field can be present, enabled by showon, and still not visible because
// its tab is not the open one.
const openTab = (label) => cy.contains('joomla-tab button', label).click();

describe('Plug-gen blueprint editing', () => {
  before(() => cy.exec(`php build/test-cleanup.php "${MARKER}"`));
  after(() => cy.exec(`php build/test-cleanup.php "${MARKER}"`));

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
  // type-specific block when the plugin type changes. Every type puts its block
  // on the same tab, and only the selected type's block is shown - which is the
  // whole point of one tab rather than one tab per type.
  it('shows the fields of the selected type and hides the others', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_plugin_type').select('finder');
    openTab('Type Settings');
    cy.get('#jform_config_finder__extension').should('be.visible');
    cy.get('#jform_config_workflow__contexts').should('not.be.visible');

    openTab('General');
    cy.get('#jform_plugin_type').select('workflow');
    openTab('Type Settings');
    cy.get('#jform_config_workflow__contexts').should('be.visible');
    cy.get('#jform_config_finder__extension').should('not.be.visible');

    // A subform renders no input of its own, so the label is what to look for.
    openTab('General');
    cy.get('#jform_plugin_type').select('task');
    openTab('Type Settings');
    cy.get('#jform_config_task__routines-lbl').should('be.visible');
    cy.get('#jform_config_workflow__contexts').should('not.be.visible');
  });

  // Slots follow the same rule as the settings: one shared tab, one block per
  // type, shown by showon.
  it('shows the custom code of the selected type only', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_plugin_type').select('finder');
    openTab('Custom Code');
    cy.get('#jform_slots_finder__finder_index_elements').should('be.visible');

    openTab('General');
    cy.get('#jform_plugin_type').select('task');
    openTab('Custom Code');
    cy.get('#jform_slots_task__task_class_members').should('be.visible');
    cy.get('#jform_slots_finder__finder_index_elements').should('not.be.visible');
  });

  it('saves a blueprint and stores it as a model', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_name').clear().type(`${MARKER}Recipes finder`);
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_org_namespace').clear().type('Acme');

    openTab('Type Settings');
    cy.get('#jform_config_finder__extension').clear().type('com_recipes');
    cy.get('#jform_config_finder__itemName').clear().type('recipe');
    cy.get('#jform_config_finder__table').clear().type('#__recipes');

    cy.get('joomla-toolbar-button[task="blueprint.apply"] button').click();

    cy.get('#system-message-container').should('contain', 'saved');
    cy.get('#jform_name').should('have.value', `${MARKER}Recipes finder`);

    // The blueprint survives a round trip through the stored model: the type
    // and its own fields come back as they were entered.
    cy.get('#jform_plugin_type').should('have.value', 'finder');
    openTab('Type Settings');
    cy.get('#jform_config_finder__itemName').should('have.value', 'recipe');
  });

  // A repeatable subform posts rows under keys the layout invents, so the only
  // way to know the mapper reads them is to let a browser post one. The row has
  // to survive the trip out to the stored model and back into the form.
  it('keeps a freely declared service through a save', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_name').clear().type(`${MARKER}Service blueprint`);
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_org_namespace').clear().type('Acme');

    // The custom element carries the field's name, not an id.
    cy.get('joomla-field-subform[name="jform[custom_services]"] .group-add').first().click();

    cy.get('input[name^="jform[custom_services]"][name$="[name]"]')
      .first().clear().type('cacheControllerFactory');
    cy.get('input[name^="jform[custom_services]"][name$="[use]"]')
      .first().clear().type('Joomla\CMS\Cache\CacheControllerFactoryInterface');
    cy.get('input[name^="jform[custom_services]"][name$="[expression]"]')
      .first().clear().type('$container->get(CacheControllerFactoryInterface::class)');

    cy.get('joomla-toolbar-button[task="blueprint.apply"] button').click();

    cy.get('#system-message-container').should('contain', 'saved');
    cy.get('input[name^="jform[custom_services]"][name$="[name]"]')
      .first().should('have.value', 'cacheControllerFactory');
  });

  // The list view links to Generate with a GET request, while the edit toolbar
  // posts the form. Both have to pass the token check, and only a real request
  // shows whether they do: this one came back as an HTML error page until the
  // controller stopped looking for the token in POST alone.
  it('generates an installable zip from the list view', () => {
    cy.visit(`${ADMIN}?option=com_pluggen&view=blueprints`);

    cy.get('#blueprintList tbody tr')
      .contains('th a', `${MARKER}Recipes finder`)
      .parents('tr')
      .find('a.btn')
      .invoke('attr', 'href')
      .then((href) => {
        // The link is root-relative; cy.request() would prefix baseUrl onto it.
        const absolute = new URL(href, Cypress.config('baseUrl')).toString();

        cy.request(absolute).then((response) => {
          expect(response.status).to.eq(200);
          expect(response.headers['content-type']).to.contain('application/zip');
          // The name carries the test marker, and the element is derived from
          // the whole name - so "[cypress] Recipes finder" really does generate
          // plg_finder_cypressrecipesfinder. That is the derivation working,
          // not the test cheating. The version is the form's default.
          expect(response.headers['content-disposition'])
            .to.contain('plg_finder_cypressrecipesfinder-1.0.0.zip');
          // PK: the archive really is an archive.
          expect(response.body.slice(0, 2)).to.eq('PK');
        });
      });
  });

  // Path separators in the name never reach a path. They cannot: the element is
  // derived from the name by keeping letters and digits and dropping the rest,
  // so there is no spelling of a name that escapes the output directory. This
  // used to be a rule the validator enforced on a field the user typed
  // directly; now it is a property of the derivation, and this is the spec that
  // says so out loud.
  it('cannot be made to write outside the output directory', () => {
    cy.visit(NEW_BLUEPRINT);

    cy.get('#jform_name').clear().type(`${MARKER}../../Evil`);
    cy.get('#jform_plugin_type').select('finder');
    cy.get('#jform_org_namespace').clear().type('Acme');

    openTab('Type Settings');
    cy.get('#jform_config_finder__extension').clear().type('com_evil');
    cy.get('#jform_config_finder__itemName').clear().type('evil');
    cy.get('#jform_config_finder__table').clear().type('#__evil');

    cy.get('joomla-toolbar-button[task="blueprint.apply"] button').click();
    cy.get('#system-message-container').should('contain', 'saved');

    cy.visit(`${ADMIN}?option=com_pluggen&view=blueprints`);

    cy.get('#blueprintList tbody tr')
      .contains('th a', `${MARKER}../../Evil`)
      .parents('tr')
      .find('a.btn')
      .invoke('attr', 'href')
      .then((href) => {
        const absolute = new URL(href, Cypress.config('baseUrl')).toString();

        cy.request(absolute).then((response) => {
          const disposition = response.headers['content-disposition'];

          expect(disposition).to.contain('plg_finder_cypressevil-1.0.0.zip');
          expect(disposition).to.not.contain('..');
          expect(disposition).to.not.contain('/');
        });
      });
  });
});
