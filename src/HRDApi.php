<?php

namespace HRDBase\Api;

use HRDBase\Api\Exceptions\HRDApiCommunicationException;
use HRDBase\Api\Exceptions\HRDApiIncorrectDataException;
use HRDBase\Api\Exceptions\HRDApiIncorrectDataReceivedException;

class HRDApi
{
    /* @var HRDApi $instance */
    protected static $instance = [];

    const PERSON = 'person';
    const COMPANY = 'company';

    protected $debug = false;
    protected $verifyPeer = false;
    protected $responseSchemaValidate = true;
    protected $schemaValidate = true;
    protected $host = 'api.test.hrd.pl';
    protected $port = 9999;
    protected $timeout = 3;
    protected $schemaFile = __DIR__ . '/../schemas/api.xsd';
    protected $responseSchemaFile = __DIR__ . '/../schemas/apiResponse.xsd';
    protected $hash = null;
    protected $fp = null;
    protected $token = null;

    /**
     * @param $config array
     * @return HRDApi
     */
    public static function getInstance($config = [])
    {
        return self::getInstanceByPass(
            function () use ($config) {
                return hex2bin($config['apiHash']);
            },
            function () use ($config) {
                return $config['apiLogin'];
            },
            function () use ($config) {
                return $config['apiPass'];
            }
        );
    }

