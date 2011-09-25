<?php

class App_Amazon_Email extends Zend_Service_Amazon_Abstract
{
	const SES_ENDPOINT = 'email.us-east-1.amazonaws.com';
	protected $_endpoint;
	protected $_toEmailAdresses = array();
	protected $_bccEmailAddresses = array();
	protected $_ccEmailAdresses = array();
	protected $_replyToAddresses = array();
	protected $_fromEmailAddress = NULL;
	protected $_returnPath = NULL;
	protected $_htmlBody = '';
	protected $_textBody = '';
	protected $_subject = '';
	protected $_charset = 'UTF-8';
	protected $_errorCode;
	protected $_errorMessage;
	
	
	// At the momend Simple Email Service is available only on US EAST. This function is adedd for future development
	// Endpoint must contain "https://"
	public function setEndpoint($endpoint)
    {
        if (!($endpoint instanceof Zend_Uri_Http)) {
            $endpoint = Zend_Uri::factory($endpoint);
        }
        if (!$endpoint->valid()) {
            throw new Zend_Exception('Invalid endpoint supplied');
        }
        $this->_endpoint = $endpoint;
        return $this;
    }
	
    public function getEndpoint()
    {
        return $this->_endpoint;
    }
	
	public function __construct($accessKey=null, $secretKey=null)
    {
        parent::__construct($accessKey, $secretKey, $region);
		
		// sets the default endpoint (us-east-1)
        $this->setEndpoint('https://'.self::SES_ENDPOINT);
    }
	
	public function verifyEmailAddress($emailAddress)
	{
		// validate email address against zend validate
		if( ! Zend_Validate::is($emailAddress, 'EmailAddress'))
		{
			throw new Zend_Exception(sprintf('Email %s is not valid', $emailAddress));
		}
		
		$response = $this->_makeRequest(array('EmailAddress' => $emailAddress, 'Action' => 'VerifyEmailAddress'));
		
		if($response->getStatus() != 200)
		{
			$this->_setError($response);
			return FALSE;
		}
		
		return TRUE;
	}
	
	public function deleteVerifiedEmailAddress($emailAddress)
	{
		if( ! Zend_Validate::is($emailAddress, 'EmailAddress'))
		{
			throw new Zend_Exception(sprintf('Email %s is not valid', $emailAddress));
		}
		
		$response = $this->_makeRequest(array('EmailAddress' => $emailAddress, 'Action' => 'DeleteVerifiedEmailAddress'));
		
		if($response->getStatus() != 200)
		{
			$this->_setError($response);
			return FALSE;
		}
		
		return TRUE;
	}
	
	// returns an array with all verified email addresses
	public function listVerifiedEmailAddresses()
	{
		$response = $this->_makeRequest(array('Action' => 'ListVerifiedEmailAddresses'));
		
		if($response->getStatus() != 200)
		{
			$this->_setError($response);
			return FALSE;
		}
		
		// create addresses array
		$xml = new SimpleXMLElement($response->getBody());
		$addresses = array();
		foreach($xml->ListVerifiedEmailAddressesResult->VerifiedEmailAddresses->member as $address)
		{
			$addresses[] = (string) $address;
		}
		
		return $addresses;
	}
	
	// returns an array with send quota
	public function getSendQuota()
	{
		$response = $this->_makeRequest(array('Action' => 'GetSendQuota'));
		
		if($response->getStatus() != 200)
		{
			$this->_setError($response);
			return FALSE;
		}
		
		$xml = new SimpleXMLElement($response->getBody());
		$quota = array();
		$quota['SentLast24Hours'] = (int) $xml->GetSendQuotaResult->SentLast24Hours;
		$quota['Max24HourSend'] = (int) $xml->GetSendQuotaResult->Max24HourSend;
		$quota['MaxSendRate'] = (int) $xml->GetSendQuotaResult->MaxSendRate;
		
		return $quota;
	}
	
