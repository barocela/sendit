<?php
require('soap-wsse.php');
require('soap-wsa.php');

class TereClient extends SoapClient {

   
   function __doRequest($request, $location, $saction, $version) {
        $dom_obj = new DOMDocument();
        $dom_obj->loadXML($request);

        $objWSA = new WSASoap($dom_obj);
//        $objWSA->addAction($saction);
//        $objWSA->addTo($location);
//        $objWSA->addMessageID();
//        $objWSA->addReplyTo();

        $dom = $objWSA->getDoc();

        $objWSSE = new WSSESoap($dom, true, false);
        /* Sign all headers to include signing the WS-Addressing headers */
        $objWSSE->signAllHeaders = FALSE;

        $objWSSE->addTimestamp();

        /* create new XMLSec Key using RSA SHA-1 and type is private key */
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
        $objKey->passphrase=PASSPHRASE;
        
        /* load the private key from file - last arg is bool if key in file (TRUE) or is string (FALSE) */
        $objKey->loadKey(PRIVATEKEY, TRUE);

        /* Sign the message - also signs appropraite WS-Security items */
        $objWSSE->signSoapDoc($objKey);

        $token = $objWSSE->addBinaryToken(file_get_contents(CERT), TRUE);
        
        /* Add certificate (BinarySecurityToken) to the message and attach pointer to Signature */
        $objWSSE->attachTokentoSig($token);

        $request = $objWSSE->saveXML();
        
//        echo $request; die; //si descomentareas aqui puedes ver lo que esta enviando
        return parent::__doRequest($request, $location, $saction, $version);
   }
   
}
?>
