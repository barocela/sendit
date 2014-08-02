<?php

# Author: Gerardo Fisanotti - DvSHyS/DiOPIN/AFIP - 13-apr-07
# Function: Get an authorization ticket (TA) from AFIP WSAA
# Input:
#        WSDL, CERT, PRIVATEKEY, PASSPHRASE, SERVICE, WSAAURL, DESTINATIONDN
#        Check below for its definitions
# Output:
#        TA.xml: the authorization ticket as granted by WSAA.
#        TOKEN.txt: The Token field, parsed from TA if you are too lazy to parse
#        SIGN.txt: The Sign field, parsed from TA for the lazy ones.
#==============================================================================
$user = "courier.sendit";
define("WSDL", "server.xml");     # The WSDL corresponding to WSAA
define("TEREWSDL", "terews.xml");
define("CERT", "ready.crt.pem");       # The X.509 obtained from Seg. Inf.
define("PRIVATEKEY", "ready.key.pem"); # The private key correspoding to CERT
define("PASSPHRASE", "Hackeruno2@"); # The passphrase (if any) to sign
# SERVICE: The WS service name you are asking a TA for
#define ("SERVICE", "wdepmovimientos");
#define ("SERVICE", "wsfe");
define("SERVICE", "serviciotere");
# WSAAURL: the URL to access WSAA, check for http or https and wsaa or wsaahomo
define("WSAAURL", "https://secure.aduana.gov.py/test/wsaa/server?wsdl/LoginCms");
# DESTINATIONDN must contain the WSAA dn, it must be exactly as follows, you
# should only change the "cn" portion, it should be "wsaahomo" for the testing
# WSAA or "wsaa" for the production WSAA.
define("DESTINATIONDN", "C=py, O=dna, OU=sofia, CN=wsaatest");


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
    $TRA->asXML('TRA.xml');
}

#==============================================================================
# This functions makes the PKCS#7 signature using TRA as input file, CERT and
# PRIVATEKEY to sign. Generates an intermediate file and finally trims the 
# MIME heading leaving the final CMS required by WSAA.

function SignTRA() {
    $STATUS = openssl_pkcs7_sign("TRA.xml", "TRA.tmp", "file://" . CERT, array("file://" . PRIVATEKEY, PASSPHRASE), array(), !PKCS7_DETACHED
    );
    if (!$STATUS) {
        exit("ERROR generating PKCS#7 signature\n");
    }
    $inf = fopen("TRA.tmp", "r");
    $i = 0;
    $CMS = "";
    while (!feof($inf)) {
        $buffer = fgets($inf);
        if ($i++ >= 4) {
            $CMS.=$buffer;
        }
    }


    fclose($inf);
    unlink("TRA.xml");
    unlink("TRA.tmp");
    return $CMS;
}

#==============================================================================

function CallWSAA($CMS) {
    $client = new SoapClient(WSDL, array('soap_version' => SOAP_1_2,
        'location' => WSAAURL,
        'trace' => 1,
        'exceptions' => 0, # To disable exceptions
        'proxy_host' => "10.104.0.172",
        'proxy_port' => "1111"
    ));


    $results = $client->loginCms($CMS);
    if (is_soap_fault($results)) {
        exit("SOAP Fault: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    }
    //return $results->loginCmsReturn;
    return $results;
}

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

if (!file_put_contents("TA.xml", $TA)) {
    exit("Error writing TA.xml\n");
}

//Conectandome a un WS

$client2 = new SoapClient(TEREWSDL, array('soap_version' => SOAP_1_2,
    'location' => "https://secure.aduana.gov.py/test/tere/serviciotere",
    'trace' => 1,
    'exceptions' => 0, # To disable exceptions
    'proxy_host' => "10.104.0.172",
    'proxy_port' => "1111"
));


$guia = <<<EOD
<terews>
<guiasMadre>
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
</guiasMadre>
</terews>
EOD;

//$results = $client2->__getFunctions();
$results = $client2->agregarGuia($TA, $guia);

if (is_soap_fault($results)) {
    exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
}

echo "<pre>";
print_r($results);
die;
?>
