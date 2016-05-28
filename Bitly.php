<?php
/**
 * Bitly API Client.
 *
 * LICENSE: Apache Software License v2.0, see LICENSE
 *
 * @package    Bitly
 * @copyright  Copyright (c) 2013 Bitly Inc.
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 * @version    0.1.0
 * @link       https://github.com/bitly/bitly-api-php
 */

/**
 * Base class for Bitly client errors.
 */
class BitlyError extends Exception
{}


/**
 * Raised when communicating with the API results in an error.
 */
class BitlyAPIError extends BitlyError
{}

/**
 * Build a query string from an array of param => values. Handle
 * multiple values correctly.
 *
 * PHP's built-in http_build_query does the insane thing of converting
 * param names when given an array value, which is only correct for PHP
 * servers. This builds correct query strings given a smaller set of
 * allowable inputs. Only one level of nesting is OK, e.g.:
 *
 *     $data = array(
 *         'foo' => 'FOO',
 *         'bar' => array('1', '2', '3')
 *     );
 *     echo build_params($data);
 *     // foo=FOO&bar=1&bar=2&bar=3
 *
 * @internal
 *
 * @param array $params
 *
 * @return string
 */
function build_query(Array $params) {
    $ret = array();
    $fmt = '%s=%s';
    $separator = '&';
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            foreach ($v as $_v) {
                array_push($ret, sprintf($fmt, $k, urlencode($_v)));
            }
        } else {
            array_push($ret, sprintf($fmt, $k, urlencode($v)));
        }
    }
    return implode($separator, $ret);
}


/**
 * A possibly-authenticated connection to the Bitly API.
 */
class Bitly
{
    /**
     * The application's OAuth2 client ID.
     *
     * @type string|null
     */
    public $clientId = null;

    /**
     * The application's OAuth2 client secret.
     *
     * @type string|null
     */
    public $clientSecret = null;

    /**
     * The user or application's OAuth2 access token.
     *
     * @type string|null
     */
    public $accessToken = null;

    /**
     * The API hostname.
     *
     * @type string
     */
    public $apiUrl = 'https://api-ssl.bitly.com/';

    /**
     * The application's user agent.
     *
     * @type string|null
     */
    public $userAgent = null;

    /**
     * Timeout for communicating with Bitly.
     *
     * @type int
     */
    public $timeout = 4;

    /**
     * Timeout for connecting to Bitly.
     *
     * @type int
     */
    public $connectTimeout = 2;

    /**
     * @var bool
     */
    private $isIPv6Disabled = false;

    /**
     * Create a new Bitly API client with the given credentials.
     *
     * @param string $clientId
     *  (Optional) The application's registered Bitly client ID.
     * @param string $clientSecret
     *  (Optional) The application's registered Bitly client secret.
     * @param string $accessToken
     *  (Optional) The application or current user's access token. Necessary
     *  for most API methods.
     *
     * @return Bitly
     *
     * @see http://dev.bitly.com/authentication.html
     */
    public function __construct($clientId=null, $clientSecret=null,
                                $accessToken=null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->accessToken = $accessToken;
        $this->userAgent = 'PHP/' . phpversion() . ' bitly_api/0.1.0';
    }

    /**
     * Given an OAuth2 auth code, get an access token from Bitly.
     *
     * Exchanges an auth code for an access token, sets the access token on
     * this client instance, and returns the token.
     *
     * @param string $code
     *  Authorization code from Bitly.
     * @param string $redirectUri
     *  The URI to which the user was redirected after authenticating.
     *
     * @return string
     *
     * @see http://dev.bitly.com/authentication.html
     */
    public function getAccessToken($code, $redirectUri)
    {
        $params = array('code' => $code, 'redirect_uri' => $redirectUri,
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret);
        $result = $this->call('oauth/access_token', $params, true, false);
        $data = array();
        parse_str($result, $data);
        $this->accessToken = $data['access_token'];
        return $data['access_token'];
    }

    /**
     * Expand a short URL or hash.
     *
     * One parameter must be specified. If both are specified, the short URL
     * has precedence.
     *
     * @param string $shortUrl
     *  (Optional) A full bitly short URL, e.g. "http://bit.ly/1234"
     * @param string $hash
     *  (Optional) The hash component of a bitly short URL, e.g. "1234"
     *
     * @return array
     *
     * @see http://dev.bitly.com/links.html#v3_expand
     */
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

