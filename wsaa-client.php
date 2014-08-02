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
define("TEREWSDL", "wsdl/terews.xml");
define("WSTEREURL", "https://secure.aduana.gov.py/test/tere/serviciotere?wsdl");
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

function addGguia() {
    $guia = <<<EOD
<guiaMadre>
<idLoteRemesa>14XXX0000000011P</idLoteRemesa>
<codAduana>704</codAduana>
<codEmpresa>666</codEmpresa>
<medio>2</medio>
<fecArribo>20141203</fecArribo>
<paisTrans>512</paisTrans>
<paisMedTrans>512</paisMedTrans>
<paisCodProc>400</paisCodProc>
<guiasHija>
<guiaHija>
<nroHijo>1588000774</nroHijo>
<destinatario>destinatario particular</destinatario>
<tipoOperacion>SNTD</tipoOperacion>
<valorDol>5000</valorDol>
<tipoPaquete>COU</tipoPaquete>
<primeraFraccion>N</primeraFraccion>
<maniPrimeraFraccion></maniPrimeraFraccion>
<sujetoControl>N</sujetoControl>
<paisOrigen>512</paisOrigen>
<paisProc>400</paisProc>
<lineas>
<linea>
<numeroTicket>1255429</numeroTicket>
<cantBultosPar>1</cantBultosPar>
<pesoBultosPar>3.445</pesoBultosPar>
<cantBultosTot>1</cantBultosTot>
<pesoBultosTot>3.445</pesoBultosTot>
<naturalezaMercaderia>DIPLOMATIC
BAG</naturalezaMercaderia>
<codArmonizado>4823.40.00.000</codArmonizado>
</linea>
<linea>
<numeroTicket>1255430</numeroTicket>
<cantBultosPar>1</cantBultosPar>
<pesoBultosPar>3.445</pesoBultosPar>
<cantBultosTot>1</cantBultosTot>
<pesoBultosTot>3.445</pesoBultosTot>
<naturalezaMercaderia>DIPLOMATIC
BAG</naturalezaMercaderia>
<codArmonizado>4823.40.00.000</codArmonizado>
</linea>
</lineas>
</guiaHija>
</guiasHija>
</guiaMadre>
EOD;

//$results = $client2->__getFunctions();
    $TA = autenticate();
    $client = createClient(TEREWSDL, WSTEREURL);
    $results = $client->agregarGuia($TA, $guia);

    echo "<pre>";
    var_dump($results);
    die;

    if (is_soap_fault($results)) {
        exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    }
}


include("csv2xml.php");

function convert($filename){
    //Create the instance of the class
    $csv2xml = new csv2xml();
    //Set the root node for the XML
    $csv2xml->setRootNode("guiaMadre");
    // Set the recurring node for the XML
    $csv2xml->setRecurringNode("guiaHija"); 
    $csv2xml->setCSVFile($filename.".csv"); 
    //Convert the file
    $csv2xml->convertCSV2XML();     
}

addGguia();

?>