    /**
     * Funkcja do pobrania stanu konta partnera. Zwraca tablicę z saldem konta oraz z zablokowanym środkami na poczet trwających operacji.
     *
     * @return array Tablica zawierająca elementy balance oraz restricted_balance – stan konta oraz zablokowane środki
     */
    public function partnerGetBalance()
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'getBalance');

        return $this->sendArrayResponse($dom, 'partner/getBalance/*');
    }

    /**
     * @return array|bool
     */
    public function pollGet()
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('poll', 'get');

        return $this->sendArrayResponse($dom, 'poll/get/*', ['id', 'objectId'], null, 'poll/get');
    }


    /**
     * @param int $id
     *
     * @return bool
     */
    public function pollAck(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('poll', 'ack');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendVoidResponse($dom, 'poll/ack');
    }


    /**
     * @param int $id
     * @return array|bool
     */
    public function certInfo(int $id)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('cert', 'info');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'cert/info/*');
    }


    /**
     * @param string $product
     * @param string $approverEmail
     * @param string $csr
     * @param mixed  $contact
     * @param bool   $insurance
     * @param int    $user
     * @param int    $period
     * @param string $periodUnit
     * @return int
     * @internal param int $userId
     */
    public function certCreate(
        string $product,
        string $approverEmail,
        string $csr,
        $contact,
        bool $insurance,
        int $user,
        int $period,
        string $periodUnit = null
    ) {
        list($dom, $elem) = $this->getAPIDOMDocument('cert', 'create');
        $elem->appendChild($dom->createElement('product', $product));
        $elem->appendChild($dom->createElement('approverEmail', $approverEmail));
        $elem->appendChild($dom->createElement('csr', $csr));
        if (is_numeric($contact)) {
            $elem->appendChild($dom->createElement('certContactId', $contact));
        } elseif (is_array($contact)) {
            $adminContact = $dom->createElement('adminContact');
            $this->arrayToXml($adminContact, $dom, $contact);
            $elem->appendChild($adminContact);
        }


        $elem->appendChild($dom->createElement('insurance', ($insurance ? 'true' : 'false')));
        $elem->appendChild($dom->createElement('userId', (string)$user));

        $periodElem = $dom->createElement('period', (string)$period);
        if ($periodUnit) {
            $periodElem->setAttribute('unit', $periodUnit);
        }
        $elem->appendChild($periodElem);


        return $this->sendArrayResponse($dom, 'cert/create/*');
    }


    /**
     * @param string $id
     * @param int $period
     * @param string $periodUnit
     * @param string|null $approverEmail
     * @param string|null $csr
     * @return int
     */
    public function certRenew(
        string $id,
        int $period,
        string $periodUnit,
        string $approverEmail = null,
        string $csr = null
    ) {
        list($dom, $elem) = $this->getAPIDOMDocument('cert', 'renew');

        $elem->appendChild($dom->createElement('id', $id));

        $periodElem = $dom->createElement('period', (string)$period);
        if ($periodUnit) {
            $periodElem->setAttribute('unit', $periodUnit);
        }
        $elem->appendChild($periodElem);

        if ($approverEmail) {
            $elem->appendChild($dom->createElement('approverEmail', $approverEmail));
        }

        if ($csr) {
            $elem->appendChild($dom->createElement('csr', $csr));
        }


        return $this->sendIntResponse($dom, 'cert/create/actionId');
    }

    /**
     * @param int|null $lastId
     * @return \Generator
     */
    public function certList(int $lastId = null)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('cert', 'list');

        if ($lastId !== null) {
            $elem->appendChild($dom->createElement('lastId', (string)$lastId));
        }
        yield from $this->sendStringYieldResponse($dom, 'cert/list/id', 'cert/list');
    }

    /**
     * @param int $id
     * @return array|bool
     */
    public function certContactInfo(int $id)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('cert', 'contactInfo');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'cert/contactInfo/*');
    }

    /**
     * Pobiera liste kontaktów certyfikatowych dla podanego CSA
     * @param int $userId
     * @param int|null $lastId
     * @return \Generator
     */
    public function certContactList(int $lastId = null)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('cert', 'contactList');
        if ($lastId !== null) {
            $elem->appendChild($dom->createElement('lastId', (string)$lastId));
        }

        yield from $this->sendStringYieldResponse($dom, 'cert/contactList/id', 'cert/contactList');
    }


    /**
     * funkcja sprawdza czy można zarezerwować i/lub zarejestrować domenę o danej nazwie, tzn. czy nie jest ona obecnie zarejestrowana przez inny podmiot, co czyni ją z założenia dostępną do rejestracji przez nowego abonenta.
     * Jeżeli callback nie jest podany to wtedy funkcja działa jak generator zwracając odpowiednie statusy domen.
     * Możliwe statusy domen:  available, taken, unknown, createOnly
     * available domena jest dostępna do rejestracji
     * taken domena jest zarejestrowana przez inny podmiot i tym samym NIE jest dostępna do rejestracji
     * unknown status domeny nie jest dostępny, spróbuj ponownie później
     * createOnly status występuje dla domen polskich, oznacza on możliwość jedynie bezpośredniej rejestracji domeny, bez jej ówczesnej rezerwacji
     *
     * @param array $domains tablica zawierająca listę domen którą chcesz sprawdzić
     * @param callable|null $callback funkcja która jako pierwszy parametr przyjmuje tablice z domenami I ich statusami
     * @param boolean $partialCallback zmienna określa czy funkcja callback może być wywoływana kilka razy czy musi być wywołana raz
     * @return \Generator
     */
    public function domainCheck(array $domains, callable $callback = null, bool $partialCallback = true)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'check');
        $domainsCount = count($domains);
        for ($i = 0; $i < $domainsCount; $i++) {
            $elem->appendChild($dom->createElement('name', $this->idnToAscii($domains[$i])));
        }
        $this->send($dom);

        $domainsReturnedCount = 0;
        while ($domainsReturnedCount < $domainsCount) {
            $responseDom = $this->read();
            $xpath = new \DOMXPath($responseDom);
            $result = $xpath->query('/api/domain/check/name');
            $domainsInResultCount = $result->length;
            $domainsReturnedCount += $domainsInResultCount;
            $toReturn = [];
            for ($i = 0; $i < $domainsInResultCount; $i++) {
                $item = $result->item($i);
                if ($callback === null) {
                    yield $item->textContent => $item->attributes->item(0)->textContent;
                } else {
                    $toReturn[$item->textContent] = $item->attributes->item(0)->textContent;
                }
            }
            if ($callback !== null && $partialCallback) {
                $callback($toReturn);
            }
        }
        if ($callback !== null && !$partialCallback) {
            $callback($toReturn);
        }
    }

    /**
     * Funkcja służy do pobierania informacji o domenie, domena ta musi być obsługiwana przez konkretnego partnera (nie można pobrać informacji dla domeny która nie jest przez Ciebie obsługiwana
     *
     * @param string $name domena dla której chciałbyś informację
     * @return array tablica w której są umieszczone informację o domenie dla której chciałbyś pobrać informacje
     */
    public function domainInfo(string $name)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'info');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendArrayResponse($dom, 'domain/info/*', [], 'domain/info/ns/', null, null, true);
    }

    /**
     * Parametr $ns dla DNSów opartych w rejestrowanej domenie podawany jest w takim formacie:
     * [['name' => 'ns1.eksampl.pl', 'ipv4'=>'1.2.3.4', 'ipv6'=>'2001:0db8:0000:0000:0000:0000:1428:57ab'], ...]
     * Przy czym określony musi być minimum 1 adres IP danego typu (mogą być podane jednocześnie ipv4 i ipv6
     *
     * @param string $domain Nazwa domeny którą chciałbyś zarejestrować
     * @param int $user ID użytkownika na którą ma być zarejestrowana domena, ID tworzone jest funkcją userCreate
     * @param array $ns Tablica zawierające DNSy które chcemy przypisać do tworzonej domeny, muszą być podane min. 2. Jeżeli DNSy są tworzone w danej nazwie domeny to trzeba podać dla nich adresy IP
     * @param int $period okres w latach na którą ma być zarejestrowana domena
     * @param boolean $privacyProtect Parametr określający czy domena ma być zarejestrowana z usługą ukrywania danych Abonenta domeny w wyszukiwarkach WHOIS jeżeli jest to usługa dostępna dla danego rozszerzenia domen. W przypadku podania wartości true dla domeny która nie obsługuje Privacy protect domena zostanie zarejestrowana z widocznymi danymi w bazach WHOIS
     * @param array $additionalData tablica zawierająca dodatkowe dane wymagane lub opcjonalne do rejestracji tego rozszerzenia domen.
     * @return int ID zlecenia
     */
    public function domainCreate(
        string $domain,
        int $user,
        array $ns,
        int $period = 1,
        bool $privacyProtect = false,
        array $additionalData = null
    ) {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'create');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('user', (string)$user));
        $elem->appendChild($dom->createElement('period', (string)$period));
        $elem->appendChild($dom->createElement('privacyProtect', ($privacyProtect ? 'true' : 'false')));
        $nsElem = $dom->createElement('ns');
        $this->nsToXml($nsElem, $dom, $ns);
        $elem->appendChild($nsElem);
        if ($additionalData !== null) {
            $additionalDataElem = $dom->createElement('additionalData');
            $this->arrayToXml($additionalDataElem, $dom, $additionalData);
            $elem->appendChild($additionalDataElem);
        }

        return $this->sendIntResponse($dom, 'domain/create/actionId');
    }

    /**
     * @param string $domain
     * @param int $csaId
     * @return int
     */
    public function domainBook(string $domain, int $csaId)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'book');
        $elem->appendChild($dom->createElement('user', $csaId));
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));

        return $this->sendIntResponse($dom, 'domain/book/actionId');
    }


    /**
     * Anulowanie cesji/transferu wew. przez przejmującego domene
     * @param int $id
     * @param string $ip
     * @return bool
     */
    public function domainTradeAssigneeCancel(int $id, string $ip)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'tradeAssigneeCancel');
        $elem->appendChild($dom->createElement('id', (string)$id));
        $elem->appendChild($dom->createElement('ip', $ip));

        return $this->sendVoidResponse($dom, 'domain/tradeAssigneeCancel');
    }

    /**
     * Anulowanie cesji/transferu wew. przez zrzekającego się domeny
     * @param int $id
     * @param string $ip
     * @return bool
     */
    public function domainTradeAssignorCancel(int $id, string $ip)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'tradeAssignorCancel');
        $elem->appendChild($dom->createElement('id', (string)$id));
        $elem->appendChild($dom->createElement('ip', $ip));

        return $this->sendVoidResponse($dom, 'domain/tradeAssignorCancel');
    }

    /**
     * @param string $domain
     * @param int $user
     * @param string $pw
     * @param int $period
     * @return int
     */
    public function domainTransfer(string $domain, int $user, string $pw, int $period = 0)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'transfer');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('user', (string)$user));

        if ($period > 0) {
            $elem->appendChild($dom->createElement('period', (string)$period));
        }

        $elem->appendChild($dom->createElement('pw', $pw));
        if ($period) {
            $elem->appendChild($dom->createElement('period', (string)$period));
        }

        return $this->sendIntResponse($dom, 'domain/transfer/actionId');
    }

    /**
     * Status transferu
     * @param int $id
     * @return string
     */
    public function domainTransferStatus(int $id)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'transferStatus');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'domain/transferStatus/*', ['assignorId']);
    }

    /**
     * Potwierdzenie płatności za cesje
     * @param int $id
     * @return bool
     */
    public function domainTradePayerConfirm(int $id)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'tradePayerConfirm');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendVoidResponse($dom, 'domain/tradePayerConfirm');
    }

    /**
     * Cesja oraz trasnfer wewnętrzny - zainicjowanie cesji
     * po zainicjowaniu cesji, zmiana kodu cesji dla domeny nie ma znaczenia
     * @param string $domain
     * @param int $user
     * @param string $pw
     * @return int
     */
    public function domainTrade(string $domain, int $user, string $pw)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'trade');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('user', (string)$user));
        $elem->appendChild($dom->createElement('pw', $pw));

        return $this->sendIntResponse($dom, 'domain/trade/actionId');
    }

    /**
     * Status cesji
     * @param int $id
     * @return string
     */
    public function domainTradeStatus(int $id)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'tradeStatus');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'domain/tradeStatus/*', ['assigneeId', 'assignorId']);
    }

    /**
     * @param string $domain
     * @param string $currentExpirationDate
     * @param int $period
     * @param bool $periodInDays
     *
     * @return int
     */
    public function domainRenew(
        string $domain,
        string $currentExpirationDate,
        int $period = 1,
        bool $periodInDays = false
    ) {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'renew');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('currentExpirationDate', $currentExpirationDate));
        $periodElem = $dom->createElement('period', (string)$period);
        if ($periodInDays) {
            $periodElem->setAttribute('unit', 'd');
        }
        $elem->appendChild($periodElem);

        return $this->sendIntResponse($dom, 'domain/renew/actionId');
    }

    /**
     * Edycja DNSów dla istniejącej domeny
     * @param string $domain
     * @param array $ns
     *
     * @return int
     */
    public function domainUpdate(string $domain, array $ns)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'update');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $nsElem = $dom->createElement('ns');
        $this->nsToXml($nsElem, $dom, $ns);
        $elem->appendChild($nsElem);

        return $this->sendIntOrVoidResponse($dom, 'domain/update', 'domain/update/actionId');
    }

    /**
     * Funkcja służy do pobierania listy obsługiwanych przez Ciebie domen. Funkcja zwraca nieokreśloną liczbę domen
     * aby pobrać kolejną paczkę trzeba podać ostatnią domenę z poprzednio pobranej paczki. Funkcja zwraca domeny jako generator.
     *
     * @param string|null $lastName Parametr w którym należy podać pełną nazwę ostatniej domeny z poprzednio pobranej paczki.
     * @return \Generator
     */
    public function domainList(string $lastName = null)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'list');
        if ($lastName !== null) {
            $elem->appendChild($dom->createElement('lastName', $this->idnToAscii($lastName)));
        }
        yield from $this->sendStringYieldResponse($dom, 'domain/list/name', 'domain/list', true);
    }

    /**
     * funkcja sprawdza czy można zarezerwować i/lub zarejestrować opcję na domenę o danej nazwie, tzn. czy nie jest ona obecnie zarejestrowana przez inny podmiot, co czyni ją z założenia dostępną do rejestracji przez nowego abonenta.
     * Jeżeli callback nie jest podany to wtedy funkcja działa jak generator zwracając odpowiednie statusy domen.
     * Możliwe statusy domen:  available, taken, unknown
     * available opcja jest dostępna do rejestracji
     * taken opcja jest zarejestrowana przez inny podmiot i tym samym NIE jest dostępna do rejestracji
     * unknown status opcji nie jest dostępny, spróbuj ponownie później
     *
     * @param array $domains tablica zawierająca listę domen którą chcesz sprawdzić
     * @param callable|null $callback funkcja która jako pierwszy parametr przyjmuje tablice z domenami I ich statusami
     * @param boolean $partialCallback zmienna określa czy funkcja callback może być wywoływana kilka razy czy musi być wywołana raz
     * @return \Generator
     */
    public function domainFutureCheck(array $domains, callable $callback = null, bool $partialCallback = true)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'futureCheck');
        $domainsCount = count($domains);
        for ($i = 0; $i < $domainsCount; $i++) {
            $elem->appendChild($dom->createElement('name', $this->idnToAscii($domains[$i])));
        }
        $this->send($dom);

        $domainsReturnedCount = 0;
        while ($domainsReturnedCount < $domainsCount) {
            $responseDom = $this->read();
            $xpath = new \DOMXPath($responseDom);
            $result = $xpath->query('/api/domain/futureCheck/name');
            $domainsInResultCount = $result->length;
            $domainsReturnedCount += $domainsInResultCount;
            $toReturn = [];
            for ($i = 0; $i < $domainsInResultCount; $i++) {
                $item = $result->item($i);
                if ($callback === null) {
                    yield $item->textContent => $item->attributes->item(0)->textContent;
                } else {
                    $toReturn[$item->textContent] = $item->attributes->item(0)->textContent;
                }
            }
            if ($callback !== null && $partialCallback) {
                $callback($toReturn);
            }
        }
        if ($callback !== null && !$partialCallback) {
            $callback($toReturn);
        }
    }

    /**
     * Funkcja służy do pobierania informacji o domenie, domena ta musi być obsługiwana przez konkretnego partnera (nie można pobrać informacji dla domeny która nie jest przez Ciebie obsługiwana
     *
     * @param string $name domena dla której chciałbyś informację
     * @return array tablica w której są umieszczone informację o domenie dla której chciałbyś pobrać informacje
     */
    public function domainFutureInfo(string $name)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'futureInfo');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendArrayResponse($dom, 'domain/futureInfo/*', [], null, null, null, true);
    }

    /**
     * Tworzenie opcji na domenę OPCJA zakładana jest na 3 lata, tylko dla .pl
     * @param string $domain
     * @param int $user
     * @return int
     */
    public function domainFutureCreate(string $domain, int $user)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'futureCreate');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('user', (string)$user));

        return $this->sendIntResponse($dom, 'domain/futureCreate/actionId');
    }


    /**
     * @param string $domain
     * @param string $currentExpirationDate
     * @return int
     */
    public function domainFutureRenew(string $domain, string $currentExpirationDate)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'futureRenew');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('currentExpirationDate', $currentExpirationDate));

        return $this->sendIntResponse($dom, 'domain/futureRenew/actionId');
    }

    /**
     * Funkcja służy do pobierania listy obsługiwanych przez Ciebie opcji domen. Funkcja zwraca nieokreśloną liczbę opcji domen
     * aby pobrać kolejną paczkę trzeba podać ostatnią domenę z poprzednio pobranej paczki. Funkcja zwraca domeny jako generator.
     *
     * @param string|null $lastName Parametr w którym należy podać pełną nazwę ostatniej domeny z poprzednio pobranej paczki.
     * @return \Generator
     */
    public function domainFutureList(string $lastName = null)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'futureList');
        if ($lastName !== null) {
            $elem->appendChild($dom->createElement('lastName', $this->idnToAscii($lastName)));
        }
        yield from $this->sendStringYieldResponse($dom, 'domain/futureList/name', 'domain/futureList', true);
    }

    /**
     * @param int $id
     *
     * @return array
     */
    public function domainNsGroupInfo(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'nsGroupInfo');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'domain/nsGroupInfo/*', [], null, null, 'domain/nsGroupInfo/ns/');
    }

    /**
     * @param string $name
     * @param array $ns
     *
     * @return int
     */
    public function domainNsGroupCreate(string $name, array $ns)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'nsGroupCreate');
        $elem->appendChild($dom->createElement('name', $name));
        $this->nsToXmlForGroup($elem, $dom, $ns);

        return $this->sendIntResponse($dom, 'domain/nsGroupCreate/id');
    }

    /**
     * @param int $id
     * @param string|null $name
     * @param array|null $ns
     *
     * @return int
     */
    public function domainNsGroupUpdate(int $id, string $name = null, array $ns = null)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'nsGroupUpdate');
        $elem->appendChild($dom->createElement('id', (string)$id));
        if ($name !== null) {
            $elem->appendChild($dom->createElement('name', $name));
        }
        if ($ns !== null) {
            $this->nsToXmlForGroup($elem, $dom, $ns);
        }

        return $this->sendVoidResponse($dom, 'domain/nsGroupUpdate');
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public function domainNsGroupDelete(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'nsGroupDelete');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendVoidResponse($dom, 'domain/nsGroupDelete');
    }

    /**
     * @param int|null $lastId
     *
     * @return \Generator
     */
    public function domainNsGroupList(int $lastId = null)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'nsGroupList');
        if ($lastId !== null) {
            $elem->appendChild($dom->createElement('lastId', (string)$lastId));
        }
        yield from $this->sendIntYieldResponse($dom, 'domain/nsGroupList/id', 'domain/nsGroupList');
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public function domainWhois(string $name)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'whois');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendStringResponse($dom, 'domain/whois/textResponse');
    }

    /**
     * Funkcja służy do pobrania kontaktów domeny
     *
     * @param string $name
     * @return array|bool
     */
    public function domainContactList(string $name)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'contactList');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendArrayResponse($dom, 'domain/contactList/*', ['registrant', 'tech']);
    }

    /**
     * Funkcja służy do ustawienia kontaktu technicznewgo domeny
     *
     * @param string $name
     * @return array|bool
     */
    public function domainContactSet(string $name, int $techId = null)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'contactSet');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));
        $techElem = $dom->createElement('tech');
        if ($techId) {
            $techElem->appendChild($dom->createElement('contactId', $techId));
        }
        $elem->appendChild($techElem);

        return $this->sendIntOrVoidResponse($dom, 'domain/contactSet', 'domain/contactSet/actionId');
    }

    /**
     * Pobieranie kodu transferu wewnętrznego
     * @param string $name Nazwa domeny
     * @return string Kod cesji
     */
    public function domainTradeGetPw(string $name)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'tradeGetPw');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendStringResponse($dom, 'domain/tradeGetPw/pw');
    }

    /**
     * Pobieranie kodu cesji
     * @param string $name
     * @return string
     */
    public function domainTradeInnerGetPw(string $name)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'tradeInnerGetPw');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendStringResponse($dom, 'domain/tradeInnerGetPw/pw');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function domainEnablePP(string $name)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'enablePP');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendVoidResponse($dom, 'domain/enablePP');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function domainDisablePP(string $name)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'disablePP');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendVoidResponse($dom, 'domain/disablePP');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function domainBuyPP(string $name)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'buyPP');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        return $this->sendVoidResponse($dom, 'domain/buyPP');
    }

    /**
     * @param string $name
     * @return bool
     */
    public function domainDnsChange(string $name, $rrsets)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'dnsChange');
        $elem->appendChild($dom->createElement('name', $name));

        foreach ($rrsets as $rrset) {
            $rrsetElem = $dom->createElement('rrset');
            $this->rrsetToXml($rrsetElem, $dom, $rrset);
            $elem->appendChild($rrsetElem);
        }

        return $this->sendVoidResponse($dom, 'domain/dnsChange');
    }

    /**
     * Ustawia rekord MX dla domeny, działa tylko jesli domena jest zaparkowana na serwerach hrd
     *
     * @param string $name Nazwa domeny
     * @param string $address Adres (ip lub alias)
     * @return bool
     */
    public function domainDnsSetMx(string $name, $address)
    {
        return $this->domainDnsChange($name, [
            [
                'name' => $name . '.',
                'type' => 'MX',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => '10 ' . $address,
                    ]
                ],
            ]
        ]);
    }

    /**
     * Ustawia rekord A dla domeny, działa tylko jesli domena jest zaparkowana na serwerach hrd
     *
     * @param string $name Nazwa domeny
     * @param string $ip Ip na który ma wskazywac domena
     * @return bool
     */
    public function domainDnsSetIp(string $name, $ip)
    {
        return $this->domainDnsChange($name, [
            [
                'name' => $name . '.',
                'type' => 'CNAME',
                'changetype' => 'DELETE'
            ],
            [
                'name' => $name . '.',
                'type' => 'A',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => $ip,
                    ]
                ],
            ]
        ]);
    }

    /**
     * Ustawia rekord CNAME dla domeny, działa tylko jesli domena jest zaparkowana na serwerach hrd
     *
     * @param string $name Nazwa domeny
     * @param string $alias Alias na który ma wskazywac domena
     * @return bool
     */
    public function domainDnsSetAlias(string $name, $alias)
    {
        return $this->domainDnsChange($name, [
            [
                'name' => $name . '.',
                'type' => 'A',
                'changetype' => 'DELETE'
            ],
            [
                'name' => $name . '.',
                'type' => 'CNAME',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => $alias,
                    ]
                ],
            ]
        ]);
    }

    /**
     * Ustawia iframe - pod adresem domany będzie wyswietlał się iframe wskazujacy na podany url, działa tylko jesli domena jest zaparkowana na serwerach hrd
     *
     * @param string $name Nazwa domeny
     * @param string $url Url na który będzie wskazywała domena
     * @return bool
     */
    public function domainDnsSetIframe(string $name, $url)
    {
        return $this->domainDnsChange($name, [
            [
                'name' => $name . '.',
                'type' => 'A',
                'changetype' => 'DELETE'
            ],
            [
                'name' => $name . '.',
                'type' => 'CNAME',
                'ttl' => 3600,
                'changetype' => 'REPLACE',
                'records' => [
                    [
                        'content' => 'dns.test.hrd.pl',
                    ]
                ],
                'comments' => [
                    [
                        'account' => 'redirect',
                        'content' => $url,
                    ]
                ]
            ]
        ]);
    }

    /**
     * @param string $name
     * @return array
     */
    public function domainDnsInfo(string $name)
    {

        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'dnsInfo');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

        // TODO parse response
        return $this->sendArrayResponse($dom, 'domain/dnsInfo/*');
    }

    /**
     * @param string $domain
     * @param bool $privacyProtect
     * @return bool|null
     */
    public function domainPrivacySet(string $domain, bool $privacyProtect)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('domain', 'privacySet');
        $elem->appendChild($dom->createElement('name', $this->idnToAscii($domain)));
        $elem->appendChild($dom->createElement('privacy', ($privacyProtect ? 'true' : 'false')));

        return $this->sendIntOrVoidResponse($dom, 'domain/privacySet', 'domain/privacySet/actionId');
    }

    /**
     * Pobieranie wiadomości
     * @param int $id
     * @return array|bool
     */
    public function newsInfo(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('news', 'info');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'news/info/*', ['id']);
    }

    /**
     * Pobieranie listy wiadomości
     * @param int $lastId
     * @return \Generator
     */
    public function newsList(int $lastId = 0)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('news', 'list');
        if ($lastId !== null) {
            $elem->appendChild($dom->createElement('lastId', (string)$lastId));
        }
        yield from $this->sendIntYieldResponse($dom, 'news/list/id', 'news/list');
    }


    /**
     * Funkcja ta służy do pobierania informacji o użytkowniku.
     *
     * @param int $id id użytkownika
     * @return array tablica zawierająca dane użytkownika
     */
    public function userInfo(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('user', 'info');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'user/info/*', ['id']);
    }

    /**
     * Funkcja służy do tworzenia nowego użytkownika
     *
     * @param string $type person|company Rodzaj użytkownika: osoba fizyczna lub firma
     * @param string $idNumber pesel|nip Numer identyfikujący podmiot: PESEL lub NIP
     * @param string $email Adres e-mail
     * @param string|null $mobilePhone format: +CC.XXXXXXXXX Telefon komórkowy w podanym formacie (CC – Country Code), np. +48.507540869
     * @param string $landlinePhone format: +CC.XXXXXXXXX Telefon kontaktowy (komórkowy lub stacjonarny) w podanym formacie (CC – Country Code), np. +48.221003248
     * @param string|null $fax format: +CC.XXXXXXXXX fax w podanym formacie (CC – Country Code), np. +48.222502913
     * @param string $name Nazwa użytkownika
     * @param string $street Ulica I numer budynku/lokalu
     * @param string $postcode kod pocztowy w formacie prawidłowym dla danego kraju
     * @param string $city Miasto
     * @param string $country Państwo zgodnie z standardem ISO 3166-1 alpha-2
     * @param string|null $representative Osoba reprezentant
     * @return int user id Numer id utworzonego użytkownika
     * @throws HRDApiIncorrectDataException
     */
    public function userCreate(
        $type,
        $idNumber,
        $email,
        $mobilePhone,
        $landlinePhone,
        $fax,
        $name,
        $street,
        $postcode,
        $city,
        $country,
        $representative = null
    ) {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('user', 'create');
        switch ($type) {
            case self::PERSON:
                $elem->appendChild($dom->createElement('personType'));
                break;
            case self::COMPANY:
                $elem->appendChild($dom->createElement('companyType'));
                $elem->appendChild($dom->createElement('representative', $representative));
                break;
            default:
                throw new HRDApiIncorrectDataException('invalid type');
        }
        $elem->appendChild($dom->createElement('idNumber', $idNumber));
        $elem->appendChild($dom->createElement('email', $email));
        if ($mobilePhone !== null) {
            $elem->appendChild($dom->createElement('mobilePhone', $mobilePhone));
        }
        $elem->appendChild($dom->createElement('landlinePhone', $landlinePhone));
        if ($fax !== null) {
            $elem->appendChild($dom->createElement('fax', $fax));
        }
        $elem->appendChild($dom->createElement('name', $name));
        $elem->appendChild($dom->createElement('street', $street));
        $elem->appendChild($dom->createElement('postcode', $postcode));
        $elem->appendChild($dom->createElement('city', $city));
        $elem->appendChild($dom->createElement('country', $country));

        return $this->sendIntResponse($dom, 'user/create/id');
    }

    /**
     * Funkcja służy do aktualizowania danych danego użytkownika
     *
     * @param int $id numer id użytkownika
     * @param string $email Adres e-mail użytkownika
     * @param string|null $mobilePhone format: +CC.XXXXXXXXX Telefon komórkowy w podanym formacie (CC – Country Code), np. +48.507540869
     * @param string|null $landlinePhone format: +CC.XXXXXXXXX Telefon stacjonarny w podanym formacie (CC – Country Code), np. +48.221003248
     * @param string|null $fax format: +CC.XXXXXXXXX fax w podanym formacie (CC – Country Code), np. +48.222502913
     * @param string $name Nazwa użytkownika
     * @param string $street Ulica I numer budynku/lokalu
     * @param string $postcode kod pocztowy w formacie prawidłowym dla danego kraju
     * @param string $city Miasto
     * @param string $country Państwo zgodnie z standardem ISO 3166-1 alpha-2
     * @param string|null $representative Osoba reprezentant
     * @return bool
     */
    public function userUpdate(
        $id,
        $mobilePhone,
        $landlinePhone,
        $fax,
        $street,
        $postcode,
        $city,
        $country
    ) {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('user', 'update');
        $elem->appendChild($dom->createElement('id', $id));

        if ($mobilePhone !== null) {
            $elem->appendChild($dom->createElement('mobilePhone', $mobilePhone));
        }
        if ($landlinePhone !== null) {
            $elem->appendChild($dom->createElement('landlinePhone', $landlinePhone));
        }
        if ($fax !== null) {
            $elem->appendChild($dom->createElement('fax', $fax));
        }
        $elem->appendChild($dom->createElement('street', $street));
        $elem->appendChild($dom->createElement('postcode', $postcode));
        $elem->appendChild($dom->createElement('city', $city));
        $elem->appendChild($dom->createElement('country', $country));

        return $this->sendVoidResponse($dom, 'user/update');
    }

    /**
     * Funkcja służy do pobierania listy obsługiwanych przez Ciebie użytkonikwów.
     * Funkcja zwraca nieokreśloną liczbę użytkowników  aby pobrać kolejną paczkę trzeba podać ostatniego użytkownika z poprzednio pobranej paczki.
     * Funkcja zwraca użytkowników jako generator.
     *
     * @param int $lastId Parametr w którym należy podać id ostatniego użytkownika z poprzednio pobranej paczki.
     * @return \Generator
     */
    public function userList(int $lastId = 0)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('user', 'list');
        if ($lastId !== null) {
            $elem->appendChild($dom->createElement('lastId', (string)$lastId));
        }
        yield from $this->sendIntYieldResponse($dom, 'user/list/id', 'user/list');
    }

    /**
     * Funkcja służy do pobrania listy kontaktów użytkownika
     *
     * @param int $id id użytkownika
     * @return \Generator
     */
    public function userContactList(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('user', 'contactList');
        $elem->appendChild($dom->createElement('id', (string)$id));

        yield from $this->sendIntYieldResponse($dom, 'user/contactList/id', 'user/contactList');
    }

    /**
     * Funkcja ta służy do pobierania informacji o kontakcie
     *
     * @param int $id id kontaktu
     * @return \Generator
     */
    public function userContactInfo(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('user', 'contactInfo');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'user/contactInfo/*');
    }

    /**
     * Wyświetla informacje z bieżącym statusem wykonywanej operacji
     * @param int $id
     * @return array
     */
    public function actionInfo(int $id)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('action', 'info');
        $elem->appendChild($dom->createElement('id', (string)$id));

        return $this->sendArrayResponse($dom, 'action/info/*', ['id'], null, null, null, true);
    }

    /**
     * Pobiera listę wykonywanych operacji
     * @param int $lastId
     * @return \Generator
     */
    public function actionList(int $lastId = 0)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('action', 'list');
        if ($lastId !== null) {
            $elem->appendChild($dom->createElement('lastId', (string)$lastId));
        }
        yield from $this->sendIntYieldResponse($dom, 'action/list/id', 'action/list');
    }

    /**
     * Funkcja słuzy do zalogowania się na konto za pomocą tokena uzyskanego przy poprzednim logowaniu
     *
     * @param callable $hash Funkcja zwracająca hash dla danego konta do którego chcemy się logować
     * @param callable $token Token uzyskany przy poprzednim logowaniu
     * @return HRDApi Instancja klasy HRDApi
     */
    public static function getInstanceByToken(
        callable $hash,
        callable $token
    ) {
        $instance = $token();
        if (!isset(static::$instance[$instance])) {
            static::$instance[$instance] = new static();
            static::$instance[$instance]->setHash($hash);
            static::$instance[$instance]->loginByToken($token);
        }

        return static::$instance[$instance];
    }

    /**
     * Funkcja służy do pobrania instancji klasy na podstawie podanych danych do logowania.
     *
     * @param callable $hash Funkcja zwracająca hash dla danego konta do którego chcemy się logować
     * @param callable $login Funkcja zwracająca login dla danego konta do którego chcemy się logować
     * @param callable $pass Funkcja zwracająca hasło dla danego konta do którego chcemy się logować
     * @param string $type typ konta do którego się logujemy.
     * @return HRDApi Instancja klasy HRDApi
     */
    public static function getInstanceByPass(
        callable $hash,
        callable $login,
        callable $pass,
        string $type = 'partnerApi'
    ) {
        $instance = $type . '#' . $login();
        if (!isset(static::$instance[$instance])) {
            static::$instance[$instance] = new static();
            static::$instance[$instance]->setHash($hash);
            static::$instance[$instance]->loginByPass($login, $pass, $type);
        }

        return static::$instance[$instance];
    }

    /**
     * Pobiera Token aktualnie zalogowanego użytkownika, można wykorzystać do loginByToken
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * HRDApi constructor.
     */
    public function __construct()
    {
    }

    /**
     * HRDApi destructor.
     */
    public function __destruct()
    {
        if ($this->fp !== null) {
            fclose($this->fp);
        }
    }

    /**
     * @param array $toReturn
     * @param \DOMXPath $xpath
     * @param string $prefix
     */
    protected function parseNs(array &$toReturn, \DOMXPath $xpath, string $prefix)
    {
        $toReturn['ns'] = [];
        $resultGroup = $xpath->query($prefix . 'group');
        if ($resultGroup->length === 1) {
            $toReturn['ns']['group'] = (int)$resultGroup->item(0)->textContent;

            return;
        }
        $result = $xpath->query($prefix . 'ns/ns');
        for ($i = 0, $l = $result->length; $i < $l; $i++) {
            $item = $result->item($i);
            $ipv4arr = [];
            $ipv6arr = [];
            $resultIpv4 = $xpath->query('./ipv4/text()', $item);
            for ($i2 = 0, $l2 = $resultIpv4->length; $i2 < $l2; $i2++) {
                $ipv4arr[] = $resultIpv4->item($i2)->textContent;
            }
            $resultIpv6 = $xpath->query('./ipv6/text()', $item);
            for ($i2 = 0, $l2 = $resultIpv6->length; $i2 < $l2; $i2++) {
                $ipv6arr[] = $resultIpv6->item($i2)->textContent;
            }
            $toReturn['ns'][] = [
                'name' => $xpath->query('./name/text()', $item)->item(0)->textContent,
                'ipv4' => $ipv4arr,
                'ipv6' => $ipv6arr,
            ];
        }
    }

    /**
     * @param array $toReturn
     * @param \DOMXPath $xpath
     * @param string $prefix
     */
    protected function parseGroupNs(array &$toReturn, \DOMXPath $xpath, string $prefix)
    {
        $toReturn['ns'] = [];
        $result = $xpath->query($prefix . 'name');
        for ($i = 0, $l = $result->length; $i < $l; $i++) {
            $item = $result->item($i);
            $toReturn['ns'][] = [
                'name' => $item->textContent,
            ];
        }
    }


    /**
     * @param \DOMElement $elem
     * @param \DOMDocument $dom
     * @param array $ns
     */
    protected function nsToXmlForGroup(\DOMElement $elem, \DOMDocument $dom, array $ns)
    {
        foreach ($ns as $item) {
            $nsElem = $dom->createElement('ns');
            $nsElem->appendChild($dom->createElement('name', $item['name']));
            $elem->appendChild($nsElem);
        }
    }

    /**
     * @param \DOMElement $nsElem
     * @param \DOMDocument $dom
     * @param array $ns
     */
    protected function nsToXml(\DOMElement $nsElem, \DOMDocument $dom, array $ns)
    {
        if (isset($ns['group'])) {
            $nsElem->appendChild($dom->createElement('group', $ns['group']));
        } else {
            $nsElem2 = $dom->createElement('ns');
            foreach ($ns as $item) {
                $nsElem3 = $dom->createElement('ns');
                $nsElem3->appendChild($dom->createElement('name', $item['name']));
                if (isset($item['ipv4'])) {
                    foreach ($item['ipv4'] as $ipv4) {
                        $nsElem3->appendChild($dom->createElement('ipv4', $ipv4));
                    }
                }
                if (isset($item['ipv6'])) {
                    foreach ($item['ipv6'] as $ipv6) {
                        $nsElem3->appendChild($dom->createElement('ipv6', $ipv6));
                    }
                }
                $nsElem2->appendChild($nsElem3);
            }
            $nsElem->appendChild($nsElem2);
        }
    }

    /**
     * @param \DOMElement $rrsetElem
     * @param \DOMDocument $dom
     * @param array $rrset
     */
    protected function xmlToRrset(\DOMElement $rrsetElem, \DOMDocument $dom, array $rrset)
    {
        $rrsetElem->appendChild($dom->createElement('name', $rrset['name']));
        $rrsetElem->appendChild($dom->createElement('type', $rrset['type']));
        if (isset($rrset['ttl'])) {
            $rrsetElem->appendChild($dom->createElement('ttl', $rrset['ttl']));
        }
        $rrsetElem->appendChild($dom->createElement('changetype', $rrset['changetype']));

        if (isset($rrset['records'])) {
            foreach ($rrset['records'] as $record) {
                $recordElem = $dom->createElement('record');
                $recordElem->appendChild($dom->createElement('content', $record['content']));
                $recordElem->appendChild($dom->createElement('disabled', ($record['disabled'] ?? false) ? 'true' : 'false'));
                $rrsetElem->appendChild($recordElem);
            }
        }
        if (isset($rrset['comments'])) {
            foreach ($rrset['comments'] as $comment) {
                $commentElem = $dom->createElement('comment');
                $commentElem->appendChild($dom->createElement('content', $comment['content']));
                $commentElem->appendChild($dom->createElement('account', $comment['account']));
                $rrsetElem->appendChild($commentElem);
            }
        }
    }

    /**
     * @param array $toReturn
     * @param \DOMElement $result
     * @internal param \DOMXPath $xpath
     * @internal param string $prefix
     */
    protected function parseRrset(array &$toReturn, \DOMElement $result)
    {
        if (!isset($toReturn['rrset'])) {
            $toReturn['rrsets'] = [];
        }

        $rrset = [
            'records' => [],
            'comments' => [],
        ];
        for ($i = 0, $l = $result->childNodes->length; $i < $l; $i++) {
            $node = $result->childNodes->item($i);

            if (in_array($node->nodeName, ['name', 'type'])) {
                $rrset[$node->nodeName] = $node->nodeValue;
            } else {
                if (in_array($node->nodeName, ['ttl'])) {
                    $rrset[$node->nodeName] = intval($node->nodeValue);
                } else {
                    if (in_array($node->nodeName, ['record', 'comment'])) {
                        $rrset[$node->nodeName][] = $this->parseRrsetRecordOrComment($node);
                    }
                }
            }
        }

        $toReturn['rrsets'][] = $rrset;
    }

    /**
     * @param \DOMXPath $xpath
     * @return array
     */
    protected function parseRrsetRecordOrComment(\DOMElement $result)
    {
        $record = [];

        for ($i = 0, $l = $result->childNodes->length; $i < $l; $i++) {
            $node = $result->childNodes->item($i);

            if ($node->nodeName == 'disabled') {
                $record[$node->nodeName] = $node->nodeValue == 'true';
            } else {
                $record[$node->nodeName] = $node->nodeValue;
            }
        }

        return $record;
    }

    /**
     * @param array $toReturn
     * @param \DOMElement $result
     * @internal param \DOMXPath $xpath
     * @internal param string $prefix
     */
    protected function parseDnsSec(array &$toReturn, \DOMElement $result)
    {
        if (!isset($toReturn['dnssec'])) {
            $toReturn['dnssec'] = [];
        }

        $dnsSecRecord = [];
        for ($i = 0, $l = $result->childNodes->length; $i < $l; $i++) {
            $node = $result->childNodes->item($i);
            if ($node->nodeName == 'record') {
                $dnsSecRecord = $this->parseDnsSecRecord($node);
            }
        }

        $toReturn['dnssec'][] = $dnsSecRecord;
    }

    /**
     * @param \DOMElement $result
     * @return array
     */
    protected function parseDnsSecRecord(\DOMElement $result)
    {
        $record = [];

        for ($i = 0, $l = $result->childNodes->length; $i < $l; $i++) {
            $node = $result->childNodes->item($i);
            $record[$node->nodeName] = $node->nodeValue;
        }

        return $record;
    }


    /**
     * @param \DOMElement $rrsetElem
     * @param \DOMDocument $dom
     * @param array $rrset
     */
    protected function rrsetToXml(\DOMElement $rrsetElem, \DOMDocument $dom, array $rrset)
    {
        $rrsetElem->appendChild($dom->createElement('name', $rrset['name']));
        $rrsetElem->appendChild($dom->createElement('type', $rrset['type']));
        if (isset($rrset['ttl'])) {
            $rrsetElem->appendChild($dom->createElement('ttl', $rrset['ttl']));
        }
        $rrsetElem->appendChild($dom->createElement('changetype', $rrset['changetype']));

        if (isset($rrset['records'])) {
            foreach ($rrset['records'] as $record) {
                $recordElem = $dom->createElement('record');
                $recordElem->appendChild($dom->createElement('content', $record['content']));
                $recordElem->appendChild($dom->createElement('disabled', ($record['disabled'] ?? false) ? 'true' : 'false'));
                $rrsetElem->appendChild($recordElem);
            }
        }
        if (isset($rrset['comments'])) {
            foreach ($rrset['comments'] as $comment) {
                $commentElem = $dom->createElement('comment');
                $commentElem->appendChild($dom->createElement('content', $comment['content']));
                $commentElem->appendChild($dom->createElement('account', $comment['account']));
                $rrsetElem->appendChild($commentElem);
            }
        }
    }

    /**
     * @param \DOMElement $nsElem
     * @param \DOMDocument $dom
     * @param array $array
     */
    protected function arrayToXml(\DOMElement $nsElem, \DOMDocument $dom, array $array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $newElem = $nsElem->appendChild($dom->createElement($key));
                $this->arrayToXml($newElem, $dom, $value);
            } else {
                $nsElem->appendChild($dom->createElement($key, $value));
            }
        }
    }

    /**
     * @param \DOMXPath $xpath
     *
     * @throws HRDApiCommunicationException
     */
    protected function parseError(\DOMXPath $xpath)
    {
        $result = $xpath->query('/api/message');

        if ($result->length === 0) {
            throw new HRDApiCommunicationException('unknown');
        }
        throw new HRDApiCommunicationException('api_error', $result->item(0)->textContent);
    }

    /**
     * @param string $name1
     * @param string|null $name2
     *
     * @return array
     */
    protected function getAPIDOMDocument(string $name1, string $name2 = null)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $api */
        /* @var \DOMElement $elem2 */
        $dom = new \DOMDocument('1.0', 'utf-8');
        $api = $dom->createElementNS('http://api.hrd.pl/api/', 'api');
        $elem1 = $dom->createElement($name1);
        if ($name2 !== null) {
            $elem2 = $dom->createElement($name2);
            $elem1->appendChild($elem2);
        }
        $api->appendChild($elem1);
        $dom->appendChild($api);
        if ($name2 !== null) {
            return [$dom, $elem2];
        }

        return [$dom, $elem1];
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     *
     * @return string
     */
    protected function sendStringResponse(\DOMDocument $dom, string $path)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);
        if ($result->length === 0) {
            $this->parseError($xpath);
        } else {
            return $result->item(0)->textContent;
        }
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     * @param string|null $additionalPath
     *
     * @return \Generator|void
     */
    protected function sendStringYieldResponse(\DOMDocument $dom, string $path, string $additionalPath = null, bool $convertIdn = null)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);
        if ($result->length === 0) {
            if ($additionalPath !== null && $xpath->query('/api/' . $additionalPath)->length === 1) {
                return;
            }
            $this->parseError($xpath);
        } else {
            for ($i = 0, $l = $result->length; $i < $l; $i++) {
                if ($convertIdn) {
                    yield $this->idnToUtf8($result->item($i)->textContent);
                } else {
                    yield $result->item($i)->textContent;
                }
            }
        }
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     *
     * @return int
     */
    protected function sendIntResponse(\DOMDocument $dom, string $path)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);

        if ($result->length === 0) {
            $this->parseError($xpath);
        } else {
            return (int)$result->item(0)->textContent;
        }
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     * @param string|null $additionalPath
     *
     * @return \Generator|void
     */
    protected function sendIntYieldResponse(\DOMDocument $dom, string $path, string $additionalPath = null)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);
        if ($result->length === 0) {
            if ($additionalPath !== null && $xpath->query('/api/' . $additionalPath)->length === 1) {
                return;
            }
            $this->parseError($xpath);
        } else {
            for ($i = 0, $l = $result->length; $i < $l; $i++) {
                yield (int)$result->item($i)->textContent;
            }
        }
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     *
     * @return bool
     */
    protected function sendVoidResponse(\DOMDocument $dom, string $path)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);
        if ($result->length === 0) {
            $this->parseError($xpath);
        } else {
            return true;
        }
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     *
     * @return bool|null
     */
    protected function sendIntOrVoidResponse(\DOMDocument $dom, string $path, string $intPath)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);
        if ($result->length === 0) {
            $this->parseError($xpath);
        }

        $result = $xpath->query('/api/' . $intPath);
        if ($result->length === 1) {
            return (int)$result->item(0)->textContent;
        } else {
            return null;
        }
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     * @param array $castToIntKeys
     * @param string|null $dnsPath
     * @param string|null $additionalPath
     *
     * @return array|bool
     */
    protected function sendArrayResponse(
        \DOMDocument $dom,
        string $path,
        array $castToIntKeys = [],
        string $dnsPath = null,
        string $additionalPath = null,
        string $dnsGroupPath = null,
        bool $convertIdn = null
    ) {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);

        if ($result->length === 0) {
            if ($additionalPath !== null && $xpath->query('/api/' . $additionalPath)->length === 1) {
                return false;
            }
            $this->parseError($xpath);
        } else {
            $toReturn = [];
            for ($i = 0, $l = $result->length; $i < $l; $i++) {
                $item = $result->item($i);

                if ($dnsPath !== null && $item->nodeName === 'ns') {
                    $this->parseNs($toReturn, $xpath, '/api/' . $dnsPath);
                } elseif ($dnsPath !== null && $item->nodeName === 'pricing') {
                    $this->parsePricing($toReturn, $item);
                } elseif ($dnsGroupPath !== null && $item->nodeName === 'ns') {
                    $this->parseGroupNs($toReturn, $xpath, '/api/' . $dnsGroupPath);
                } elseif ($convertIdn && in_array($item->nodeName, ['objectName', 'name'])) {
                    $toReturn[$item->nodeName] = $this->idnToUtf8($item->textContent);
                } elseif ($item->nodeName === 'rrset') {
                    $this->parseRrset($toReturn, $item);
                } elseif ($item->nodeName === 'dnssec') {
                    $this->parseDnsSec($toReturn, $item);
                } else {
                    $toReturn[$item->nodeName] = (in_array($item->nodeName, $castToIntKeys, true) ? (int)$item->textContent : $item->textContent);
                }
            }

            return $toReturn;
        }
    }

    /**
     * @param callable $hash
     */
    protected function setHash(callable $hash)
    {
        $this->hash = $hash();
    }

    /**
     * @param callable $token
     */
    protected function loginByToken(callable $token)
    {
        list($dom, $api) = $this->getAPIDOMDocument('token');
        $login = $dom->createElement('token', $token());
        $api->appendChild($login);
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/status/text()');
        if ($result->length > 0 && $result->item(0)->textContent === 'ok') {
            $this->token = $token();
        }
    }

    /**
     * @param callable $login
     * @param callable $pass
     * @param string $type
     */
    protected function loginByPass(callable $login, callable $pass, string $type)
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('login');
        $elem->appendChild($dom->createElement('login', $login()));
        $elem->appendChild($dom->createElement('pass', $pass()));
        $elem->appendChild($dom->createElement('type', $type));
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/token/text()');
        if ($result->length > 0) {
            $this->token = $result->item(0)->textContent;
        } else {

        }
    }

    /**
     * @param array ...$objects
     */
    protected function dump(...$objects)
    {
        foreach ($objects as $object) {
            var_dump($object);
        }
    }

    /**
     * @param \DOMDocument $xml
     *
     * @throws HRDApiCommunicationException
     * @throws HRDApiIncorrectDataException
     */
    protected function send(\DOMDocument $xml)
    {
        if ($this->schemaValidate === true) {
            if ($this->debug) {
                $xml->schemaValidate($this->schemaFile);
            } else {
                try {
                    if ($xml->schemaValidate($this->schemaFile) !== true) {
                        throw new HRDApiIncorrectDataException('schema validation failed');
                    }
                } catch (\Exception $e) {
                    if ($this->debug) {
                        $this->dump('validate error', $xml->saveXML());
                    }
                    throw new HRDApiIncorrectDataException('schema validation failed');
                }
            }
        }
        if ($this->fp === null || $this->fp === false) {
            $context = stream_context_create();
            if (!$this->verifyPeer) {
                stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
                stream_context_set_option($context, 'ssl', 'verify_peer', false);
                stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            }
            $this->fp = stream_socket_client(
                'ssl://' . $this->host . ':' . $this->port,
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if ($this->fp === false) {
                throw new HRDApiCommunicationException('connect error');
            }
        }
        $data = $xml->saveXML();
        if ($this->debug) {
            $this->dump('write', $data);
        }

        $hash = hash('sha512', $data . $this->hash, true);
        $data = $hash . $data;

        $data = pack('N', strlen($data)) . $data;
        if (fwrite($this->fp, $data) !== strlen($data)) {
            throw new HRDApiCommunicationException('send error');
        }
    }

    /**
     * @return \DOMDocument
     * @throws HRDApiCommunicationException
     * @throws HRDApiIncorrectDataReceivedException
     */
    protected function read()
    {
        $length = fread($this->fp, 4);
        if (strlen($length) !== 4) {
            throw new HRDApiCommunicationException('read error');
        }
        $length = unpack('N', $length)[1];
        $data = '';
        while (strlen($data) < $length) {
            $data .= fread($this->fp, $length - strlen($data));
        }

        if ($this->debug) {
            $this->dump('read', $data);
        }
        if (strlen($data) !== $length) {
            throw new HRDApiCommunicationException('read error');
        }
        if ($this->responseSchemaValidate === true) {
            $dom = new \DOMDocument();
            $dom->loadXML($data);
            if ($dom->schemaValidate($this->responseSchemaFile) !== true) {
                throw new HRDApiIncorrectDataReceivedException('response schema validation failed');
            }
        }
        $dom = new \DOMDocument();
        $dom->loadXML(str_replace('xmlns="http://api.hrd.pl/api/"', '', $data));

        return $dom;
    }

    /**
     * @return \Generator
     */
    public function partnerGetPricings()
    {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'getPricings');

        yield from $this->sendPricingYeldResponse($dom, 'partner/pricings/pricing', 'partner/pricings');
    }

    /**
     * @param \DOMDocument $dom
     * @param string $path
     * @param string|null $additionalPath
     *
     * @return \Generator|void
     */
    protected function sendPricingYeldResponse(\DOMDocument $dom, string $path, string $additionalPath)
    {
        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/' . $path);
        if ($result->length === 0) {
            if ($additionalPath !== null && $xpath->query('/api/' . $additionalPath)->length === 1) {
                return;
            }
            $this->parseError($xpath);
        } else {
            for ($i = 0, $l = $result->length; $i < $l; $i++) {
                $item = $result->item($i);
                $items = $item->childNodes;
                $pricingArray = [
                    'name' => null,
                    'prices' => [],
                ];
                foreach ($items as $item) {
                    if ($item->nodeName == 'name') {
                        $pricingArray['name'] = $item->nodeValue;
                    }
                    if ($item->nodeName == 'price') {
                        $priceName = null;
                        $priceValue = null;
                        foreach ($item->childNodes as $priceItem) {
                            if ($priceItem->nodeName === 'name') {
                                $priceName = $priceItem->nodeValue;
                            }
                            if ($priceItem->nodeName === 'value') {
                                $value = $priceItem->nodeValue;
                                if ($value === '(brak)') {
                                    $value = null;
                                }
                                $priceValue = $value;
                            }
                        }
                        $pricingArray['prices'][$priceName] = $priceValue;
                    }
                }
                yield $pricingArray;
            }
        }
    }

    public function parsePricing(array &$toReturn, \DOMElement $item)
    {
        $items = $item->childNodes;

        $pricingArray = [];
        $prices = [];
        foreach ($items as $item) {
            if ($item->nodeName == 'name') {
                $pricingArray[$item->nodeName] = $item->nodeValue;
            }

            if ($item->nodeName == 'prices') {

                $priceElements = $item->childNodes;
                foreach ($priceElements as $priceElementItem) {
                    foreach ($priceElementItem->childNodes as $priceItem) {
                        if ($priceItem->nodeName === 'name') {
                            $priceName = $priceItem->nodeValue;
                        }
                        if ($priceItem->nodeName === 'value') {
                            $value = $priceItem->nodeValue;
                            if ($value === '(brak)') {
                                $value = null;
                            }
                            $priceValue = $value;
                        }
                    }
                    $prices[$priceName] = $priceValue;
                }

            }
        }
        $pricingArray['prices'] = $prices;

        $toReturn = $pricingArray;
    }

    /**
     * @return \Generator
     */
    public function partnerGetPricingsList()
    {
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'getPricingsList');

        yield from $this->sendStringYieldResponse($dom, 'partner/pricings/name', 'partner/pricings');
    }

    /**
     * Funkcja ta służy do pobierania informacji o cenniku na daną usługę dla partnera
     *
     * @param string $name nazwa usługi
     * @return array tablica zawierająca ceny dla danej usługi
     */
    public function partnerPricingServiceInfo(string $name)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'getPricingInfo');
        $elem->appendChild($dom->createElement('name', (string)$name));

        return $this->sendArrayResponse($dom, 'partner/pricing', [], 'partner/pricing');
    }

    /**
     * wysyła ponownie maile z linkami potwierdzającymi cesję do przekazującego i przejmującego
     * @param int $id - id akcji
     * @param null $assigneeId - id przejmującego
     * @param null $assignorId - id przekazującego
     *
     * @return array|bool
     */
    public function partnerResendTradeMail(int $id, $assigneeId = null, $assignorId = null)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'resendTradeMail');
        $elem->appendChild($dom->createElement('id', (string)$id));
        if (!empty($assignorId)) {
            $elem->appendChild($dom->createElement('assignor', (string)$assignorId));
        }
        if (!empty($assigneeId)) {
            $elem->appendChild($dom->createElement('assignee', (string)$assigneeId));
        }

        return $this->sendVoidResponse($dom, 'partner/resendTradeMail');
    }

    /**
     * funkcja pobiera tablice ustawień
     *
     * @return array tablica zawierająca ustawienia (klucz -> wartość)
     */
    public function getKeys()
    {
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'getKeys');

        $this->send($dom);
        $responseDom = $this->read();
        $xpath = new \DOMXPath($responseDom);
        $result = $xpath->query('/api/partner/getKeys/*');
        $keys = [];

        if ($result->length === 0) {
            $additionalPath = '/partner/getKeys';
            if ($additionalPath !== null && $xpath->query('/api/' . $additionalPath)->length === 1) {
                return [];
            }
            $this->parseError($xpath);
        } else {
            for ($i = 0, $l = $result->length; $i < $l; $i++) {
                $item = $result->item($i);
                $key = null;
                $value = null;
                foreach ($item->childNodes as $node) {
                    switch ($node->nodeName) {
                        case 'key':
                            $key = $node->nodeValue;
                            break;
                        case 'value':
                            $value = $node->nodeValue;
                            break;
                        default:
                            return $this->parseError($xpath);
                    }
                }
                $keys[$key] = $value;
            }
        }

        return $keys;
    }

    /**
     * funkcja ustawia ustawienie
     *
     * @return array tablica zawierająca ustawienia (klucz -> wartość)
     */
    public function setKey($key, $value)
    {
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'setKey');
        $elem->appendChild($dom->createElement('key', $key));
        $elem->appendChild($dom->createElement('value', $value));

        return $this->sendVoidResponse($dom, 'partner/setKey');
    }

    /**
     * @param int|null $userId
     * @param string $type
     * @param string|null $org
     * @param string $name
     * @param string $voice
     * @param string $fax
     * @param string $street
     * @param string $postcode
     * @param string $city
     * @param string $sp
     * @param string $country
     * @param string $email
     * @return int
     * @throws HRDApiIncorrectDataException
     */
    public function partnerContactCreate(
        int $userId = null,
        string $type,
        string $org = null,
        string $name,
        string $voice,
        string $fax = null,
        string $street,
        string $postcode,
        string $city,
        string $sp,
        string $country,
        string $email
    ) {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
        list($dom, $elem) = $this->getAPIDOMDocument('partner', 'contactCreate');

        if ($userId) {
            $elem->appendChild($dom->createElement('userId', (string)$userId));
        }
        switch ($type) {
            case self::PERSON;
            $elem->appendChild($dom->createElement('personType'));
            break;
        case self::COMPANY:
            $elem->appendChild($dom->createElement('companyType'));
            $elem->appendChild($dom->createElement('org', $org));
            break;
        default:
            throw new HRDApiIncorrectDataException('invalid type');
    }
    $elem->appendChild($dom->createElement('name', $name));
    $elem->appendChild($dom->createElement('voice', $voice));
    if ($fax) {
        $elem->appendChild($dom->createElement('fax', $fax));
    }
    $elem->appendChild($dom->createElement('street', $street));
    $elem->appendChild($dom->createElement('postcode', $postcode));
    $elem->appendChild($dom->createElement('city', $city));
    $elem->appendChild($dom->createElement('sp', $sp));
    $elem->appendChild($dom->createElement('country', $country));
    $elem->appendChild($dom->createElement('email', $email));

    return $this->sendIntResponse($dom, 'partner/contactCreate/contactId');
}

