<?php

namespace furkankadioglu\eFatura;

use Exception;
use GuzzleHttp\Client;
use Rhumsaa\Uuid\Uuid;
use furkankadioglu\eFatura\Models\Invoice;
use furkankadioglu\eFatura\Models\UserInformations;

class InvoiceManager {
    /**
     * Api Urls
     */
    const BASE_URL = "https://earsivportal.efatura.gov.tr";
    const TEST_URL = "https://earsivportaltest.efatura.gov.tr";

    /**
     * Username field for auth
     *
     * @var string
     */
    protected $username;

    /**
     * Password field for auth
     *
     * @var string
     */
    protected $password;

    /**
     * Guzzle client variable
     *
     * @var GuzzleHttp\Client
     */
    protected $client;

    /**
     * Session Token
     *
     * @var string
     */
    protected $token;

    /**
     * Language
     *
     * @var string
     */
    protected $language = "TR";

    /**
     * Current targeted invoice
     *
     * @var furkankadioglu\eFatura\Models\Invoice
     */
    protected $invoice;

    /**
     * Referrer variable
     *
     * @var string
     */
    protected $referrer;

    /**
     * Debug mode
     *
     * @var boolean
     */
    protected $debugMode = false;

    /**
     * Invoices
     *
     * @var array furkankadioglu\eFatura\Models\Invoice
     */
    protected $invoices = [];

    /**
     * User Informations
     *
     * @var furkankadioglu\eFatura\Models\UserInformations
     */
    protected $userInformations;

    /**
     * Base headers
     *
     * @var array
     */
    protected $headers = [
        "accept" => "*/*",
        "accept-language" => "tr,en-US;q=0.9,en;q=0.8",
        "cache-control" => "no-cache",
        "content-type" => "application/x-www-form-urlencoded;charset=UTF-8",
        "pragma" => "no-cache",
        "sec-fetch-mode" => "cors",
        "sec-fetch-site" => "same-origin",

    ];

    /**
     * Base construct method for guzzle and connection settings
     */
    public function __construct()
    {
        $this->referrer = $this->getBaseUrl()."/intragiris.html";
        $this->headers["referrer"] = $this->referrer;

        $this->client = new Client($this->headers);
    }

    /**
     * Setter function for username
     *
     * @param string $username
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Set a debug mode
     *
     * @param boolean $status
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function setDebugMode($status)
    {
        $this->debugMode = $status;
        return $this;
    }

    /**
     * Setter function for password
     *
     * @param string $password
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function setTestCredentials()
    {
        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/esign", [
            "form_params" => [
                "assoscmd" => "kullaniciOner",
                "rtype" => "json",
            ]
        ]);
        $body = json_decode($response->getBody(), true);

        $this->checkError($body);
        $this->username = $body["userid"];
        $this->password = "1";
        return $this;
    }

    /**
     * Setter function for all credentials
     *
     * @param string $username
     * @param string $password
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function setCredentials($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Setter function for token
     *
     * @param string $token
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function setToken($token) 
    {
        $this->token = $token;
        return $token;
    }

    /**
     * Connect with credentials
     *
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function connect() {
        $this->getTokenFromApi();
        return $this;
    }

    /**
     * Get all credentials as an array
     *
     * @return array
     */
    public function getCredentials()
    {
        return [
            $this->username,
            $this->password
        ];
    }

    /**
     * Get base url 
     *
     * @return string
     */
    public function getBaseUrl()
    {
        if($this->debugMode)
        {
            return self::TEST_URL;
        }
        return self::BASE_URL;
    }

