<?php

namespace PerryRylance\WordPressDomForm;

use Exception;
use Illuminate\Support\Arr;
use PerryRylance\DOMDocument;
use PerryRylance\DOMDocument\DOMObject;
use PerryRylance\DOMForm;

abstract class FormPage
{
	private DOMForm $form;

	public function __construct()
	{
		@session_start();

		if(!file_exists($this->getTemplateFilename()))
			throw new Exception("File not found");
		
		$html		= file_get_contents($this->getTemplateFilename());
		$document	= new DOMDocument();

		$document->loadHTML($html, [
			DOMDocument::OPTION_EVALUATE_PHP => true
		]);

		$this->form	= new DOMForm(
			$document->find("form"),
			json_decode( get_option($this->getOptionName()), true ),
			new ErrorHandler()
		);

		$this->addFormActions();
		$this->addFormNonce();
	}

	abstract protected function getTemplateFilename(): string;
	abstract protected function getOptionName(): string;
	abstract protected function onAdminMenu(string $pageTitle, string $menuTitle, string $capability, string $menuSlug, ?int $position = null): void;

	public static function register(
		string $pageTitle,
		string $menuTitle,
		string $capability,
		string $menuSlug,
		?int $position = null
	): void
	{
		$class		= get_called_class();
		$instance	= new $class();

		add_action('admin_menu', fn() => $instance->onAdminMenu($pageTitle, $menuTitle, $capability, $menuSlug, $position));
		add_action("admin_action_" . $instance->getAction(), fn() => $instance->submit());
	}

	// NB: WordPress struggles with \ in the action name, so do this
	private function getAction(): string
	{
		return "submit_" . preg_replace('/\\\\/', '_', get_called_class());
	}

	private function getRejectedDataSessionKey(): string
	{
		return "rejected-" . get_called_class() . "-data";
	}

	private function storeRejectedDataInSession(array $data): void
	{
		$_SESSION[$this->getRejectedDataSessionKey()] = $data;
	}

	private function recallRejectedDataFromSession(): array | null
	{
		if(!isset($_SESSION[$this->getRejectedDataSessionKey()]))
			return null;

		return $_SESSION[$this->getRejectedDataSessionKey()];
	}

	private function forgetRejectedDataFromSession(): void
	{
		unset($_SESSION[$this->getRejectedDataSessionKey()]);
	}

	private function addHiddenInput(string $name, string $value): void
	{
		$document	= $this->form->element->ownerDocument;
		$input		= new DOMObject($document->createElement("input"));
		
		$input->attr([
			"type"	=> "hidden",
			"name"	=> $name,
			"value"	=> $value
		]);
		
		$this->form->element->append($input[0]);
	}

	private function addFormActions(): void
	{
		$this->form->element->setAttribute("action", admin_url('admin.php'));
		$this->addHiddenInput("action", $this->getAction());
	}

	private function addFormNonce(): void
	{
		$this->addHiddenInput("nonce", wp_create_nonce($this->getAction()));
	}

	protected function render(): void
	{
		// NB: admin.php doesn't display anything, so we pass the rejected data through using session, this way it survives the redirect, and we can render it here.
		if($rejectedData = $this->recallRejectedDataFromSession())
		{
			$this->form->submit($rejectedData);
			$this->forgetRejectedDataFromSession();
		}

		echo $this->form->element->html;
	}

	protected function submit(): void
	{
		// TODO: Nonce handling
		// TODO: Support localizing error messages

		$input = Arr::except($_POST, ['action', 'nonce']);
		
		(new DOMObject($this->form->element))->find("input[name='action'], input[name='nonce']")->remove();

		if(!wp_verify_nonce($_POST['nonce'], $this->getAction()))
		{
			throw new Exception("Invalid nonce");
		}
		else if($data = $this->form->submit($input))
		{
			update_option($this->getOptionName(), json_encode($data));
			$this->forgetRejectedDataFromSession();
		}
		else
			$this->storeRejectedDataInSession($input);

		wp_redirect($_SERVER['HTTP_REFERER'] . "#first-error");
	}
}