/**
 * @param int $contactId
 * @param string|null $org
 * @param string $name
 * @param string $voice
 * @param string $fax
 * @param string $street
 * @param string $postcode
 * @param string $city
 * @param string $sp
 * @param string $country
 * @param string $email
 * @return bool
 */
public function partnerContactUpdate(
    int $contactId,
    string $org = null,
    string $name,
    string $voice,
    string $fax = null,
    string $street,
    string $postcode,
    string $city,
    string $sp,
    string $country,
    string $email
) {
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
    list($dom, $elem) = $this->getAPIDOMDocument('partner', 'contactUpdate');

    $elem->appendChild($dom->createElement('id', $contactId));

    if ($org) {
        $elem->appendChild($dom->createElement('org', $org));
    }

    $elem->appendChild($dom->createElement('name', $name));
    $elem->appendChild($dom->createElement('voice', $voice));
    if ($fax) {
        $elem->appendChild($dom->createElement('fax', $fax));
    }

    $elem->appendChild($dom->createElement('street', $street));
    $elem->appendChild($dom->createElement('postcode', $postcode));
    $elem->appendChild($dom->createElement('city', $city));
    $elem->appendChild($dom->createElement('sp', $sp));
    $elem->appendChild($dom->createElement('country', $country));
    $elem->appendChild($dom->createElement('email', $email));

    return $this->sendIntOrVoidResponse($dom, 'partner/contactUpdate', 'partner/contactUpdate/actionId');
}