	public function getSendStatistics()
	{
		$response = $this->_makeRequest(array('Action' => 'GetSendStatistics'));
		
		if($response->getStatus() != 200)
		{
			$this->_setError($response);
			return FALSE;
		}
		
		$xml = new SimpleXMLElement($response->getBody());
		$statistics = array();
		foreach($xml->GetSendStatisticsResult->SendDataPoints->member as $member)
		{
			$statistics[] = array(
				'DeliveryAttempts' => (int) $member->DeliveryAttempts,
				'Timestamp' => (string) $member->Timestamp,
				'Rejects' => (int) $member->Rejects,
				'Bounces' => (int) $member->Bounces,
				'Complaints' => (int) $member->Complaints
			);
		}
		
		return $statistics;
	}
	
	public function setTo($emailAddress)
	{
		$this->_toEmailAdresses = array();
		$this->addTo($emailAddress);
	}
	
	public function addTo($emailAddress)
	{
		if( ! is_array($emailAddress))
		{
			$emailAddress = array($emailAddress);
		}
		
		foreach($emailAddress as $address)
		{
			if( ! in_array($address, $this->_toEmailAdresses))
			{
				if( ! Zend_Validate::is($address, 'EmailAddress'))
				{
					throw new Zend_Exception(sprintf('Email %s is not valid', $address));
				}
				$this->_toEmailAdresses[] = $address;
			}
		}
	}
	
	public function setBcc($emailAddress)
	{
		$this->_bccEmailAddresses = array();
		$this->addBcc($emailAddress);
	}
	
	public function addBcc($emailAddress)
	{
		if( ! is_array($emailAddress))
		{
			$emailAddress = array($emailAddress);
		}
		
		foreach($emailAddress as $address)
		{
			if( ! in_array($address, $this->_bccEmailAdresses))
			{
				if( ! Zend_Validate::is($address, 'EmailAddress'))
				{
					throw new Zend_Exception(sprintf('Email %s is not valid', $address));
				}
				$this->_bccEmailAdresses[] = $address;
			}
		}
	}
	
	public function setCc($emailAddress)
	{
		$this->_ccEmailAdresses = array();
		$this->addCc($emailAddress);
	}
	
	public function addCc($emailAddress)
	{
		if( ! is_array($emailAddress))
		{
			$emailAddress = array($emailAddress);
		}
		
		foreach($emailAddress as $address)
		{
			if( ! in_array($address, $this->_ccEmailAdresses))
			{
				if( ! Zend_Validate::is($address, 'EmailAddress'))
				{
					throw new Zend_Exception(sprintf('Email %s is not valid', $address));
				}
				
				$this->_ccEmailAdresses[] = $address;
			}
		}
	}
	
	public function setReplyTo($emailAddress)
	{
		$this->_replyToAddresses = array();
		$this->addReplyTo($emailAddress);
	}
	
	public function addReplyTo($emailAddress)
	{
		if( ! is_array($emailAddress))
		{
			$emailAddress = array($emailAddress);
		}
		
		foreach($emailAddress as $address)
		{
			if( ! in_array($address, $this->_replyToAddresses))
			{
				if( ! Zend_Validate::is($address, 'EmailAddress'))
				{
					throw new Zend_Exception(sprintf('Email %s is not valid', $address));
				}
				
				$this->_replyToAddresses[] = $address;
			}
		}
	}
	
	public function setReplyPath($emailAddress)
	{
		if( ! Zend_Validate::is($emailAddress, 'EmailAddress'))
		{
			throw new Zend_Exception(sprintf('Email %s is not valid', $emailAddress));
		}
		
		$this->_returnPath = $emailAddress;
	}
	
	public function setBodyText($text)
	{
		$this->_textBody = $text;
	}
	
	public function setBodyHtml($html)
	{
		$this->_htmlBody = $html;
	}
	
	public function setSubject($subject)
	{
		$this->_subject = $subject;
	}
	
