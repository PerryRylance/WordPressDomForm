<?php

namespace PerryRylance\WordPressDomForm;

use PerryRylance\DOMDocument\DOMElement;
use PerryRylance\DOMForm\Exceptions\Handlers\DisplayHtml;
use PerryRylance\DOMForm\Exceptions\Population\PopulationException;

class ErrorHandler extends DisplayHtml
{
	protected function createElement(PopulationException $exception): DOMElement
	{
		$span = Parent::createElement($exception);
		$span->setAttribute('class', 'notice notice-error');
		return $span;
	}
}