/**
 * @param int $contactId
 * @return bool|null
 */
public function partnerContactDelete(int $contactId)
{
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
    list($dom, $elem) = $this->getAPIDOMDocument('partner', 'contactDelete');
    $elem->appendChild($dom->createElement('id', $contactId));

    return $this->sendIntOrVoidResponse($dom, 'partner/contactDelete', 'partner/contactDelete/actionId');
}

/**
 * @param int $contactId
 * @return array|bool
 */
public function partnerContactInfo(int $contactId)
{
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
    list($dom, $elem) = $this->getAPIDOMDocument('partner', 'contactInfo');
    $elem->appendChild($dom->createElement('id', $contactId));

    return $this->sendArrayResponse($dom, 'partner/contactInfo/*');
}

/**
 * @param int $userId
 * @param int|null $lastId
 * @return \Generator
 */
public function partnerContactList(int $userId = null, int $lastId = null)
{
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
    list($dom, $elem) = $this->getAPIDOMDocument('partner', 'contactList');
    if ($userId !== null) {
        $elem->appendChild($dom->createElement('userId', $userId));
    }
    if ($lastId !== null) {
        $elem->appendChild($dom->createElement('lastId', $lastId));
    }
    yield from $this->sendIntYieldResponse($dom, 'partner/contactList/id', 'partner/contactList');
}