    /**
     * Get auth token
     *
     * @return string
     */
    public function getTokenFromApi()
    {
        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/assos-login", [
            "form_params" => [
                "assoscmd" => $this->debugMode ? "login" : "anologin",
                "rtype" => "json",
                "userid" => $this->username,
                "sifre" => $this->password,
                "sifre2" => $this->password,
                "parola" => "1"
                
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $this->token = $body["token"];

    }

    /**
     * Check error, if exist throw it!
     *
     * @param array $jsonData
     * @return void
     */
    private function checkError($jsonData)
    {
        if(isset($jsonData["error"]))
        {
            throw new Exception("Bir hata oluştu! \t \n".print_r($jsonData));
        }
    }

    /**
     * Setter function for invoice
     *
     * @param Invoice $invoice
     * @return furkankadioglu\eFatura\InvoiceManager
     */
    public function setInvoice(Invoice $invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }

    /**
     * Getter function for invoice
     *
     * @return furkankadioglu\eFatura\Models\Invoice
     */
    public function getInvoice()
    {
        return $this->invoice;
    }

    /**
     * Get invoices from api
     *
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getInvoicesFromAPI($startDate, $endDate)
    {

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            
                "headers" => $this->headers,
                "form_params" => [
                    "cmd" => "EARSIV_PORTAL_TASLAKLARI_GETIR",
                    "callid" => Uuid::uuid1()->toString(),
                    "pageName" => "RG_BASITTASLAKLAR",
                    "token" => $this->token,
                    "jp" => '{"baslangic":"'.$startDate.'","bitis":"'.$endDate.'","table":[]}'
                ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $body;
    }

    /**
     * Get main three menu from api
     *
     * @return array
     */
    public function getMainTreeMenuFromAPI()
    {
        $parameters = [
            "cmd" => "getUserMenu",
            "callid" => Uuid::uuid1()->toString(),
            "pageName" => "MAINTREEMENU",
            "token" => $this->token,    
            "jp" => '{"ANONIM_LOGIN":"1"}'
        ];

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => [
                "referrer" => $this->referrer
            ],
            "form_params" => $parameters
        ]);
        

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $body["data"];
    }

    /**
     * Create draft basic invoice
     *
     * @param Invoice $invoice
     * @return furkankadioglu\eFatura\Models\Invoice
     */
    public function createDraftBasicInvoice(Invoice $invoice = null)
    {
        if($invoice != null)
        {
            $this->invoice = $invoice;
        }

        if($this->invoice == null)
        {
            throw new Exception("Invoice null");
        }

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_FATURA_OLUSTUR",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_FATURA",
                "token" => $this->token,
                "jp" => "".json_encode($this->invoice->export()).""
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        if($body["data"] != "Faturanız başarıyla taslaklara eklenmiştir.")
        {
            throw new Exception("Fatura oluşturulamadı.");
        }

        return $this;
    }

    /**
     * Sign a certificate with device
     *
     * @param Invoice $invoice
     * @return void
     */
    public function signDraftInvoiceWithDevice(Invoice $invoice = null)
    {
        if($invoice != null)
        {
            $this->invoice = $invoice;
        }

        if($this->invoice == null)
        {
            throw new Exception("Invoice null");
        }

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_FATURA_HSM_CIHAZI_ILE_IMZALA",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_BASITTASLAKLAR",
                "token" => $this->token,
                "imzalanacaklar" => [$this->invoice->getSummary()]
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $this;
    }

    /**
     * Get html invoice
     *
     * @param Invoice $invoice
     * @return void
     */
    public function getInvoiceHTML(Invoice $invoice = null, $signed = true)
    {
        if($invoice != null)
        {
            $this->invoice = $invoice;
        }

        if($this->invoice == null)
        {
            throw new Exception("Invoice null");
        }

        $data = [
                "ettn" => $this->invoice->getUuid(),
                "onayDurumu" => $signed ? "Onaylandı" : "Onaylanmadı"
        ];

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_FATURA_GOSTER",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_TASLAKLAR",
                "token" => $this->token,
                "jp" => "".json_encode($data)."",
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $body["data"];
    }

    /**
     * Cancel an invoice
     *
     * @param Invoice $invoice
     * @return boolean
     */
    public function cancelInvoice(Invoice $invoice = null, $reason = "Yanlış İşlem")
    {
        if($invoice != null)
        {
            $this->invoice = $invoice;
        }

        if($this->invoice == null)
        {
            throw new Exception("Invoice null");
        }

        $data = [
            "silinecekler" => [$this->invoice->getSummary()],
            "aciklama" => $reason
        ];

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_FATURA_SIL",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_BASITTASLAKLAR",
                "token" => $this->token,
                "jp" => "".json_encode($data)."",
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        if($body["data"] != "İptal edildi.")
        {
            throw new Exception("Fatura iptal edilemedi.");
        }

        return true;
    }

    /**
     * Get an invoice from API
     *
     * @param Invoice $invoice
     * @return array
     */
    public function getInvoiceFromAPI(Invoice $invoice = null)
    {
        if($invoice != null)
        {
            $this->invoice = $invoice;
        }

        if($this->invoice == null)
        {
            throw new Exception("Invoice null");
        }

        $data = [
            "ettn" => $this->invoice->getUuid()
        ];

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_FATURA_GETIR",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_BASITFATURA",
                "token" => $this->token,
                "jp" => "".json_encode($data)."",
            ]
        ]);

        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $body["data"];
    }

    /**
     * Get download url
     *
     * @param Invoice $invoice
     * @param boolean $signed
     * @return string
     */
    public function getDownloadURL(Invoice $invoice = null, $signed = true)
    {
        if($invoice != null)
        {
            $this->invoice = $invoice;
        }

        if($this->invoice == null)
        {
            throw new Exception("Invoice null");
        }
        
        $signed = $signed ? "Onaylandı" : "Onaylanmadı";

        return $this->getBaseUrl()."/earsiv-services/download?token={$this->token}&ettn={$this->invoice->getUuid()}&belgeTip=FATURA&onayDurumu={$signed}&cmd=downloadResource";
    }

    /**
     * Set invoice manager user informations
     *
     * @param furkankadioglu\eFatura\Models\UserInformations $userInformations
     * @return furkankadioglu\eFatura\Models\Invoice
     */
    public function setUserInformations(UserInformations $userInformations)
    {
        $this->userInformations = $userInformations;
        return $this;
    }

    /**
     * Get invoice manager user informations
     *
     * @return furkankadioglu\eFatura\Models\UserInformations
     */
    public function getUserInformations()
    {
        return $this->userInformations;
    }

    /**
     * Get user informations data
     *
     * @return furkankadioglu\eFatura\Models\UserInformations
     */
    public function getUserInformationsData()
    {
        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_KULLANICI_BILGILERI_GETIR",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_KULLANICI",
                "token" => $this->token,
                "jp" => "{}",
            ]
        ]);
        
        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        $userInformations = new UserInformations($body["data"]);
        return $this->userInformations = $userInformations;
    }

    /**
     * Send user informations data
     *
     * @param Invoice $invoice
     * @return array
     */
    public function sendUserInformationsData(UserInformations $userInformations = null)
    {
        if($userInformations != null)
        {
            $this->userInformations = $userInformations;
        }

        if($this->userInformations == null)
        {
            throw new Exception("User informations null");
        }
        

        $response = $this->client->post($this->getBaseUrl()."/earsiv-services/dispatch", [
            "headers" => $this->headers,
            "form_params" => [
                "cmd" => "EARSIV_PORTAL_KULLANICI_BILGILERI_KAYDET",
                "callid" => Uuid::uuid1()->toString(),
                "pageName" => "RG_KULLANICI",
                "token" => $this->token,
                "jp" => "".json_encode($this->userInformations->export())."",
            ]
        ]);
        
        $body = json_decode($response->getBody(), true);

        $this->checkError($body);

        return $body["data"];
    }

    


}

?>