    /**
     * Get info about a given short URL or hash.
     *
     * One parameter must be specified. if both are specified, the short URL
     * has precedence.
     *
     * @param string $shortUrl
     *  (Optional) A full bitly short URL, e.g. "http://bit.ly/1234"
     * @param string $hash
     *  (Optional) The hash component of a bitly short URL, e.g. "1234"
     * @param $expandUser
     *  (Optional) Include extra information about the user.
     *
     * @return array
     *
     * @see http://dev.bitly.com/links.html#v3_info
     */
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

    /**
     * Look up a bitly link based on a long URL.
     *
     * @param string $url
     *  A long URL to look up.
     *
     * @return string
     *
     * @see http://dev.bitly.com/links.html#v3_link_lookup
     */
    public function linkLookup($url)
    {
        $params = array('url' => $url);
        $results = $this->call('v3/link/lookup', $params);
        return $results['link_lookup'][0]['aggregate_link'];
    }

    /**
     * Shorten a long URL.
     *
     * 'domain' is a preferred domain, but if the user has a custom short
     * domain, the custom domain will always be used.
     *
     * @param string $longUrl
     *  A long URL to shorten.
     * @param string $domain
     *  (Optional) A preferred domain (either 'bit.ly', 'j.mp', or 'bitly.com').
     *
     * @returns array
     *  An associative array containing:
     *   - 'new_hash' (bool) Whether this URL has been shortened before.
     *   - 'url' (string) The actual short URL.
     *   - 'hash' (string) A Bitly identifier which is unique to this account.
     *   - 'global_hash' (string) A Bitly identifier which can be tracked
     *     across accounts.
     *   - 'long_url' (string) An normalized echo back of the original long URL.
     *
     * @see http://dev.bitly.com/links.html#v3_shorten
     */
    public function shorten($url, $domain=null)
    {
        $params = array('longUrl' => $url);
        if ($domain !== null) {
            $params['domain'] = $domain;
        }
        $results = $this->call('v3/shorten', $params);
        $results['new_hash'] = ($results['new_hash'] == 1) ? true : false;
        return $results;
    }

    /**
     * Edit a saved link.
     *
     * Possible keys/values for $params are as on
     *   http://dev.bitly.com/links.html#v3_user_link_edit
     *
     * @param string $link A bitly link, e.g. 'http://bit.ly/1234'.
     * @param array $params An array of optional values from above.
     *
     * @return string An echo back of the edited link.
     *
     * @see http://dev.bitly.com/links.html#v3_user_link_edit
     */
    public function userLinkEdit($link, Array $params)
    {
        $keys = array_keys($params);
        $params['edit'] = implode(',', $keys);
        $params['link'] = $link;
        $results = $this->call('v3/user/link_edit', $params);
        return $results['link_edit'];
    }

    /**
     * Look up a Bitly link shortened by the authenticated user.
     *
     * @param string $url A long URL to look up.
     *
     * @return array
     *  An associative array containing:
     *   - 'url' (string) An echo back of the long URL.
     *   - 'link' (string) The corresponding Bitly link.
     *   - 'aggregate_link' (string) The corresponding aggregate link.
     *
     * @see http://dev.bitly.com/links.html#v3_user_link_lookup
     */
    public function userLinkLookup($url) {
        $params = array('url' => $url);
        $results = $this->call('v3/user/link_lookup', $params);
        return $results['link_lookup'][0];
    }

    /**
     * Save a Bitly link as the authenticated user.
     *
     * Possible keys/values for $params are as on
     *   http://dev.bitly.com/links.html#v3_user_link_save
     *
     * @param string $url A long URL to shorten and save.
     * @param array $params An associative array of optional values.
     *
     * @return array
     *  An associative array containing:
     *   - 'link' (string) The Bitly link for the long URL.
     *   - 'aggregate_link' (string) The corresponding Bitly aggregate link.
     *   - 'new_link' (bool) Whether the user has saved this link before.
     *   - 'long_url' (string) A normalized echo back of the long URL.
     *
     * @see http://dev.bitly.com/links.html#v3_user_link_save
     */
    public function userLinkSave($url, Array $params)
    {
        $params['longUrl'] = $url;
        $results = $this->call('v3/user/link_save', $params);
        $results = $results['link_save'];
        $results['new_link'] = ($results['new_link'] == 1) ? true : false;
        return $results;
    }