/**
 * Dodaje rekord secDNS dla wskazanej domeny
 * @param $domain
 * @param $alg
 * @param $digestType
 * @param $digest
 * @param $key
 * @return int
 */
public function domainDnsSecAdd($domain, $alg, $digestType, $digest, $key)
{
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'dnssecAdd');
    $elem->appendChild($dom->createElement('name', $domain));
    $elem->appendChild($dom->createElement('alg', $alg));
    $elem->appendChild($dom->createElement('digestType', $digestType));
    $elem->appendChild($dom->createElement('digest', $digest));
    if (in_array($key, ['zsk', 'ksk'])) {
        $elem->appendChild($dom->createElement('keyType', $key));
    } else {
        $elem->appendChild($dom->createElement('keyTag', $key));
    }

    return $this->sendIntResponse($dom, 'domain/dnssecAdd/actionId');
}


/**
 * Usuwa wskazany rekord secDNS dla domeny
 * @param $domain
 * @param $alg
 * @param $digestType
 * @param $digest
 * @param $key
 * @return int
 */
public function domainDnsSecDelete($domain, $alg, $digestType, $digest, $key)
{
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'dnssecDelete');
    $elem->appendChild($dom->createElement('name', $domain));
    $elem->appendChild($dom->createElement('alg', $alg));
    $elem->appendChild($dom->createElement('digestType', $digestType));
    $elem->appendChild($dom->createElement('digest', $digest));
    if (in_array($key, ['zsk', 'ksk'])) {
        $elem->appendChild($dom->createElement('keyType', $key));
    } else {
        $elem->appendChild($dom->createElement('keyTag', $key));
    }

    return $this->sendIntResponse($dom, 'domain/dnssecDelete/actionId');
}