	public function send()
	{
		if( ! $this->_fromEmailAddress)
		{
			throw new Zend_Exception('Sender email address is not specified');
		}
		
		if( ! $this->_toEmailAdresses AND ! $this->_bccEmailAddresses AND ! $this->_ccEmailAdresses)
		{
			throw new Zend_Exception('No email address specified');
		}
		
		$params = array('Action' => 'SendEmail');
		if($this->_toEmailAdresses)
		{
			foreach($this->_toEmailAdresses as $key => $one)
			{
				$params['Destination.ToAddresses.member.' . ($key+1)] = $one;
			}
		}
		
		if($this->_ccEmailAdresses)
		{
			foreach($this->_ccEmailAdresses as $key => $one)
			{
				$params['Destination.CcAddresses.member.' . ($key+1)] = $one;
			}
		}

		if($this->_bccEmailAddresses)
		{
			foreach($this->_bccEmailAddresses as $key => $one)
			{
				$params['Destination.BccAddresses.member.' . ($key+1)] = $one;
			}
		}
		
		if($this->_replyToAddresses)
		{
			foreach($this->_replyToAddresses as $key => $one)
			{
				$params['ReplyToAddresses.member.' . ($key+1)] = $one;
			}
		}
		
		$params['Source'] = $this->_fromEmailAddress;
		if($this->_returnPath)
		{
			$params['ReturnPath'] = $this->_returnPath;
		}
		
		if($this->_subject)
		{
			$params['Message.Subject.Data'] = $this->_subject;
			$params['Message.Subject.Charset'] = $this->_charset;
		}
		
		if($this->_textBody)
		{
			$params['Message.Body.Text.Data'] = $this->_textBody;
			$params['Message.Body.Text.Charset'] = $this->_charset;
		}

		if($this->_htmlBody)
		{
			$params['Message.Body.Html.Data'] = $this->_htmlBody;
			$params['Message.Body.Html.Charset'] = $this->_charset;
		}
		
		$response = $this->_makeRequest($params);
		
		if($response->getStatus() != 200)
		{
			$this->_setError($response);
			return FALSE;
		}
		
		return $response;
	}
	
	public function setFrom($from)
	{
		if( ! Zend_Validate::is($from, 'EmailAddress'))
		{
			throw new Zend_Exception(sprintf('Email %s is not valid', $from));
		}
		
		$this->_fromEmailAddress = $from;
	}
	
	public function setCharset($charset)
	{
		$this->_charset = $charset;
	}
	
	public function _makeRequest($params = NULL, $headers = array())
	{
		if (!is_array($headers)) {
            $headers = array($headers);
        }

        $headers['Date'] = gmdate(DATE_RFC1123, time());
		
		$auth = 'AWS3-HTTPS AWSAccessKeyId='.$this->_getAccessKey();
		$auth .= ',Algorithm=HmacSHA256,Signature='.$this->_getSignature($headers['Date']);
		$headers['X-Amzn-Authorization'] = $auth;
		
		$client = self::getHttpClient();
		
		$client->resetParameters();
        $client->setUri($this->_endpoint);
        $client->setAuth(false);

        $client->setHeaders(array('Content-MD5' => null,
                                  'Expect'      => null,
                                  'Range'       => null,
                                  'x-amz-acl'   => null));

        $client->setHeaders($headers);

		if (is_array($params)) 
		{
            $client->setParameterPost($params);
		}
		$retry_count = 1;
		do {
            $retry = false;

            $response = $client->request('POST');
            $response_code = $response->getStatus();

            // Some 5xx errors are expected, so retry automatically
            if ($response_code >= 500 && $response_code < 600 && $retry_count <= 5) {
                $retry = true;
                $retry_count++;
                sleep($retry_count / 4 * $retry_count);
            }
        } while ($retry);
		
		return $response;
	}

	private function _setError(Zend_Http_Response $response)
	{
		$xml = new SimpleXMLElement($response->getBody());
		$this->_errorCode = (string) $xml->Error->Code;
		$this->_errorMessage = (string) $xml->Error->Message;
	}
	
	public function getErrorMessage()
	{
		return $this->_errorMessage;
	}
	
	public function getErrorCode()
	{
		return $this->_errorCode;
	}

	private function _getSignature($string)
	{
		return base64_encode(hash_hmac('sha256', $string, $this->_getSecretKey(), true));
	}
}