    /**
     * Return a specified number of 'high-value' Bitly links.
     *
     * @param int $limit Maximum number of links to return.
     *
     * @return array
     *  A list of Bitly links.
     *
     * @see http://dev.bitly.com/data_apis.html#v3_highvalue
     */
    public function highvalue($limit)
    {
        $params = array('limit' => $limit);
        $results = $this->call('v3/highvalue', $params);
        return $results['values'];
    }

    /**
     * Search links receiving clicks.
     *
     * @param string $query A string to search for.
     * @param int $limit
     *  (Optional) The maximum number of links to return (10).
     * @param int $offset
     *  (Optional) The result to start with (0).
     * @param string $lang
     *  (Optional) A two-letter ISO language code.
     * @param string $cities
     *  (Optional) Limit to links active in this city (ordered like
     *  'us-il-chicago').
     * @param string $domain
     *  (Optional) Restrict results to this domain.
     * @fields string[]
     *  (Optional) Which fields to return in the response.
     *
     * @return array
     *  An array of results, each result is an associative array and its
     *  contents will depend on 'fields'.
     *
     * @see http://dev.bitly.com/data_apis.html#v3_search
     */
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

    /**
     * Returns phrases that are receiving an uncharacteristically high volume
     * of click traffic, and the individual links (hashes) driving traffic to
     * pages containing these phrases.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_realtime_bursting_phrases
     */
    public function realtimeBurstingPhrases() {
        return $this->call('v3/realtime/bursting_phrases');
    }

    /**
     * Returns phrases that are receiving a consistently high volume of click
     * traffic, and the individual links (hashes) driving traffic to pages
     * containing these phrases.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_realtime_hot_phrases
     */
    public function realtimeHotPhrases() {
        return $this->call('v3/realtime/hot_phrases');
    }

    /**
     * Returns the click rate for content containing a specified phrase.
     *
     * @param string $phrase
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_realtime_clickrate
     */
    public function realtimeClickrate($phrase) {
        $params = array('phrase' => $phrase);
        return $this->call('v3/realtime/clickrate', $params);
    }

    /**
     * Returns metadata about a single bitly link.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_link_info
     */
    public function linkInfo($link) {
        $params = array('link' => $link);
        return $this->call('v3/link/info', $params);
    }

    /**
     * Returns the “main article” from the linked page, as determined by the
     * content extractor, in either HTML or plain text format.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_link_content
     */
    public function linkContent($link) {
        $params = array('link' => $link);
        return $this->call('v3/link/content', $params);
    }

    /**
     * Returns the detected categories for a document, in descending order of
     * confidence.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_link_category
     */
    public function linkCategory($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/category', $params);
        return $results['categories'];
    }

    /**
     * Returns the "social score" for a specified bitly link.
     *
     * Note that the social score are highly dependent upon activity (clicks)
     * occurring on the bitly link. If there have not been clicks on a bitly
     * link within the last 24 hours, it is possible a social score for that
     * link does not exist.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_link_social
     */
    public function linkSocial($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/social', $params);
        return $results['social_scores'];
    }

    /**
     * Returns the significant locations for the bitly link.
     *
     * Note that locations are highly dependent upon activity (clicks)
     * occurring on the bitly link. If there have not been clicks on a bitly
     * link within the last 24 hours, it is possible that location data for
     * that link does not exist.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_link_location
     */
    public function linkLocation($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/location', $params);
        return $results['locations'];
    }

    /**
     * Returns the significant languages for the Bitly link.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/data_apis.html#v3_link_language
     */
    public function linkLanguage($link) {
        $params = array('link' => $link);
        $results = $this->call('v3/link/language', $params);
        return $results['languages'];
    }

    /**
     * Returns the number of clicks on a single Bitly link.
     *
     * @param string $link A Bitly link.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_clicks
     */
    public function linkClicks($link, $unit='day', $units=null,
                               $timezone='America/New_York', $rollup=null,
                               $limit=100, $unitReferenceTs='now')
    {
        $params = array('link' => $link, 'unit' => $unit,
                        'timezone' => $timezone, 'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/link/clicks', $params);
    }

    /**
     * Returns metrics about the countries referring click traffic to a Bitly
     * link.
     *
     * @param string $link A Bitly link.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_countries
     */
    public function linkCountries($link, $unit='day', $units=null,
                                  $timezone='America/New_York', $limit=100,
                                  $unitReferenceTs='now')
    {
        $params = array('link' => $link, 'unit' => $unit,
                        'timezone' => $timezone, 'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/countries', $params);
    }

