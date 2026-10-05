<?php
/**
 * SimpleEmailServiceMessage PHP class
 *
 * @link   
 * @package AmazonSimpleEmailService
 * @version v0.9.1
 */
// final class SimpleEmailServiceMessage { // name changed

class emailAws {

    // these are public for convenience only
    // these are not to be used outside of the SimpleEmailService class!
    public $to, $cc, $bcc, $replyto, $recipientsCharset;
    public $from, $returnpath;
    public $subject, $messagetext, $messagehtml;
    public $subjectCharset, $messageTextCharset, $messageHtmlCharset;
    public $attachments, $customHeaders, $configuration_set, $message_tags;
    public $is_clean, $raw_message;

    public function __construct() {
        $this->to = array();
        $this->cc = array();
        $this->bcc = array();
        $this->replyto = array();
        $this->recipientsCharset = 'UTF-8';

        $this->from = null;
        $this->returnpath = null;

        $this->subject = null;
        $this->messagetext = null;
        $this->messagehtml = null;

        $this->subjectCharset = 'UTF-8';
        $this->messageTextCharset = 'UTF-8';
        $this->messageHtmlCharset = 'UTF-8';

        $this->attachments = array();
        $this->customHeaders = array();
        $this->configuration_set = null;
        $this->message_tags = array();

        $this->is_clean = true;
        $this->raw_message = null;
    }

    /**
     * addTo, addCC, addBCC, and addReplyTo have the following behavior:
     * If a single address is passed, it is appended to the current list of addresses.
     * If an array of addresses is passed, that array is merged into the current list.
     *
     * @return SimpleEmailServiceMessage $this
     * @link http://docs.aws.amazon.com/ses/latest/APIReference/API_Destination.html
     */
    public function addTo($to) {
        if (!is_array($to)) {
            $this->to[] = $to;
        } else {
            $this->to = array_unique(array_merge($this->to, $to));
        }

        $this->is_clean = false;

        return $this;
    }

