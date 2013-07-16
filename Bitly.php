<?php
/**
* Bitly API Client.
*
* LICENSE: Apache Software License v2.0, see LICENSE
*
* @copyright  Copyright (c) 2013 Bitly Inc.
* @license    http://www.apache.org/licenses/LICENSE-2.0
* @version    0.1.0
* @link       https://github.com/bitly/bitly-api-php
*/

class BitlyError extends Exception
{}


class BitlyAPIError extends BitlyError
{}


class Bitly
{
    public $clientId = null;
    public $clientSecret = null;
    //public $username = null;
    //public $apiKey = null;
    public $accessToken = null;
    public $apiUrl = 'https://api-ssl.bitly.com/';
    public $userAgent = null;
    public $connectTimeout = 25;

    public function __construct($clientId=null, $clientSecret=null,
                                //$username=null, $apiKey=null,
                                $accessToken=null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        //$this->username = $username;
        //$this->apiKey = $apiKey;
        $this->accessToken = $accessToken;
        $this->userAgent = 'PHP/' . phpversion() . ' bitly_api/0.1.0';
    }

    /**
    * Given a {@param $code}, get an access token from Bitly.
    */
    public function getAccessToken($code, $redirectUri)
    {
        $params = array('code' => $code, 'redirect_uri' => $redirectUri,
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret);
        $result = $this->call('oauth/access_token', $params, true, false);
        $data = array();
        parse_str($result, $data);
        var_dump($data);
        $this->accessToken = $data['access_token'];
        return $data['access_token'];
    }

    public function expand($shortUrl=null, $hash=null)
    {
        if (!$shortUrl && !$hash) {
            throw new BitlyError('shortUrl or hash must be specified');
        }

        $params = array();
        if ($shortUrl) {
            $params['shortUrl'] = $shortUrl;
        }
        elseif ($hash) {
            $params['hash'] = $hash;
        }
        $results = $this->call('v3/expand', $params);
        return $results['expand'][0];
    }

    public function info($shortUrl=null, $hash=null, $expandUser=null)
    {
        if (!$shortUrl && !$hash) {
            throw new BitlyError('shortUrl or hash must be specified');
        }

        if ($expandUser !== null) {
            $params = array('expand_user' => $expandUser);
        }

        if ($shortUrl) {
            $params['shortUrl'] = $shortUrl;
        }
        elseif ($hash) {
            $params['hash'] = $hash;
        }
        $results = $this->call('v3/info', $params);
        return $results['info'][0];
    }

    public function linkLookup($url)
    {
        $params = array('url' => $url);
        $results = $this->call('v3/link/lookup', $params);
        return $results['link_lookup'][0];
    }

    public function shorten($url, $domain=null)
    {
        $params = array('longUrl' => $url);
        if ($domain !== null) {
            $params['domain'] = $domain;
        }
        $results = $this->call('v3/shorten', $params);
        return $results;
    }

    /**
    * Possible keys/values for $params are as on
    *   http://dev.bitly.com/links.html#v3_user_link_edit
    *
    * @param $link A bitly link.
    * @param $params An array of optional values from above.
    */
    public function userLinkEdit($link, Array $params)
    {
        $keys = array_keys($params);
        $params['edit'] = implode(',', $keys);
        $params['link'] = $link;
        $results = $this->call('v3/user/link_edit', $params);
        return $results['link_edit'];
    }

    public function userLinkLookup($url) {
        $params = array('url' => $url);
        $results = $this->call('v3/user/link_lookup', $params);
        return $results['link_lookup'][0];
    }

    /**
    * Possible keys/values for $params are as on
    *   http://dev.bitly.com/links.html#v3_user_link_save
    *
    * @param $url A long URL to shorten and save.
    * @param $params An array of optional values.
    */
    public function userLinkSave($url, Array $params)
    {
        $params['longUrl'] = $url;
        $results = $this->call('v3/user/link_save', $params);
        return $results['link_save'];
    }

    public function highvalue($limit)
    {
        $params = array('limit' => $limit);
        $results = $this->call('v3/highvalue', $params);
        return $results['values'];
    }

    public function search($query, $limit=10, $offset=0, $lang=null,
                           $cities=null, $domain=null, Array $fields=null)
    {
        $params = array('query' => $query, 'limit' => $limit,
                        'offset' => $offset);
        if ($lang !== null) {
            $params['lang'] = $lang;
        }
        if ($cities !== null) {
            $param['cities'] = $cities;
        }
        if ($domain !== null) {
            $params['domain'] = $domain;
        }
        if ($fields !== null) {
            $params['fields'] = implode(',', $fields);
        }
        $results = $this->call('v3/search', $params);
        return $results['results'];
    }

    public function realtimeBurstingPhrases() {
        return $this->call('v3/realtime/bursting_phrases');
    }

    public function realtimeHotPhrases() {
        return $this->call('v3/realtime/hot_phrases');
    }

    public function realtimeClickrate($phrase) {
        $params = array('phrase' => $phrase);
        return $this->call('v3/realtime/clickrate', $params);
    }

    public function linkInfo($link) {
        $params = array('link' => $link);
        return $this->call('v3/link/info', $params);
    }

    public function linkContent($link) {
        $params = array('link' => $link);
        return $this->call('v3/link/content', $params);
    }