/**
 * Create host
 *
 * @param string $name
 * @param array $ip
 * @return int
 */
public function domainHostCreate(string $name, array $ip)
{
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'hostCreate');
    $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

    if (isset($ip['ipv4'])) {
        foreach ($ip['ipv4'] as $ipv4) {
            $elem->appendChild($dom->createElement('ipv4', $ipv4));
        }
    }
    if (isset($ip['ipv6'])) {
        foreach ($ip['ipv6'] as $ipv6) {
            $elem->appendChild($dom->createElement('ipv6', $ipv6));
        }
    }

    return $this->sendIntOrVoidResponse($dom, 'domain/hostCreate', 'domain/hostCreate/actionId');
}

/**
 * Delete host
 *
 * @param string $name
 * @return int
 */
public function domainHostDelete(string $name)
{
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'hostDelete');
    $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

    return $this->sendIntOrVoidResponse($dom, 'domain/hostDelete', 'domain/hostDelete/actionId');
}

/**
 * Funkcja służy do pobierania informacji o serwerze nazw - host, domena nadrzędna musi być obsługiwana przez konkretnego partnera
 *
 * @param string $name Host dla której chciałbyś informację
 * @return array tablica w której są umieszczone informację o hoście dla której chciałbyś pobrać informacje
 */
