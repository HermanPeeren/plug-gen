-- The blueprint's own name. "Title" was Joomla's habit rather than a decision;
-- what the column holds is the name of a blueprint, and the form now says so.
ALTER TABLE `#__pluggen_blueprints` CHANGE `title` `name` varchar(255) NOT NULL DEFAULT '';
