<?php

namespace PerryRylance\WordPressDomForm;

abstract class OptionsPage extends FormPage
{
	protected function onAdminMenu(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, ?int $position = null): void
	{
		add_options_page(
			$pageTitle, 
			$menuTitle, 
			$capability, 
			$menuSlug, 
			fn() => $this->render(),
			$position
		);
	}
}