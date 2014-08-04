<?php

ini_set('display_errors', 1);

define("USER", "courier.sendit");
define("WSDL", "wsdl/server.xml");     # The WSDL corresponding to WSAA
define("CERT", "certificate/ready.crt.pem");       # The X.509 obtained from Seg. Inf.
define("PRIVATEKEY", "certificate/ready.key.pem"); # The private key correspoding to CERT
define("PASSPHRASE", "Hackeruno2@"); # The passphrase (if any) to sign
# SERVICE: The WS service name you are asking a TA for
#define ("SERVICE", "wdepmovimientos");
#define ("SERVICE", "wsfe");
define("SERVICE", "serviciotere");
define("WSAAURL", "https://secure.aduana.gov.py/test/wsaa/server?wsdl/LoginCms");
# WSAAURL: the URL to access WSAA, check for http or https and wsaa or wsaahomo
define("TEREWSDL", "wsdl/serviciotere.xml");
define("WSTEREURL", "https://secure.aduana.gov.py/test/tere/serviciotere?wsdl");

define("WSREFERENCIA", "wsdl/reference.xml");
define("WSREFERENCIAURL", "https://secure.aduana.gov.py/test/tere/servicioreferencia?wsdl");
# DESTINATIONDN must contain the WSAA dn, it must be exactly as follows, you
# should only change the "cn" portion, it should be "wsaahomo" for the testing
# WSAA or "wsaa" for the production WSAA.
define("DESTINATIONDN", "C=py, O=dna, OU=sofia, CN=wsaatest");
define("PROXY", true);

//define("PROXY", false); //usar esta rey
# You shouldn't have to change anything below this line!!!
#==============================================================================

function CreateTRA() {
    $TRA = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?>' .
            '<loginTicketRequest version="1.0">' .
            '</loginTicketRequest>');
    $TRA->addChild('header');
# Now we extract the distinguished name from the CERT and we re-order it 
# according to RFC 2253, that is what WSAA expects to receive.
    $certdata = openssl_x509_parse(file_get_contents(CERT));
    $DN = "";
    foreach ($certdata['subject'] as $key => $value) {
        if ($DN != "")
            $DN = "," . $DN;
        $DN = $key . "=" . $value . $DN;
    }
    $TRA->header->addChild('source', $DN);
    $TRA->header->addChild('destination', DESTINATIONDN);
    $TRA->header->addChild('uniqueId', date('U'));
    $TRA->header->addChild('generationTime', date('c', date('U') - 600));
    $TRA->header->addChild('expirationTime', date('c', date('U') + 600));
    $TRA->addChild('service', SERVICE);
    $TRA->asXML('generate/TRA.xml');
}

#==============================================================================
# This functions makes the PKCS#7 signature using TRA as input file, CERT and
# PRIVATEKEY to sign. Generates an intermediate file and finally trims the 
# MIME heading leaving the final CMS required by WSAA.

function SignTRA() {
    $STATUS = openssl_pkcs7_sign("generate/TRA.xml", "generate/TRA.tmp", "file://" . CERT, array("file://" . PRIVATEKEY, PASSPHRASE), array(), !PKCS7_DETACHED
    );
    if (!$STATUS) {
        exit("ERROR generating PKCS#7 signature\n");
    }
    $inf = fopen("generate/TRA.tmp", "r");
    $i = 0;
    $CMS = "";
    while (!feof($inf)) {
        $buffer = fgets($inf);
        if ($i++ >= 4) {
            $CMS.=$buffer;
        }
    }


    fclose($inf);
    unlink("generate/TRA.xml");
    unlink("generate/TRA.tmp");
    return $CMS;
}

#==============================================================================