    /**
     * Clear the To: email address(es) for the message
     *
     * @return SimpleEmailServiceMessage $this
     */
    public function clearTo() {
        $this->to = array();

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     * @see addTo()
     */
    public function addCC($cc) {
        if (!is_array($cc)) {
            $this->cc[] = $cc;
        } else {
            $this->cc = array_merge($this->cc, $cc);
        }

        $this->is_clean = false;

        return $this;
    }

    /**
     * Clear the CC: email address(es) for the message
     *
     * @return SimpleEmailServiceMessage $this
     */
    public function clearCC() {
        $this->cc = array();

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     * @see addTo()
     */
    public function addBCC($bcc) {
        if (!is_array($bcc)) {
            $this->bcc[] = $bcc;
        } else {
            $this->bcc = array_merge($this->bcc, $bcc);
        }

        $this->is_clean = false;

        return $this;
    }

    /**
     * Clear the BCC: email address(es) for the message
     *
     * @return SimpleEmailServiceMessage $this
     */
    public function clearBCC() {
        $this->bcc = array();

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     * @see addTo()
     */
    public function addReplyTo($replyto) {
        if (!is_array($replyto)) {
            $this->replyto[] = $replyto;
        } else {
            $this->replyto = array_merge($this->replyto, $replyto);
        }

        $this->is_clean = false;

        return $this;
    }

    /**
     * Clear the Reply-To: email address(es) for the message
     *
     * @return SimpleEmailServiceMessage $this
     */
    public function clearReplyTo() {
        $this->replyto = array();

        $this->is_clean = false;

        return $this;
    }

    /**
     * Clear all of the message recipients in one go
     *
     * @return SimpleEmailServiceMessage $this
     * @uses clearTo()
     * @uses clearCC()
     * @uses clearBCC()
     * @uses clearReplyTo()
     */
    public function clearRecipients() {
        $this->clearTo();
        $this->clearCC();
        $this->clearBCC();
        $this->clearReplyTo();

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setFrom($from) {
        $this->from = $from;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setReturnPath($returnpath) {
        $this->returnpath = $returnpath;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setRecipientsCharset($charset) {
        $this->recipientsCharset = $charset;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setSubject($subject) {
        $this->subject = $subject;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setSubjectCharset($charset) {
        $this->subjectCharset = $charset;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     * @link http://docs.aws.amazon.com/ses/latest/APIReference/API_Message.html
     */
    public function setMessageFromString($text, $html = null) {
        $this->messagetext = $text;
        $this->messagehtml = $html;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setMessageFromFile($textfile, $htmlfile = null) {
        if (fs::fileExists($textfile) && fs::isFile($textfile) && is_readable($textfile)) {
            $this->messagetext = fs::file_get_contents($textfile);
        } else {
            $this->messagetext = null;
        }
        if (fs::fileExists($htmlfile) && fs::isFile($htmlfile) && is_readable($htmlfile)) {
            $this->messagehtml = fs::file_get_contents($htmlfile);
        } else {
            $this->messagehtml = null;
        }

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setMessageFromURL($texturl, $htmlurl = null) {
        if ($texturl !== null) {
            $this->messagetext = file_get_contents($texturl);
        } else {
            $this->messagetext = null;
        }
        if ($htmlurl !== null) {
            $this->messagehtml = file_get_contents($htmlurl);
        } else {
            $this->messagehtml = null;
        }

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setMessageCharset($textCharset, $htmlCharset = null) {
        $this->messageTextCharset = $textCharset;
        $this->messageHtmlCharset = $htmlCharset;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function setConfigurationSet($configuration_set = null) {
        $this->configuration_set = $configuration_set;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return array $message_tags
     */
    public function getMessageTags() {
        return $this->message_tags;
    }

    /**
     * @return null|mixed $message_tag
     */
    public function getMessageTag($key) {
        return isset($this->message_tags[$key]) ? $this->message_tags[$key] : null;
    }

    /**
     * Add Message tag
     *
     * Both key and value can contain only ASCII letters (a-z, A-Z), numbers (0-9), underscores (_), or dashes (-) and be less than 256 characters.
     *
     * @param string $key
     * @param mixed $value
     * @return SimpleEmailServiceMessage $this
     * @link https://docs.aws.amazon.com/ses/latest/DeveloperGuide/event-publishing-send-email.html
     * @link https://docs.aws.amazon.com/ses/latest/APIReference/API_MessageTag.html
     */
    public function setMessageTag($key, $value) {
        $this->message_tags[$key] = $value;

        $this->is_clean = false;

        return $this;
    }

    /**
     * @param string $key The key of the tag to be removed
     * @return SimpleEmailServiceMessage $this
     */
    public function removeMessageTag($key) {
        unset($this->message_tags[$key]);

        $this->is_clean = false;

        return $this;
    }

    /**
     * @param array $message_tags
     * @return SimpleEmailServiceMessage $this
     */
    public function setMessageTags($message_tags = array()) {
        $this->message_tags = array_merge($this->message_tags, $message_tags);

        $this->is_clean = false;

        return $this;
    }

    /**
     * @return SimpleEmailServiceMessage $this
     */
    public function removeMessageTags() {
        $this->message_tags = array();

        $this->is_clean = false;

        return $this;
    }

    /**
     * Add custom header - this works only with SendRawEmail
     *
     * @param string $header Your custom header
     * @return SimpleEmailServiceMessage $this
     * @link( Restrictions on headers, http://docs.aws.amazon.com/ses/latest/DeveloperGuide/header-fields.html)
     */
    public function addCustomHeader($header) {
        $this->customHeaders[] = $header;

        $this->is_clean = false;

        return $this;
    }

    /**
     * Add email attachment by directly passing the content
     *
     * @param string $name      The name of the file attachment as it will appear in the email
     * @param string $data      The contents of the attachment file
     * @param string $mimeType  Specify custom MIME type
     * @param string $contentId Content ID of the attachment for inclusion in the mail message
     * @param string $attachmentType    Attachment type: attachment or inline
     * @return SimpleEmailServiceMessage $this
     */
    public function addAttachmentFromData($name, $data, $mimeType = 'application/octet-stream', $contentId = null, $attachmentType = 'attachment') {
        $this->attachments[$name] = array(
            'name' => $name,
            'mimeType' => $mimeType,
            'data' => $data,
            'contentId' => $contentId,
            'attachmentType' => ($attachmentType == 'inline' ? 'inline; filename="' . $name . '"' : $attachmentType),
        );

        $this->is_clean = false;

        return $this;
    }

    /**
     * Add email attachment by passing file path
     *
     * @param string $name      The name of the file attachment as it will appear in the email
     * @param string $path      Path to the attachment file
     * @param string $mimeType  Specify custom MIME type
     * @param string $contentId Content ID of the attachment for inclusion in the mail message
     * @param string $attachmentType    Attachment type: attachment or inline
     * @return boolean Status of the operation
     */
    public function addAttachmentFromFile($name, $path, $mimeType = 'application/octet-stream', $contentId = null, $attachmentType = 'attachment') {
        if (fs::fileExists($path) && fs::isFile($path) && is_readable($path)) {
            $this->addAttachmentFromData($name, fs::file_get_contents($path), $mimeType, $contentId, $attachmentType);
            return true;
        }

        $this->is_clean = false;

        return false;
    }

    /**
     * Add email attachment by passing file path
     *
     * @param string $name      The name of the file attachment as it will appear in the email
     * @param string $url      URL to the attachment file
     * @param string $mimeType  Specify custom MIME type
     * @param string $contentId Content ID of the attachment for inclusion in the mail message
     * @param string $attachmentType    Attachment type: attachment or inline
     * @return boolean Status of the operation
     */
    public function addAttachmentFromUrl($name, $url, $mimeType = 'application/octet-stream', $contentId = null, $attachmentType = 'attachment') {
        $data = file_get_contents($url);
        if ($data !== false) {
            $this->addAttachmentFromData($name, $data, $mimeType, $contentId, $attachmentType);
            return true;
        }

        $this->is_clean = false;

        return false;
    }

    /**
     * Get the existence of attached inline messages
     *
     * @return boolean
     */
    public function hasInlineAttachments() {
        foreach ($this->attachments as $attachment) {
            if ($attachment['attachmentType'] != 'attachment') {
                return true;
            }

        }
        return false;
    }

    /**
     * Get the raw mail message
     *
     * @return string
     */
    public function getRawMessage($encode = true) {
        if ($this->is_clean && !is_null($this->raw_message) && $encode) {
            return $this->raw_message;
        }

        $this->is_clean = true;

        $boundary = uniqid(rand(), true);
        $raw_message = count($this->customHeaders) > 0 ? join("\n", $this->customHeaders) . "\n" : '';

        if (!empty($this->message_tags)) {
            $message_tags = array();
            foreach ($this->message_tags as $key => $value) {
                $message_tags[] = "{$key}={$value}";
            }

            $raw_message .= 'X-SES-MESSAGE-TAGS: ' . join(', ', $message_tags) . "\n";
        }

        if (!is_null($this->configuration_set)) {
            $raw_message .= 'X-SES-CONFIGURATION-SET: ' . $this->configuration_set . "\n";
        }

        $raw_message .= count($this->to) > 0 ? 'To: ' . $this->encodeRecipients($this->to) . "\n" : '';
        $raw_message .= 'From: ' . $this->encodeRecipients($this->from) . "\n";
        if (!empty($this->replyto)) {
            $raw_message .= 'Reply-To: ' . $this->encodeRecipients($this->replyto) . "\n";
        }

        if (!empty($this->cc)) {
            $raw_message .= 'CC: ' . $this->encodeRecipients($this->cc) . "\n";
        }
        if (!empty($this->bcc)) {
            $raw_message .= 'BCC: ' . $this->encodeRecipients($this->bcc) . "\n";
        }

        if ($this->subject != null && strlen($this->subject) > 0) {
            $raw_message .= 'Subject: =?' . $this->subjectCharset . '?B?' . base64_encode($this->subject) . "?=\n";
        }

        $raw_message .= 'MIME-Version: 1.0' . "\n";
        $raw_message .= 'Content-type: ' . ($this->hasInlineAttachments() ? 'multipart/related' : 'Multipart/Mixed') . '; boundary="' . $boundary . '"' . "\n";
        $raw_message .= "\n--{$boundary}\n";
        $raw_message .= 'Content-type: Multipart/Alternative; boundary="alt-' . $boundary . '"' . "\n";

        if ($this->messagetext != null && strlen($this->messagetext) > 0) {
            $charset = empty($this->messageTextCharset) ? '' : "; charset=\"{$this->messageTextCharset}\"";
            $raw_message .= "\n--alt-{$boundary}\n";
            $raw_message .= 'Content-Type: text/plain' . $charset . "\n\n";
            $raw_message .= $this->messagetext . "\n";
        }

        if ($this->messagehtml != null && strlen($this->messagehtml) > 0) {
            $charset = empty($this->messageHtmlCharset) ? '' : "; charset=\"{$this->messageHtmlCharset}\"";
            $raw_message .= "\n--alt-{$boundary}\n";
            $raw_message .= 'Content-Type: text/html' . $charset . "\n\n";
            $raw_message .= $this->messagehtml . "\n";
        }
        $raw_message .= "\n--alt-{$boundary}--\n";

        foreach ($this->attachments as $attachment) {
            $raw_message .= "\n--{$boundary}\n";
            $raw_message .= 'Content-Type: ' . $attachment['mimeType'] . '; name="' . $attachment['name'] . '"' . "\n";
            $raw_message .= 'Content-Disposition: ' . $attachment['attachmentType'] . "\n";
            if (!empty($attachment['contentId'])) {
                $raw_message .= 'Content-ID: ' . $attachment['contentId'] . '' . "\n";
            }
            $raw_message .= 'Content-Transfer-Encoding: base64' . "\n";
            $raw_message .= "\n" . chunk_split(base64_encode($attachment['data']), 76, "\n") . "\n";
        }

        $raw_message .= "\n--{$boundary}--\n";

        if (!$encode) {
            return $raw_message;
        }

        $this->raw_message = base64_encode($raw_message);

        return $this->raw_message;
    }

    /**
     * Encode recipient with the specified charset in `recipientsCharset`
     *
     * @return string Encoded recipients joined with comma
     */
    public function encodeRecipients($recipient) {
        if (is_array($recipient)) {
            return join(', ', array_map(array($this, 'encodeRecipients'), $recipient));
        }

        if (preg_match("/(.*)<(.*)>/", $recipient, $regs)) {
            $recipient = '=?' . $this->recipientsCharset . '?B?' . base64_encode($regs[1]) . '?= <' . $regs[2] . '>';
        }

        return $recipient;
    }

    /**
     * Validates whether the message object has sufficient information to submit a request to SES.
     *
     * This does not guarantee the message will arrive, nor that the request will succeed;
     * instead, it makes sure that no required fields are missing.
     *
     * This is used internally before attempting a SendEmail or SendRawEmail request,
     * but it can be used outside of this file if verification is desired.
     * May be useful if e.g. the data is being populated from a form; developers can generally
     * use this function to verify completeness instead of writing custom logic.
     *
     * @return boolean
     */
    public function validate() {
        // at least one destination is required
        if (count($this->to) == 0 && count($this->cc) == 0 && count($this->bcc) == 0) {
            return false;
        }

        // sender is required
        if ($this->from == null || strlen($this->from) == 0) {
            return false;
        }

        // subject is required
        if (($this->subject == null || strlen($this->subject) == 0)) {
            return false;
        }

        // message is required
        if ((empty($this->messagetext) || strlen((string) $this->messagetext) == 0)
            && (empty($this->messagehtml) || strlen((string) $this->messagehtml) == 0)) {
            return false;
        }

        return true;
    }
}



/**
*
* Copyright (c) 2014, Daniel Zahariev.
* Copyright (c) 2011, Dan Myers.
* Parts copyright (c) 2008, Donovan Schonknecht.
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* This is a modified BSD license (the third clause has been removed).
* The BSD license may be found here:
* http://www.opensource.org/licenses/bsd-license.php
*
* Amazon Simple Email Service is a trademark of Amazon.com, Inc. or its affiliates.
*
* SimpleEmailService is based on Donovan Schonknecht's Amazon S3 PHP class, found here:
* http://undesigned.org.za/2007/10/22/amazon-s3-php-class
*
* @copyright 2014 Daniel Zahariev
* @copyright 2011 Dan Myers
* @copyright 2008 Donovan Schonknecht
*/

/**
* SimpleEmailService PHP class
*
* @link https://github.com/daniel-zahariev/php-aws-ses
* @package AmazonSimpleEmailService
* @version v0.9.1
*/
class SimpleEmailService
{
	/**
	 * @link(AWS SES regions, http://docs.aws.amazon.com/ses/latest/DeveloperGuide/regions.html)
	 */
	const AWS_US_EAST_1 = 'email.us-east-1.amazonaws.com';
	const AWS_US_WEST_2 = 'email.us-west-2.amazonaws.com';
	const AWS_EU_WEST1 = 'email.eu-west-1.amazonaws.com';

	/**
	 * AWS SES Target host of region
	 */
	protected $__host;

	/**
	 * AWS SES Access key
	 */
	protected $__accessKey;

	/**
	 * AWS Secret key
	 */
	protected $__secretKey;

	/**
	 * Enable/disable
	 */
	protected $__trigger_errors;

	/**
	 * Controls the reuse of CURL hander for sending a bulk of messages
	 * @deprecated
	 */
	protected $__bulk_sending_mode = false;

	/**
	 * Optionally reusable SimpleEmailServiceRequest instance
	 */
	protected $__ses_request = null;

	/**
	 * Controls CURLOPT_SSL_VERIFYHOST setting for SimpleEmailServiceRequest's curl handler
	 */
	protected $__verifyHost = true;

	/**
	 * Controls CURLOPT_SSL_VERIFYPEER setting for SimpleEmailServiceRequest's curl handler
	 */
	protected $__verifyPeer = true;

	/**
	* Constructor
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @param string $host Amazon Host through which to send the emails
	* @param boolean $trigger_errors Trigger PHP errors when AWS SES API returns an error
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $host = self::AWS_US_EAST_1, $trigger_errors = true) {
		if ($accessKey !== null && $secretKey !== null) {
			$this->setAuth($accessKey, $secretKey);
		}
		$this->__host = $host;
		$this->__trigger_errors = $trigger_errors;
	}

	/**
	* Set AWS access key and secret key
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return SimpleEmailService $this
	*/
	public function setAuth($accessKey, $secretKey) {
		$this->__accessKey = $accessKey;
		$this->__secretKey = $secretKey;

		return $this;
	}

	/**
	 * Set AWS Host
	 * @param string $host AWS Host
	 */
	public function setHost($host = self::AWS_US_EAST_1) {
		$this->__host = $host;

		return $this;
	}

	/**
	 * @deprecated
	 */
	public function enableVerifyHost($enable = true) {
		$this->__verifyHost = (bool)$enable;

		return $this;
	}

	/**
	 * @deprecated
	 */
	public function enableVerifyPeer($enable = true) {
		$this->__verifyPeer = (bool)$enable;

		return $this;
	}

	/**
	 * @deprecated
	 */
	public function verifyHost() {
		return $this->__verifyHost;
	}

	/**
	 * @deprecated
	 */
	public function verifyPeer() {
		return $this->__verifyPeer;
	}


	/**
	* Get AWS target host
	* @return boolean
	*/
	public function getHost() {
		return $this->__host;
	}

	/**
	* Get AWS SES auth access key
	* @return string
	*/
	public function getAccessKey() {
		return $this->__accessKey;
	}

	/**
	* Get AWS SES auth secret key
	* @return string
	*/
	public function getSecretKey() {
		return $this->__secretKey;
	}

	/**
	* Get the verify peer CURL mode
	* @return boolean
	*/
	public function getVerifyPeer() {
		return $this->__verifyPeer;
	}

	/**
	* Get the verify host CURL mode
	* @return boolean
	*/
	public function getVerifyHost() {
		return $this->__verifyHost;
	}

	/**
	* Get bulk email sending mode
	* @deprecated
	* @return boolean
	*/
	public function getBulkMode() {
		return $this->__bulk_sending_mode;
	}


	/**
	* Enable/disable CURLOPT_SSL_VERIFYHOST for SimpleEmailServiceRequest's curl handler
	* verifyHost and verifyPeer determine whether curl verifies ssl certificates.
	* It may be necessary to disable these checks on certain systems.
	* These only have an effect if SSL is enabled.
	*
	* @param boolean $enable New status for the mode
	* @return SimpleEmailService $this
	*/
	public function setVerifyHost($enable = true) {
		$this->__verifyHost = (bool)$enable;
		return $this;
	}

	/**
	* Enable/disable CURLOPT_SSL_VERIFYPEER for SimpleEmailServiceRequest's curl handler
	* verifyHost and verifyPeer determine whether curl verifies ssl certificates.
	* It may be necessary to disable these checks on certain systems.
	* These only have an effect if SSL is enabled.
	*
	* @param boolean $enable New status for the mode
	* @return SimpleEmailService $this
	*/
	public function setVerifyPeer($enable = true) {
		$this->__verifyPeer = (bool)$enable;
		return $this;
	}

	/**
	* Enable/disable bulk email sending mode
	*
	* @param boolean $enable New status for the mode
	* @return SimpleEmailService $this
	* @deprecated
	*/
	public function setBulkMode($enable = true) {
		$this->__bulk_sending_mode = (bool)$enable;
		return $this;
	}

	/**
	* Lists the email addresses that have been verified and can be used as the 'From' address
	*
	* @return array An array containing two items: a list of verified email addresses, and the request id.
	*/
	public function listVerifiedEmailAddresses() {
		$ses_request = $this->getRequestHandler('GET');
		$ses_request->setParameter('Action', 'ListVerifiedEmailAddresses');

		$ses_response = $ses_request->getResponse();
		if($ses_response->error === false && $ses_response->code !== 200) {
			$ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
		}
		if($ses_response->error !== false) {
			$this->__triggerError('listVerifiedEmailAddresses', $ses_response->error);
			return false;
		}

		$response = array();
		if(!isset($ses_response->body)) {
			return $response;
		}

		$addresses = array();
		foreach($ses_response->body->ListVerifiedEmailAddressesResult->VerifiedEmailAddresses->member as $address) {
			$addresses[] = (string)$address;
		}

		$response['Addresses'] = $addresses;
		$response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;

		return $response;
	}

	/**
	* Requests verification of the provided email address, so it can be used
	* as the 'From' address when sending emails through SimpleEmailService.
	*
	* After submitting this request, you should receive a verification email
	* from Amazon at the specified address containing instructions to follow.
	*
	* @param string $email The email address to get verified
	* @return array The request id for this request.
	*/
	public function verifyEmailAddress($email) {
		$ses_request = $this->getRequestHandler('POST');
		$ses_request->setParameter('Action', 'VerifyEmailAddress');
		$ses_request->setParameter('EmailAddress', $email);

		$ses_response = $ses_request->getResponse();
		if($ses_response->error === false && $ses_response->code !== 200) {
			$ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
		}
		if($ses_response->error !== false) {
			$this->__triggerError('verifyEmailAddress', $ses_response->error);
			return false;
		}

		$response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;
		return $response;
	}

	/**
	* Removes the specified email address from the list of verified addresses.
	*
	* @param string $email The email address to remove
	* @return array The request id for this request.
	*/
	public function deleteVerifiedEmailAddress($email) {
		$ses_request = $this->getRequestHandler('DELETE');
		$ses_request->setParameter('Action', 'DeleteVerifiedEmailAddress');
		$ses_request->setParameter('EmailAddress', $email);

		$ses_response = $ses_request->getResponse();
		if($ses_response->error === false && $ses_response->code !== 200) {
			$ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
		}
		if($ses_response->error !== false) {
			$this->__triggerError('deleteVerifiedEmailAddress', $ses_response->error);
			return false;
		}

		$response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;
		return $response;
	}

	/**
	* Retrieves information on the current activity limits for this account.
	* See http://docs.amazonwebservices.com/ses/latest/APIReference/API_GetSendQuota.html
	*
	* @return array An array containing information on this account's activity limits.
	*/
	public function getSendQuota() {
		$ses_request = $this->getRequestHandler('GET');
		$ses_request->setParameter('Action', 'GetSendQuota');

		$ses_response = $ses_request->getResponse();
		if($ses_response->error === false && $ses_response->code !== 200) {
			$ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
		}
		if($ses_response->error !== false) {
			$this->__triggerError('getSendQuota', $ses_response->error);
			return false;
		}

		$response = array();
		if(!isset($ses_response->body)) {
			return $response;
		}

		$response['Max24HourSend'] = (string)$ses_response->body->GetSendQuotaResult->Max24HourSend;
		$response['MaxSendRate'] = (string)$ses_response->body->GetSendQuotaResult->MaxSendRate;
		$response['SentLast24Hours'] = (string)$ses_response->body->GetSendQuotaResult->SentLast24Hours;
		$response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;

		return $response;
	}

	/**
	* Retrieves statistics for the last two weeks of activity on this account.
	* See http://docs.amazonwebservices.com/ses/latest/APIReference/API_GetSendStatistics.html
	*
	* @return array An array of activity statistics.  Each array item covers a 15-minute period.
	*/
	public function getSendStatistics() {
		$ses_request = $this->getRequestHandler('GET');
		$ses_request->setParameter('Action', 'GetSendStatistics');

		$ses_response = $ses_request->getResponse();
		if($ses_response->error === false && $ses_response->code !== 200) {
			$ses_response->error = array('code' => $ses_response->code, 'message' => 'Unexpected HTTP status');
		}
		if($ses_response->error !== false) {
			$this->__triggerError('getSendStatistics', $ses_response->error);
			return false;
		}

		$response = array();
		if(!isset($ses_response->body)) {
			return $response;
		}

		$datapoints = array();
		foreach($ses_response->body->GetSendStatisticsResult->SendDataPoints->member as $datapoint) {
			$p = array();
			$p['Bounces'] = (string)$datapoint->Bounces;
			$p['Complaints'] = (string)$datapoint->Complaints;
			$p['DeliveryAttempts'] = (string)$datapoint->DeliveryAttempts;
			$p['Rejects'] = (string)$datapoint->Rejects;
			$p['Timestamp'] = (string)$datapoint->Timestamp;

			$datapoints[] = $p;
		}

		$response['SendDataPoints'] = $datapoints;
		$response['RequestId'] = (string)$ses_response->body->ResponseMetadata->RequestId;

		return $response;
	}


	/**
	* Given a SimpleEmailServiceMessage object, submits the message to the service for sending.
	*
	* @param SimpleEmailServiceMessage $sesMessage An instance of the message class
	* @param boolean $use_raw_request If this is true or there are attachments to the email `SendRawEmail` call will be used
	* @param boolean $trigger_error Optionally overwrite the class setting for triggering an error (with type check to true/false)
	* @return array An array containing the unique identifier for this message and a separate request id.
	*         Returns false if the provided message is missing any required fields.
	*  @link(AWS SES Response formats, http://docs.aws.amazon.com/ses/latest/DeveloperGuide/query-interface-responses.html)
	*/
	public function sendEmail($sesMessage, $use_raw_request = false , $trigger_error = null) {
		if(!$sesMessage->validate()) {
			$this->__triggerError('sendEmail', 'Message failed validation.');
			return false;
		}

		$ses_request = $this->getRequestHandler('POST');
		$action = !empty($sesMessage->attachments) || $use_raw_request ? 'SendRawEmail' : 'SendEmail';
		$ses_request->setParameter('Action', $action);

		// Works with both calls
		if (!is_null($sesMessage->configuration_set)) {
			$ses_request->setParameter('ConfigurationSetName', $sesMessage->configuration_set);
		}

		if($action == 'SendRawEmail') {
			// https://docs.aws.amazon.com/ses/latest/APIReference/API_SendRawEmail.html
			$ses_request->setParameter('RawMessage.Data', $sesMessage->getRawMessage());
		} else {
			$i = 1;
			foreach($sesMessage->to as $to) {
				$ses_request->setParameter('Destination.ToAddresses.member.'.$i, $sesMessage->encodeRecipients($to));
				$i++;
			}

			if(is_array($sesMessage->cc)) {
				$i = 1;
				foreach($sesMessage->cc as $cc) {
					$ses_request->setParameter('Destination.CcAddresses.member.'.$i, $sesMessage->encodeRecipients($cc));
					$i++;
				}
			}

			if(is_array($sesMessage->bcc)) {
				$i = 1;
				foreach($sesMessage->bcc as $bcc) {
					$ses_request->setParameter('Destination.BccAddresses.member.'.$i, $sesMessage->encodeRecipients($bcc));
					$i++;
				}
			}

			if(is_array($sesMessage->replyto)) {
				$i = 1;
				foreach($sesMessage->replyto as $replyto) {
					$ses_request->setParameter('ReplyToAddresses.member.'.$i, $sesMessage->encodeRecipients($replyto));
					$i++;
				}
			}

			$ses_request->setParameter('Source', $sesMessage->encodeRecipients($sesMessage->from));

			if($sesMessage->returnpath != null) {
				$ses_request->setParameter('ReturnPath', $sesMessage->returnpath);
			}

			if($sesMessage->subject != null && strlen($sesMessage->subject) > 0) {
				$ses_request->setParameter('Message.Subject.Data', $sesMessage->subject);
				if($sesMessage->subjectCharset != null && strlen($sesMessage->subjectCharset) > 0) {
					$ses_request->setParameter('Message.Subject.Charset', $sesMessage->subjectCharset);
				}
			}


			if($sesMessage->messagetext != null && strlen($sesMessage->messagetext) > 0) {
				$ses_request->setParameter('Message.Body.Text.Data', $sesMessage->messagetext);
				if($sesMessage->messageTextCharset != null && strlen($sesMessage->messageTextCharset) > 0) {
					$ses_request->setParameter('Message.Body.Text.Charset', $sesMessage->messageTextCharset);
				}
			}

			if($sesMessage->messagehtml != null && strlen($sesMessage->messagehtml) > 0) {
				$ses_request->setParameter('Message.Body.Html.Data', $sesMessage->messagehtml);
				if($sesMessage->messageHtmlCharset != null && strlen($sesMessage->messageHtmlCharset) > 0) {
					$ses_request->setParameter('Message.Body.Html.Charset', $sesMessage->messageHtmlCharset);
				}
			}

			$i = 1;
			foreach($sesMessage->message_tags as $key => $value) {
				$ses_request->setParameter('Tags.member.'.$i.'.Name', $key);
				$ses_request->setParameter('Tags.member.'.$i.'.Value', $value);
				$i++;
			}
		}

		$ses_response = $ses_request->getResponse();
		if($ses_response->error === false && $ses_response->code !== 200) {
			$response = array(
				'code' => $ses_response->code,
				'error' => array('Error' => array('message' => 'Unexpected HTTP status')),
			);
			return $response;
		}
		if($ses_response->error !== false) {
			if (($this->__trigger_errors && ($trigger_error !== false)) || $trigger_error === true) {
				$this->__triggerError('sendEmail', $ses_response->error);
				return false;
			}
			return $ses_response;
		}

		$response = array(
			'MessageId' => (string)$ses_response->body->{"{$action}Result"}->MessageId,
			'RequestId' => (string)$ses_response->body->ResponseMetadata->RequestId,
		);
		return $response;
	}

	/**
	* Trigger an error message
	*
	* {@internal Used by member functions to output errors}
	* @param  string $functionname The name of the function that failed
	* @param array $error Array containing error information
	* @return  void
	*/
	public function __triggerError($functionname, $error)
	{
		if($error == false) {
			trigger_error(sprintf("SimpleEmailService::%s(): Encountered an error, but no description given", $functionname), E_USER_WARNING);
		}
		else if(isset($error['curl']) && $error['curl'])
		{
			trigger_error(sprintf("SimpleEmailService::%s(): %s %s", $functionname, $error['code'], $error['message']), E_USER_WARNING);
		}
		else if(isset($error['Error']))
		{
			$e = $error['Error'];
			$message = sprintf("SimpleEmailService::%s(): %s - %s: %s\nRequest Id: %s\n", $functionname, $e['Type'], $e['Code'], $e['Message'], $error['RequestId']);
			trigger_error($message, E_USER_WARNING);
		}
		else {
			trigger_error(sprintf("SimpleEmailService::%s(): Encountered an error: %s", $functionname, $error), E_USER_WARNING);
		}
	}

	/**
	 * Set SES Request
	 *
	 * @param SimpleEmailServiceRequest $ses_request description
	 * @return SimpleEmailService $this
	 */
	public function setRequestHandler(SimpleEmailServiceRequest $ses_request = null) {
		if (!is_null($ses_request)) {
			$ses_request->setSES($this);
		}

		$this->__ses_request = $ses_request;

		return $this;
	}

	/**
	 * Get SES Request
	 *
	 * @param string $verb HTTP Verb: GET, POST, DELETE
	 * @return SimpleEmailServiceRequest SES Request
	 */
	public function getRequestHandler($verb) {
		if (empty($this->__ses_request)) {
			$this->__ses_request = new SimpleEmailServiceRequest($this, $verb);
		} else {
			$this->__ses_request->setVerb($verb);
		}

		return $this->__ses_request;
	}
}


/**
* SimpleEmailServiceRequest PHP class
*
* @link https://github.com/daniel-zahariev/php-aws-ses
* @package AmazonSimpleEmailService
* @version v0.9.1
*/
class SimpleEmailServiceRequest
{
	private $ses, $verb, $parameters = array();

	// CURL request handler that can be reused
	protected $curl_handler = null;

	// Holds the response from calling AWS's API
	protected $response;

	//
	public static $curlOptions = array();

	/**
	* Constructor
	*
	* @param SimpleEmailService $ses The SimpleEmailService object making this request
	* @param string $verb HTTP verb
	* @return void
	*/
	public function __construct(SimpleEmailService $ses = null, $verb = 'GET') {
		$this->ses = $ses;
		$this->verb = $verb;
		$this->response = (object) array('body' => '', 'code' => 0, 'error' => false);
	}


	/**
	* Set SES class
	*
	* @param SimpleEmailService $ses
	* @return SimpleEmailServiceRequest $this
	*/
	public function setSES(SimpleEmailService $ses) {
		$this->ses = $ses;

		return $this;
	}

	/**
	* Set HTTP method
	*
	* @param string $verb
	* @return SimpleEmailServiceRequest $this
	*/
	public function setVerb($verb) {
		$this->verb = $verb;

		return $this;
	}

	/**
	* Set request parameter
	*
	* @param string  $key Key
	* @param string  $value Value
	* @param boolean $replace Whether to replace the key if it already exists (default true)
	* @return SimpleEmailServiceRequest $this
	*/
	public function setParameter($key, $value, $replace = true) {
		if(!$replace && isset($this->parameters[$key])) {
			$temp = (array)($this->parameters[$key]);
			$temp[] = $value;
			$this->parameters[$key] = $temp;
		} else {
			$this->parameters[$key] = $value;
		}

		return $this;
	}

	/**
	* Get the params for the reques
	*
	* @return array $params
	*/
	public function getParametersEncoded() {
		$params = array();

		foreach ($this->parameters as $var => $value) {
			if(is_array($value)) {
				foreach($value as $v) {
					$params[] = $var.'='.$this->__customUrlEncode($v);
				}
			} else {
				$params[] = $var.'='.$this->__customUrlEncode($value);
			}
		}

		sort($params, SORT_STRING);

		return $params;
	}

	/**
	* Clear the request parameters
	* @return SimpleEmailServiceRequest $this
	*/
	public function clearParameters() {
		$this->parameters = array();
		return $this;
	}

	/**
	* Instantiate and setup CURL handler for sending requests.
	* Instance is cashed in `$this->curl_handler`
	*
	* @return resource $curl_handler
	*/
	protected function getCurlHandler() {
		if (!empty($this->curl_handler))
			return $this->curl_handler;

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'SimpleEmailService/php');

		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, ($this->ses->verifyHost() ? 2 : 0));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ($this->ses->verifyPeer() ? 1 : 0));
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		foreach(self::$curlOptions as $option => $value) {
			curl_setopt($curl, $option, $value);
		}

		$this->curl_handler = $curl;

		return $this->curl_handler;
	}

	/**
	* Get the response
	*
	* @return object | false
	*/
	public function getResponse() {

		// must be in format 'Sun, 06 Nov 1994 08:49:37 GMT'
		$date = gmdate('D, d M Y H:i:s e');
		$query = implode('&', $this->getParametersEncoded());
		$auth = 'AWS3-HTTPS AWSAccessKeyId='.$this->ses->getAccessKey();
		$auth .= ',Algorithm=HmacSHA256,Signature='.$this->__getSignature($date);
		$url = 'https://'.$this->ses->getHost().'/';

		$headers = array();
		$headers[] = 'Date: ' . $date;
		$headers[] = 'Host: ' . $this->ses->getHost();
		$headers[] = 'X-Amzn-Authorization: ' . $auth;

		$curl_handler = $this->getCurlHandler();
		curl_setopt($curl_handler, CURLOPT_CUSTOMREQUEST, $this->verb);

		// Request types
		switch ($this->verb) {
			case 'GET':
			case 'DELETE':
				$url .= '?'.$query;
				break;

			case 'POST':
				curl_setopt($curl_handler, CURLOPT_POSTFIELDS, $query);
				$headers[] = 'Content-Type: application/x-www-form-urlencoded';
				break;
		}
		curl_setopt($curl_handler, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl_handler, CURLOPT_URL, $url);


		// Execute, grab errors
		if (curl_exec($curl_handler)) {
			$this->response->code = curl_getinfo($curl_handler, CURLINFO_HTTP_CODE);
		} else {
			$this->response->error = array(
				'curl' => true,
				'code' => curl_errno($curl_handler),
				'message' => curl_error($curl_handler),
			);
		}

		// cleanup for reusing the current instance for multiple requests
		curl_setopt($curl_handler, CURLOPT_POSTFIELDS, '');
		$this->parameters = array();

		// Parse body into XML
		if ($this->response->error === false && !empty($this->response->body)) {
			$this->response->body = simplexml_load_string($this->response->body);

			// Grab SES errors
			if (!in_array($this->response->code, array(200, 201, 202, 204))
				&& isset($this->response->body->Error)) {
				$error = $this->response->body->Error;
				$output = array();
				$output['curl'] = false;
				$output['Error'] = array();
				$output['Error']['Type'] = (string)$error->Type;
				$output['Error']['Code'] = (string)$error->Code;
				$output['Error']['Message'] = (string)$error->Message;
				$output['RequestId'] = (string)$this->response->body->RequestId;

				$this->response->error = $output;
				unset($this->response->body);
			}
		}

		$response = $this->response;
		$this->response = (object) array('body' => '', 'code' => 0, 'error' => false);

		return $response;
	}

	/**
	* Destroy any leftover handlers
	*/
	public function __destruct() {
		if (!empty($this->curl_handler))
			@curl_close($this->curl_handler);
	}

	/**
	* CURL write callback
	*
	* @param resource $curl CURL resource
	* @param string $data Data
	* @return integer
	*/
	private function __responseWriteCallback(&$curl, &$data) {
		if (!isset($this->response->body)) {
			$this->response->body = $data;
		} else {
			$this->response->body .= $data;
		}

		return strlen($data);
	}

	/**
	* Contributed by afx114
	* URL encode the parameters as per http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/index.html?Query_QueryAuth.html
	* PHP's rawurlencode() follows RFC 1738, not RFC 3986 as required by Amazon. The only difference is the tilde (~), so convert it back after rawurlencode
	* See: http://www.morganney.com/blog/API/AWS-Product-Advertising-API-Requires-a-Signed-Request.php
	*
	* @param string $var String to encode
	* @return string
	*/
	private function __customUrlEncode($var) {
		return str_replace('%7E', '~', rawurlencode($var));
	}

	/**
	* Generate the auth string using Hmac-SHA256
	*
	* @internal Used by SimpleEmailServiceRequest::getResponse()
	* @param string $string String to sign
	* @return string
	*/
	private function __getSignature($string) {
		return base64_encode(hash_hmac('sha256', $string, $this->ses->getSecretKey(), true));
	}
}
