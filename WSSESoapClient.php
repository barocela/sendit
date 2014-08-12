<?php

class WSSESoapClient extends SoapClient {                                                                                           
	protected $wsseUser;
	protected $wssePassword;

    public function setWSSECredentials($user, $password) {
        $this->wsseUser = $user;
        $this->wssePassword = $password;
    }

    public function __doRequest($request, $location, $action, $version) {
        if (!$this->wsseUser or !$this->wssePassword) {
            return parent::__doRequest($request, $location, $action, $version);
        }

        // get SOAP message into DOM
        $dom = new DOMDocument();
        $dom->loadXML($request);
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('SOAP-ENV', 'http://schemas.xmlsoap.org/soap/envelope/');

        // search for SOAP header, create one if not found
        $header = $xp->query('/SOAP-ENV:Envelope/SOAP-ENV:Header')->item(0);
        if (!$header) {
            $header = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'SOAP-ENV:Header');
            $envelope = $xp->query('/SOAP-ENV:Envelope')->item(0);
            $envelope->insertBefore($header, $xp->query('/SOAP-ENV:Envelope/SOAP-ENV:Body')->item(0));
        }

        // add WSSE header
        $usernameToken = $dom->createElementNS('http://schemas.xmlsoap.org/ws/2002/07/secext', 'wsse:UsernameToken');
        $username = $dom->createElementNS('http://schemas.xmlsoap.org/ws/2002/07/secext', 'wsse:Username', $this->wsseUser);
        $password = $dom->createElementNS('http://schemas.xmlsoap.org/ws/2002/07/secext', 'wsse:Password', $this->wssePassword);
        $usernameToken->appendChild($username);
        $usernameToken->appendChild($password);
        $header->appendChild($usernameToken);

        // perform SOAP call
        $request = $dom->saveXML();
        return parent::__doRequest($request, $location, $action, $version);
    }

} // class WSSESoapClient
?>