function CallWSAA($CMS) {
    $client = createClient(WSDL, WSAAURL);
    $results = $client->loginCms($CMS);
    if (is_soap_fault($results)) {
        exit("SOAP Fault: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    }
    //return $results->loginCmsReturn;
    return $results;
}

function autenticate() {

    #==============================================================================
    if (!file_exists(CERT)) {
        exit("Failed to open " . CERT . "\n");
    }
    if (!file_exists(PRIVATEKEY)) {
        exit("Failed to open " . PRIVATEKEY . "\n");
    }
    if (!file_exists(WSDL)) {
        exit("Failed to open " . WSDL . "\n");
    }

    CreateTRA();
    $CMS = SignTRA();
    $TA = CallWSAA($CMS);
    $xml = new SimpleXMLElement($TA);

    if (!file_put_contents("generate/TA.xml", $TA)) {
        exit("Error writing generate/TA.xml\n");
    }

    return $TA;
}

function createClient($wsdl, $url) {

    $options = array();
    $options['location'] = $url;
    $options['trace'] = 1;
    $options['exceptions'] = 0;
    $options['soap_version'] = SOAP_1_2;
    if (PROXY) {
        $options['proxy_host'] = "10.104.0.172";
        $options['proxy_port'] = "1111";
    }

    $client = new SoapClient($wsdl, $options);
    return $client;
}

function addManifiesto() {
    $autentication = <<<EOD
            <autenticacion>
                <idUsuario>wsaatest</idUsuario>
                <codAduana>704</codAduana>
                <firma>FXuFJFeBnXLSmpjqQmtpWfbDfaqvNKfI0l+goUYdlQevfdFcaXlovhpKN1ikyBVS0LunHQ8CcFzv/e2ye6Vx6+dvby4UydcsGeKEAtvSJaJxYvZGvHXhJhhqvKXE88dNzyQgFdAeStTXYG0URiSVY+awPV0RUqQWB8GWo3OJsbg=</firma>
                <token>CjxhdXRoPgoJPGlkIHVuaXF1ZV9pZD0iMTQwNzE3MDMxNyIgc3JjPSJDPXB5LCBPPWRuYSwgT1U9c29maWEsIENOPXdzYWF0ZXN0IiBnZW5fdGltZT0iMjAxNC0wOC0wNFQxMjoyODozNy0wNDowMCIgZXhwX3RpbWU9IjIwMTQtMDgtMDRUMTI6NDg6MzctMDQ6MDAiLz4KCTxvcGVyYXRpb24gdmFsdWU9ImdyYW50ZWQiIHR5cGU9ImxvZ2luIj4KCQk8bG9naW4gdWlkPSJDPVBZLE89c2VuZGl0Y291cnJpZXIsT1U9c29maWEsQ049Y291cmllci5zZW5kaXQiIHNlcnZpY2U9InNlcnZpY2lvdGVyZSIgYXV0aG1ldGhvZD0iY21zIj4KCQk8L2xvZ2luPgoJPC9vcGVyYXRpb24+CjwvYXV0aD4K</token>
            </autenticacion>
EOD;
    $manifiesto = <<<EOD
            <manifiesto xmlns="">
                    <idSofia>14704TERE000422F</idSofia>
            </manifiesto>
EOD;

    $TA = autenticate();
    $client = createClient(TEREWSDL, WSTEREURL);

//    $results = $client->agregarGuia($guia, array("codAduana" => "A23434"));
    $results = $client->AsignarManifiesto($manifiesto, $autentication);

    if (is_soap_fault($results)) {
        exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    }
}

function getAduanas() {
//    $autentication = <<<EOD
//            <autenticacion>
//                <idUsuario>wsaatest</idUsuario>
//                <codAduana>704</codAduana>
//                <firma>FXuFJFeBnXLSmpjqQmtpWfbDfaqvNKfI0l+goUYdlQevfdFcaXlovhpKN1ikyBVS0LunHQ8CcFzv/e2ye6Vx6+dvby4UydcsGeKEAtvSJaJxYvZGvHXhJhhqvKXE88dNzyQgFdAeStTXYG0URiSVY+awPV0RUqQWB8GWo3OJsbg=</firma>
//                <token>CjxhdXRoPgoJPGlkIHVuaXF1ZV9pZD0iMTQwNzE3MDMxNyIgc3JjPSJDPXB5LCBPPWRuYSwgT1U9c29maWEsIENOPXdzYWF0ZXN0IiBnZW5fdGltZT0iMjAxNC0wOC0wNFQxMjoyODozNy0wNDowMCIgZXhwX3RpbWU9IjIwMTQtMDgtMDRUMTI6NDg6MzctMDQ6MDAiLz4KCTxvcGVyYXRpb24gdmFsdWU9ImdyYW50ZWQiIHR5cGU9ImxvZ2luIj4KCQk8bG9naW4gdWlkPSJDPVBZLE89c2VuZGl0Y291cnJpZXIsT1U9c29maWEsQ049Y291cmllci5zZW5kaXQiIHNlcnZpY2U9InNlcnZpY2lvdGVyZSIgYXV0aG1ldGhvZD0iY21zIj4KCQk8L2xvZ2luPgoJPC9vcGVyYXRpb24+CjwvYXV0aD4K</token>
//            </autenticacion>
//EOD;

    $TA = autenticate();
    $xml = simplexml_load_string($TA);
    
    $data = $xml->credentials;
    
    $autentication = new stdClass();
    $autentication->idUsuario = 'wsaatest';
    $autentication->codAduana = '704';
    $autentication->firma = (string)$data->sign;
    $autentication->token = (string)$data->token;

    $client = createClient(WSREFERENCIA, WSREFERENCIAURL);
    $results = $client->getAduanas($autentication);

    if (is_soap_fault($results)) {
        exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    }
}

include("csv2xml.php");

function convert($filename) {
    //Create the instance of the class
    $csv2xml = new csv2xml();
    //Set the root node for the XML
    $csv2xml->setRootNode("guiaMadre");
    // Set the recurring node for the XML
    $csv2xml->setRecurringNode("guiaHija");
    $csv2xml->setCSVFile($filename . ".csv");
    //Convert the file
    $csv2xml->convertCSV2XML();
}

getAduanas();
?>
