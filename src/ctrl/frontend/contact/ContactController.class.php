<?php
namespace ctrl\frontend\contact;

use core\BackController;
use core\http\HTTPRequest;
use lib\Captcha;
use lib\entities\ContactMessage;
use \InvalidArgumentException;

class ContactController extends BackController {
	public function executeIndex(HTTPRequest $request) {
		$this->page()->addVar('title', 'Contact');

		$captcha = Captcha::build($this->app());
		$this->page()->addVar('captcha', $captcha);

		if ($request->postExists('message-sender-name')) {
			$messageData = array(
				'senderName' => trim($request->postData('message-sender-name')),
				'senderEmail' => $request->postData('message-sender-email'),
				'subject' => trim($request->postData('message-subject')),
				'content' => trim($request->postData('message-content'))
			);

			$this->page()->addVar('message', $messageData);

			try {
				$message = new ContactMessage($messageData);
			} catch(InvalidArgumentException $e) {
				$this->page()->addVar('error', $e->getMessage());
				return;
			}

			$captchaId = (int) $request->postData('captcha-id');
			$captchaValue = $request->postData('captcha-value');

			try {
				Captcha::check($this->app(), $captchaId, $captchaValue);
			} catch (\Exception $e) {
				$this->page()->addVar('error', $e->getMessage());
				return;
			}

			$contactConfig = $this->config->read();

			$messageDest = $contactConfig['email'];
			$messageSubject = $contactConfig['subjectPrepend'].' '.$message['subject'];
			$messageContent = 'Nom : '.$message['senderName'].' <'.$message['senderEmail'].'>'."\n";
			$messageContent .= 'Sujet : '.$message['subject']."\n";
			$messageContent .= 'Message :'."\n".$message['content'];

			$messageHeaders = 'From: '.$message['senderEmail']."\r\n".
			'Reply-To: '.$message['senderEmail']."\r\n" .
			'X-Mailer: PHP/' . phpversion();

			if (mail($messageDest, $messageSubject, $messageContent, $messageHeaders) !== false) {
				$this->page()->addVar('messageSent?', true);
			} else {
				$this->page()->addVar('error', 'Cannot send message: server error');
			}
		}
	}
}