    /**
     * Returns users who have encoded this link.
     *
     * Optionally restricted to the requesting user's social graph.
     *
     * @param string $link A Bitly link.
     * @param bool $myNetwork (Optional)
     * @param int $limit (Optional)
     * @param bool $expandUser (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_encoders
     */
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

    /**
     * Returns the number of users who have shortened a single Bitly link.
     *
     * @param string $link A Bitly link.
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_encoders_count
     */
    public function linkEncodersCount($link)
    {
        $params = array('link' => $link);
        return $this->call('v3/link/encoders_count', $params);
    }

    /**
     * Returns metrics about the pages referring click traffic to a Bitly link.
     *
     * @param string $link A Bitly link.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_referrers
     */
    public function linkReferrers($link, $unit='day', $units=null,
                                  $timezone='America/New_York', $limit=100,
                                  $unitReferenceTs='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/referrers', $params);
    }

    /**
     * Returns metrics about the pages referring click traffic to a Bitly link,
     * grouped by domain.
     *
     * @param string $link A Bitly link.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_referrers_by_domain
     */
    public function linkReferrersByDomain($link, $unit='day', $units=null,
                                          $timezone='America/New_York',
                                          $limit=100, $unitReferenceTs='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/refererrs_by_domain', $params);
    }

    /**
     * Returns metrics about the domains referring click traffic to a Bitly
     * link.
     *
     * @param string $link A Bitly link.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_referring_domains
     */
    public function linkReferringDomains($link, $unit='day', $units=null,
                                         $timezone='America/New_York',
                                         $limit=100, $unitReferenceTs='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/link/refererring_domains', $params);
    }

    /**
     * Returns metrics about shares of a single Bitly link.
     *
     * @param string $link A Bitly link.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_link_shares
     */
    public function linkShares($link, $unit='day', $units=null,
                               $timezone='America/New_York', $rollup=null,
                               $limit=100, $unitReferenceTs='now')
    {
        $params = array('link' => $link, 'unit' => $unit, 'limit' => $limit,
                        'timezone' => $timezone,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/link/shares', $params);
    }

    /**
     * Return or update information about a user.
     *
     * If called with no arguments, will return information about the
     * authenticated user.
     *
     * The $fullName argument is only valid for the authenticated user and will
     * set the user's full name property.
     *
     * @param string $login (Optional)
     * @param string $fullName (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/user_info.html#v3_user_info
     */
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

    /**
     * Returns entries from a user's link history in reverse chronological
     * order.
     *
     * @param string $link (Optional)
     * @param int $limit (Optional)
     * @param int $offset (Optional)
     * @param int $createdBefore (Optional)
     * @param int $createdAfter (Optional)
     * @param int $modifiedAfter (Optional)
     * @param bool $expandClientId (Optional)
     * @param string $archived (Optional) "on", "off" or "both".
     * @param string $private (Optional) "on", "off" or "both".
     * @param string $user (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/user_info.html#v3_user_link_history
     */
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

    /**
     * Returns entries from a user's network history in reverse chronological
     * order.
     *
     * @param int $offset (Optional)
     * @param bool $expandClientId (Optional)
     * @param int $limit (Optional)
     * @param bool $expandUser (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/user_info.html#v3_user_network_history
     */
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

    /**
     * Returns a list of tracking domains a user has configured.
     *
     * @return array
     *
     * @see http://dev.bitly.com/user_info.html#v3_user_tracking_domain_list
     */
    public function userTrackingDomainList()
    {
        $result = $this->call('v3/user/tracking_domain_list');
        return $result['tracking_domains'];
    }

    /**
     * Returns the aggregate number of clicks on all the authenticated user's
     * Bitly links.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_clicks
     */
    public function userClicks($unit='day', $units=null,
                               $timezone='America/New_York', $rollup=null,
                               $limit=100, $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/clicks', $params);
    }

    /**
     * Returns aggregate metrics about the countries referring clicks to all
     * the authenticated user's Bitly links.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_countries
     */
    public function userCountries($unit='day', $units=null,
                                  $timezone='America/New_York', $rollup=null,
                                  $limit=100, $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/countries', $params);
    }

    /**
     * Returns the authenticated user's most-clicked Bitly links.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_popular_links
     */
    public function userPopularLinks($unit='day', $units=null,
                                  $timezone='America/New_York', $limit=100,
                                  $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        return $this->call('v3/user/popular_links', $params);
    }

    /**
     * Returns aggregate metrics about the pages referring click traffic to all
     * of the authenticated user's Bitly links.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_referrers
     */
    public function userReferrers($unit='day', $units=null,
                                  $timezone='America/New_York', $rollup=null,
                                  $limit=100, $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/referrers', $params);
    }

    /**
     * Returns aggregate metrics about the domains referring click traffic to
     * all of the authenticated user's Bitly links.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_referring_domains
     */
    public function userReferringDomains($unit='day', $units=null,
                                         $timezone='America/New_York',
                                         $rollup=null, $limit=100,
                                         $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/referring_domains', $params);
    }

    /**
     * Returns the number of shares by the authenticated user in a given time
     * period.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_share_counts
     */
    public function userShareCounts($unit='day', $units=null,
                                    $timezone='America/New_York', $rollup=null,
                                    $limit=100, $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/share_counts', $params);
    }

    /**
     * Returns the number of shares by the authenticated user, broken down by
     * share type.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_share_counts_by_share_type
     */
    public function userShareCountsByShareType(
        $unit='day', $units=null, $timezone='America/New_York', $rollup=null,
        $limit=100, $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/share_counts_by_share_type', $params);
    }

    /**
     * Returns the number of links shortened in a given time period by the
     * authenticated user.
     *
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/link_metrics.html#v3_user_shorten_counts
     */
    public function userShortenCounts($unit='day', $units=null,
                                      $timezone='America/New_York',
                                      $rollup=null, $limit=100,
                                      $unitReferenceTs='now')
    {
        $params = array('unit' => $unit, 'timezone' => $timezone,
                        'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        return $this->call('v3/user/shorten_counts', $params);
    }

    /**
     * Archive a bundle for the authenticate user.
     *
     * Only the bundle's owner is allowed to archive it.
     *
     * @param string $bundleLink The URL of the bundle to archive.
     *
     * @return bool
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_archive
     */
    public function bundleArchive($bundleLink)
    {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/archive', $params, false, true);
        return ($result === 'OK') ? true : false;
    }

    /**
     * Returns a list of public bundles created by a user.
     *
     * @param string $user The user to get bundles for.
     * @param bool $expandUser (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_bundles_by_user
     */
    public function bundleBundlesByUser($user, $expandUser=null)
    {
        $params = array('user' => $user);
        if ($expandUser !== null) {
            $params['expand_user'] = $expandUser;
        }
        $result = $this->call('v3/bundle/bundles_by_user', $params);
        return $result['bundles'];
    }

    /**
     * Clone a bundle for the authenticate user.
     *
     * @param string $bundleLink URL of the bundle to clone.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_clone
     */
    public function bundleClone($bundleLink)
    {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/clone', $params);
        return $result['bundle'];
    }

    /**
     * Add a collaborator to a bundle.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $collaborator Bitly login or email address to add.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_collaborator_add
     */
    public function bundleCollaboratorAdd($bundleLink, $collaborator)
    {
        $params = array('bundle_link' => $bundleLink,
                        'collaborator' => $collaborator);
        $result = $this->call('v3/bundle/collaborator_add', $params);
        return $result['bundle'];
    }

    /**
     * Remove a collaborator from a bundle.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $collaborator Bitly login of the collaborator to remove.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_collaborator_remove
     */
    public function bundleCollaboratorRemove($bundleLink, $collaborator)
    {
        $params = array('bundle_link' => $bundleLink,
                        'collaborator' => $collaborator);
        $result = $this->call('v3/bundle/collaborator_remove', $params);
        return $result['bundle'];
    }

    /**
     * Returns information about a bundle and its contents.
     *
     * @param string $bundleLink URL of the bundle.
     * @param bool $expandUser (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_contents
     */
    public function bundleContents($bundleLink)
    {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/contents', $params);
        return $result['bundle'];
    }

    /**
     * Create a bundle for the authenticate user.
     *
     * @param bool $private (Optional)
     * @param string $title (Optional)
     * @param string $description (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_create
     */
    public function bundleCreate($private=null, $title=null, $description=null)
    {
        $params = array();
        if ($private !== null) {
            $params['private'] = $private;
        }
        if ($title !== null) {
            $params['title'] = $title;
        }
        if ($description !== null) {
            $params['description'] = $description;
        }
        $result = $this->call('v3/bundle/create', $params);
        return $result['bundle'];
    }

    /**
     * Edit a bundle for the authenticated user.
     *
     * For optional string values, "null" means "don't change," while the empty
     * string will clear the value. For all other optional values, "null" means
     * "no change".
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $title (Optional)
     * @param string $description (Optional)
     * @param bool $private (Optional)
     * @param bool $preview (Optional)
     * @param string $ogImage (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_edit
     */
    public function bundleEdit($bundleLink, $title=null, $description=null,
                               $private=null, $preview=null, $ogImage=null)
    {
        $params = array('bundle_link' => $bundleLink);
        $keys = array();
        if ($title !== null) {
            $params['title'] = $title;
            $keys[] = 'title';
        }
        if ($description !== null) {
            $params['description'] = $description;
            $keys[] = 'description';
        }
        if ($private !== null) {
            $params['private'] = $private;
            $keys[] = 'private';
        }
        if ($preview !== null) {
            $params['preview'] = $preview;
            $keys[] = 'preview';
        }
        if ($ogImage !== null) {
            $params['og_image'] = $ogImage;
            $keys[] = 'og_image';
        }
        $params['edit'] = implode(',', $keys);
        $result = $this->call('v3/bundle/edit', $params);
        return $result['bundle'];
    }

    /**
     * Add a link to a bundle.
     *
     * Links are automatically added to the top (position 0) of the bundle.
     *
     * @param string $bundleLink URL of the bundle
     * @param string $link A Bitly link or long URL.
     * @param string $title (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_add
     */
    public function bundleLinkAdd($bundleLink, $link, $title=null)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link);
        if ($title !== null) {
            $params['title'] = $title;
        }
        $result = $this->call('v3/bundle/link_add', $params);
        return $result['bundle'];
    }

    /**
     * Add a comment to a bundle item.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $link Bitly link in the bundle.
     * @param string $comment The comment, must be <= 512 characters.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_comment_add
     */
    public function bundleLinkCommentAdd($bundleLink, $link, $comment)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'comment' => $comment);
        $result = $this->call('v3/bundle/link_comment_add', $params);
        return $result['bundle'];
    }

    /**
     * Edit a comment on a bundle item.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $link Bitly link in the bundle.
     * @param int $commentId ID of the comment to edit.
     * @param string $comment The edited comment, must be <= 512 characters.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_comment_edit
     */
    public function bundleLinkCommentEdit($bundleLink, $link, $commentId,
                                          $comment)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'comment_id' => $commentId, 'comment' => $comment);
        $result = $this->call('v3/bundle/link_comment_edit', $params);
        return $result['bundle'];
    }

    /**
     * Remove a comment from a bundle item.
     *
     * Only the original commenter and the bundle owner can remove comments.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $link Bitly link in the bundle.
     * @param int $commentId ID of the comment to remove.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_comment_remove
     */
    public function bundleLinkCommentRemove($bundleLink, $link, $commentId)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'comment_id' => $commentId);
        $result = $this->call('v3/bundle/link_comment_remove', $params);
        return $result['bundle'];
    }

    /**
     * Edit a bundle item.
     *
     * For the optional parameters, a value of "null" means "no change."
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $link Bitly link in the bundle.
     * @param string $title (Optional) The new title.
     * @param bool $preview (Optional) Display preview content.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_edit
     */
    public function bundleLinkEdit($bundleLink, $link, $title=null,
                                   $preview=null)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link);
        $keys = array();
        if ($title !== null) {
            $params['title'] = $title;
            $keys[] = 'title';
        }
        if ($preview !== null) {
            $params['preview'] = $preview;
            $keys[] = 'preview';
        }
        $result = $this->call('v3/bundle/link_edit', $params);
        return $result['bundle'];
    }

    /**
     * Remove a link from a bundle.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $link Bitly link in the bundle.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_remove
     */
    public function bundleLinkRemove($bundleLink, $link)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link);
        $result = $this->call('v3/bundle/link_remove', $params);
        return $result['bundle'];
    }

    /**
     * Reorder a link in a bundle.
     *
     * For $displayOrder, a value of -1 moves the link to the end of the
     * bundle.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $link Bitly link in the bundle.
     * @param int $displayOrder New position in the bundle.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_link_reorder
     */
    public function bundleLinkReorder($bundleLink, $link, $displayOrder)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'display_order' => $displayOrder);
        $result = $this->call('v3/bundle/link_reorder', $params);
        return $result['bundle'];
    }

    // TODO: How?
    //public function bundleReorder($bundleLink, Array $links) {}

    /**
     * Remove a pending/invited collaborator from a bundle.
     *
     * @param string $bundleLink URL of the bundle.
     * @param string $collaborator Bitly login or email address.
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_pending_collaborator_remove
     */
    public function bundlePendingCollaboratorRemove($bundleLink,
                                                    $collaborator)
    {
        $params = array('bundle_link' => $bundleLink,
                        'collaborator' => $collaborator);
        $result = $this->call('v3/bundle/pending_collaborator_remove',
                              $params);
        return $result['bundle'];
    }

    /**
     * Get the number of views for a bundle.
     *
     * @param string $bundleLink URL of the bundle.
     *
     * @return int
     *
     * @see http://dev.bitly.com/bundles.html#v3_bundle_view_count
     */
    public function bundleViewCount($bundleLink) {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/view_count', $params);
        return $result['view_count'];
    }

    /**
     * Returns all the bundles this user has access to.
     *
     * @param bool $expandUser (Optional)
     *
     * @return array
     *
     * @see http://dev.bitly.com/bundles.html#v3_user_bundle_history
     */
    public function userBundleHistory($expandUser=null)
    {
        $params = array();
        if ($expandUser !== null) {
            $params['expand_user'] = $expandUser;
        }
        $result = $this->call('v3/user/bundle_history', $params);
        return $result['bundles'];
    }

    /**
     * Query whether a given domain is a valid Bitly pro domain.
     *
     * @param string $domain A short domain (e.g. "nyti.ms").
     *
     * @return bool
     *
     * @see http://dev.bitly.com/domains.html#v3_bitly_pro_domain
     */
    public function bitlyProDomain($domain)
    {
        $params = array('domain' => $domain);
        $result = $this->call('v3/bitly_pro_domain', $params);
        return ($result['bitly_pro_domain'] == 1) ? true : false;
    }

    /**
     * Returns the number of clicks on Bitly links pointing to the
     * specified tracking domains.
     *
     * @param string $domain A tracking domain.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return int
     *
     * @see http://dev.bitly.com/domains.html#v3_user_tracking_domain_clicks
     */
    public function userTrackingDomainClicks($domain, $unit='day', $units=null,
                                             $timezone='America/New_York',
                                             $rollup=null, $limit=100,
                                             $unitReferenceTs='now')
    {
        $params = array('domain' => $domain, 'unit' => $unit,
                        'timezone' => $timezone, 'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        $result = $this->call('v3/user/tracking_domain_clicks', $params);
        return $result['tracking_domain_clicks'];
    }

    /**
     * Returns the number of links pointing to a specified tracking
     * domain shortened in a given time period by all Bitly users.
     *
     * @param string $domain A tracking domain.
     * @param string $unit (Optional)
     * @param int $units (Optional)
     * @param string|int $timezone (Optional)
     * @param bool $rollup (Optional)
     * @param int $limit (Optional)
     * @param string $unitReferenceTs (Optional)
     *
     * @return int
     *
     * @see http://dev.bitly.com/domains.html#v3_user_tracking_domain_shorten_counts
     */
    public function userTrackingDomainShortenCounts(
        $domain, $unit='day', $units=null, $timezone='America/New_York',
        $rollup=null, $limit=100, $unitReferenceTs='now')
    {
        $params = array('domain' => $domain, 'unit' => $unit,
                        'timezone' => $timezone, 'limit' => $limit,
                        'unit_reference_ts' => $unitReferenceTs);
        if ($units !== null) {
            $params['units'] = $units;
        }
        if ($rollup !== null) {
            $params['rollup'] = $rollup;
        }
        $result = $this->call('v3/user/tracking_domain_shorten_counts',
                              $params);
        return $result['tracking_domain_shorten_counts'];
    }

    public function disableIPv6()
    {
        $this->isIPv6Disabled = true;
    }

    /**
     * Execute a query against the Bitly API and return decoded results.
     *
     * @internal
     *
     * @param string $endpoint API endpoint to query.
     * @param array $params (Optional) Query parameters.
     * @param bool $post Do an HTTP POST instead of GET.
     * @param bool $json Attempt to decode response as JSON.
     *
     * @return mixed
     */
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
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

        if ($this->isIPv6Disabled) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            $query = build_query($params);
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
