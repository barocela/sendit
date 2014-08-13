<?php

include("csv2xml.php");
include("TereClient.php");

class Autenticacion {

    public $codAduana;
    public $firmaWSAA;
    public $idUsuario;
    public $ticketWSAA;

}

class linea {

    public $cantBultosPar;
    public $cantBultosTot;
    public $codArmonizado;
    public $naturalezaMercaderia;
    public $numeroTicket;
    public $pesoBultosPar;
    public $pesoBultosTot;

}

class guiaHija {

    public $destinatario;
    public $lineas;
    public $maniPrimeraFraccion;
    public $nroHijo;
    public $paisOrigen;
    public $paisProc;
    public $primeraFraccion;
    public $sujetoControl;
    public $tipoOperacion;
    public $tipoPaquete;
    public $valorDol;

}

class guiaMadre {
    public $guiasHija;
    public $codAduana;
    public $codEmpresa;
    public $fecArribo;
    public $idLoteRemesa;
    public $medio;
    public $paisCodProc;
    public $paisMedTrans;
    public $paisTrans;
}


class terews{
    public $guiasMadre;
}

class guia{
    public $terews;
}


class AuthObject {

    function AuthObject($autenticacion) {
        $this->autenticacion = $autenticacion;
    }

}

class Manifiesto {

    function Manifiesto($idSofia, $manifiesto, $prefijo, $titulo) {
        $this->idSofia = $idSofia;
        $this->manifiesto = $manifiesto;
        $this->prefijo = $prefijo;
        $this->titulo = $titulo;
    }

}

class consultarGuia {

    function consultarGuia($fechaDesde, $fechaHasta, $autenticacion) {
        $this->fechaDesde = $fechaDesde;
        $this->fechaHasta = $fechaHasta;
        $this->autenticacion = $autenticacion;
    }

}

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
define("TERE_WSDL", "https://secure.aduana.gov.py/test/wsdl/tere/serviciotere");
define("TERE_URL", "https://secure.aduana.gov.py/test/tere/serviciotere");

define("REFERENCIA_URL", "https://secure.aduana.gov.py/test/tere/servicioreferencia");
define("REFERENCIA_WSDL", "https://secure.aduana.gov.py/test/wsdl/tere/servicioreferencia");
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

    if (!file_put_contents("generate/TA.xml", $TA)) {
        exit("Error writing generate/TA.xml\n");
    }

    $xmlresult = simplexml_load_string($TA);

    $data = $xmlresult->credentials;
    $firma = (string) $data->sign;
    $token = (string) $data->token;

    $autentication = new Autenticacion();
    $autentication->codAduana = "704";
    $autentication->firmaWSAA = $firma;
    $autentication->idUsuario = "courier.sendit";
    $autentication->ticketWSAA = $token;

    return $autentication;
}

function createClient($wsdl, $url, $wsse = false) {

    $options = array();
    $options['location'] = $url;
    $options['trace'] = 1;
    $options['exceptions'] = 0;
//    $options['features'] = SOAP_SINGLE_ELEMENT_ARRAYS;
    $options['soap_version'] = SOAP_1_2;

    if (PROXY) {
        $options['proxy_host'] = "10.104.0.172";
        $options['proxy_port'] = "1111";
    }

    if ($wsse) {
        $client = new TereClient($wsdl, $options);
    } else {
        $client = new SoapClient($wsdl, $options);
    }
    return $client;
}

function getAduanas() {

    $client = createClient(REFERENCIA_WSDL, REFERENCIA_URL);
    $autentication = autenticate();


    $objAuth = new AuthObject($autentication);
    $autentication_obj = new SoapVar($objAuth, SOAP_ENC_OBJECT);

//    $results = $client->getAduanas($autentication);
    $results = $client->getMoneda($autentication_obj);

    if (is_soap_fault($results)) {
        exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    } else {
        echo "<pre>";
        print_r($results);
        die;
    }
}

function addGuia() {

    $client = createClient(TERE_WSDL, TERE_URL, true);
    $autentication = autenticate();

    $objAuth = new AuthObject($autentication);
    $autentication_obj = new SoapVar($objAuth, SOAP_ENC_OBJECT);

    
    $linea = new linea();
    $linea->cantBultosPar = 1;
    $linea->cantBultosTot=1;
    $linea->codArmonizado = 1901.01;
    $linea->naturalezaMercaderia = "DULDE DE LECHE";
    $linea->numeroTicket = 1;
    $linea->pesoBultosPar = 4.51;
    $linea->pesoBultosTot = 4.51;
    
    $guia_hija = new guiaHija();
    $guia_hija->destinatario = "Juan Perez";
    $guia_hija->lineas=array($linea);
    $guia_hija->maniPrimeraFraccion='';
    $guia_hija->nroHijo = "000138446";
    $guia_hija->paisOrigen = 528;
    $guia_hija->primeraFraccion = "N";
    $guia_hija->sujetoControl = "N";
    $guia_hija->tipoOperacion = "SNTD";
    $guia_hija->tipoPaquete = "COU";
    $guia_hija->valorDol = 15;
    
    $guia_madre = new guiaMadre();
    $guia_madre->codAduana = 704;
    $guia_madre->codEmpresa = 681;
    $guia_madre->fecArribo = "20140127";
    $guia_madre->idLoteRemesa = "14XXX0000000719C";
    $guia_madre->medio = 2;
    $guia_madre->paisCodProc = 512;
    $guia_madre->paisMedTrans = 512;
    $guia_madre->paisTrans = 512;
    $guia_madre->guiasHija = array($guia_hija);
    
    $terews = new terews();
    $terews->guiasMadre= array($guia_madre);
    
    $guia = new guia();
    $guia->terews = array($terews);
    
    $results = $client->agregarGuia($guia, $autentication_obj);

    echo "<pre>";
    print_r($client->__getLastRequest());
    die;
    
    if (is_soap_fault($results)) {
        exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    } else {
        echo "<pre>";
        print_r($results);
        die;
    }
}

function consultarListaGuias() {
    $autentication = autenticate();
    $client = createClient(TERE_WSDL, TERE_URL, true);

    $consultar_guia = new consultarGuia("20140301", "20140801", $autentication);
    $params = new SoapVar($consultar_guia, SOAP_ENC_OBJECT);

    $client->consultarListaGuias($params);
    $results = $client->__getLastRequestHeaders();

    if (is_soap_fault($results)) {
        exit("error: " . $results->faultcode . "\n" . $results->faultstring . "\n");
    } else {
        echo "<pre>";
        print_r($results);
        die;
    }
}

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

//consultarGuia();
//getAduanas();
//consultarListaGuias();

addGuia();
?>
