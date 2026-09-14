/**
 * Show or hide the field descriptions on the blueprint form.
 *
 * The starting state is decided by the component options and rendered as a
 * class on the form, so this file only ever flips it. Joomla core has the same
 * button, but core toggles a class its own field layout puts on each
 * description; that class never reaches a subform's children, and most of this
 * form is subforms.
 *
 * aria-describedby is kept in step with what is visible, as core does: a
 * description that is display:none is not something a control should still
 * claim to be described by.
 */
((document) => {
	'use strict';

	const HIDDEN = 'pluggen-descriptions-hidden';

	const onReady = () => {
		const form = document.querySelector('form[data-pluggen-descriptions]');
		const button = document.querySelector('.button-descriptions');

		if (!form || !button) {
			return;
		}

		const descriptions = form.querySelectorAll('div[id$="-desc"]');

		// Nothing to show or hide: say so by removing the button rather than
		// leaving one that does nothing when pressed.
		if (descriptions.length === 0) {
			button.closest('joomla-toolbar-button')?.remove();

			return;
		}

		const apply = (hidden) => {
			descriptions.forEach((description) => {
				const control = document.getElementById(description.id.slice(0, -5));

				if (!control) {
					return;
				}

				if (hidden) {
					control.removeAttribute('aria-describedby');
				} else {
					control.setAttribute('aria-describedby', description.id);
				}
			});

			button.setAttribute('aria-pressed', hidden ? 'false' : 'true');
		};

		apply(form.classList.contains(HIDDEN));

		button.addEventListener('click', (event) => {
			event.preventDefault();
			apply(form.classList.toggle(HIDDEN));
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})(document);