    public function linkCategory($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/category', $params);
        return $results['categories'];
    }

    public function linkSocial($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/social', $params);
        return $results['social_scores'];
    }

    public function linkLocation($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/location', $params);
        return $results['locations'];
    }

    public function linkLanguage($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/language', $params);
        return $results['languages'];
    }

    public function linkClicks($link, $unit='day', $units=null,
                               $timezone='America/New_York', $rollup=null,
                               $limit=100, $unit_reference_ts='now')
    {
        $params = array('link' => $link, 'unit' => $unit,
                        'timezone' => $timezone, 'limit' => $limit,
                        'unit_reference_ts' => $unit_reference_ts);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/link/clicks', $params);
    }

    public function linkCountries($link, $unit='day', $units=null,
                                  $timezone='America/New_York', $limit=100,
                                  $unit_reference_ts='now')
    {
        $params = array('link' => $link, 'unit' => $unit,
                        'timezone' => $timezone, 'limit' => $limit,
                        'unit_reference_ts' => $unit_reference_ts);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/countries', $params);
    }

    public function linkEncoders($link, $myNetwork=null, $limit=10,
                                 $expandUser=null)
    {
        $params = array('link' => $link, 'limit' => $limit);
        if ($myNetwork !== null) {
            $params['my_network'] = $myNetwork;
        }
        if ($expandUser !== null) {
            $params['expand_user'] = $expandUser;
        }
        return $this->call('v3/link/encoders', $params);
    }

    public function linkEncodersCount($link)
    {
        $params = array('link' => $link);
        return $this->call('v3/link/encoders_count', $params);
    }

    public function linkReferrers($link, $unit='day', $units=null,
                                  $timezone='America/New_York', $limit=100,
                                  $unit_reference_ts='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unit_reference_ts);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/referrers', $params);
    }

    public function linkReferrersByDomain($link, $unit='day', $units=null,
                                          $timezone='America/New_York',
                                          $limit=100, $unit_reference_ts='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unit_reference_ts);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/refererrs_by_domain', $params);
    }

    public function linkReferringDomains($link, $unit='day', $units=null,
                                         $timezone='America/New_York',
                                         $limit=100, $unit_reference_ts='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unit_reference_ts);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/refererring_domains', $params);
    }

    public function linkShares($link, $unit='day', $units=null,
                               $timezone='America/New_York', $rollup=null,
                               $limit=100, $unit_reference_ts='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unit_reference_ts);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/link/shares', $params);
    }

    public function userInfo($login=null, $fullName=null)
    {
        $params = array();
        if ($login !== null) {
            $params['login'] = $login;
        }
        if ($fullName !== null) {
            $params['full_name'] = $fullName;
        }
        return $this->call('v3/user/info', $params);
    }

    public function userLinkHistory($link=null, $limit=50, $offset=null,
                                    $createdBefore=null, $createdAfter=null,
                                    $modifiedAfter=null, $expandClientId=null,
                                    $archived=null, $private=null, $user=null)
    {
        $params = array();
        if ($link !== null) {
            $params['link'] = $link;
        }
        if ($limit !== null) {
            $params['limit'] == $limit;
        }
        if ($offset !== null) {
            $params['offset'] = $offset;
        }
        if ($createdBefore !== null) {
            $params['created_before'] = $createdBefore;
        }
        if ($createdAfter !== null) {
            $params['created_after'] = $createdAfter;
        }
        if ($modifiedAfter !== null) {
            $params['modified_after'] = $modifiedAfter;
        }
        if ($archived !== null) {
            $params['archived'] = $archived;
        }
        if ($private !== null) {
            $params['private'] = $private;
        }
        if ($user !== null) {
            $params['user'] = $user;
        }
        return $this->call('v3/user/link_history', $params);
    }

    public function userNetworkHistory($offset=null, $expandClientId=false,
                                       $limit=20, $expandUser=null)
    {
        $params = array('expand_client_id' => $expandClientId,
                        'limit' => $limit);
        if ($offset !== null) {
            $params['offset'] = $offset;
        }
        if ($expandUser !== null) {
            $params['expand_user'] = $expandUser;
        }
        return $this->call('v3/user/network_history', $params);
    }

    public function userTrackingDomainList()
    {
        $result = $this->call('v3/user/tracking_domain_list');
        return $result['tracking_domains'];
    }

    protected function call($endpoint, Array $params=null, $post=false, $json=true)
    {
        if ($params === null) {
            $params = array();
        }
        $url = $this->apiUrl . $endpoint;
        $params['format'] = 'json';
        if ($this->accessToken !== null &&
            !array_key_exists('access_token', $params))
        {
            $params['access_token'] = $this->accessToken;
        }

        // Convert booleans to 'true'/'false'.
        foreach($params as $k => &$v) {
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            $query = http_build_query($params);
            $url .= '?' . $query;
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($status_code !== 200) {
            throw new BitlyAPIError($result, $status_code);
        }
        if ($json) {
            $result = json_decode($result, true);
            if ($result['status_code'] !== 200) {
                throw new BitlyAPIError($result['status_txt'],
                                        (int)$result['status_code']);
            }
            return $result['data'];
        }
        return $result;
    }
}