public function domainHostInfo(string $name)
{
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'hostInfo');
    $elem->appendChild($dom->createElement('name', $this->idnToAscii($name)));

    return $this->sendArrayResponse($dom, 'domain/hostInfo/*');
}

/**
 * Funkcja służy do pobierania listy hostów wybranego parntnera
 *
 * @param string $lastName nazwa ostatnio pobranego hosta
 * @return array tablica w której są umieszczone informację o hoście dla której chciałbyś pobrać informacje
 */
public function domainHostList(string $lastName = null)
{
        /* @var \DOMDocument $dom */
        /* @var \DOMElement $elem */
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'hostList');
    if ($lastName !== null) {
        $elem->appendChild($dom->createElement('lastName', $this->idnToAscii($lastName)));
    }
    yield from $this->sendStringYieldResponse($dom, 'domain/hostList/name', 'domain/hostList');
}

/**
 * Funkcja służy do sprawdzenia czy  podany kod authInfo jest prawidłowy (dla domen .pl i .eu)
 * @param string $domainName - nazwa domeny
 * @param string $authInfo - kod authInfo
 *
 * @return bool true jeżeli kod authinfo jest poprawny, false - w przypadku nieprawidłowej weryfikacji
 */
public function domainValidateAuthInfo(string $domainName, string $authInfo)
{
    list($dom, $elem) = $this->getAPIDOMDocument('domain', 'authInfoCheck');
    $elem->appendChild($dom->createElement('name', $this->idnToAscii($domainName)));
    $elem->appendChild($dom->createElement('authInfo', (string)$authInfo));

    $response = $this->sendStringResponse($dom, 'domain/authInfoCheck/valid');
    return filter_var($response, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
}

protected function idnToAscii($name)
{
    return idn_to_ascii($name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
}

protected function idnToUtf8($name)
{
    return idn_to_utf8($name, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
}
}

