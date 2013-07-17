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
    /* @type string|null The application's OAuth2 client ID. */
    public $clientId = null;

    /* @type string|null The application's OAuth2 client secret. */
    public $clientSecret = null;

    /* @type string|null The user or application's OAuth2 access token. */
    public $accessToken = null;

    /* @type string The API hostname. */
    public $apiUrl = 'https://api-ssl.bitly.com/';

    /* @type string|null The application's user agent. */
    public $userAgent = null;

    /* @type int Timeout for communicating with Bitly. */
    public $timeout = 4;

    /* @type int Timeout for connecting to Bitly. */
    public $connectTimeout = 2;

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
     * @param $link A bitly link, e.g. 'http://bit.ly/1234'.
     * @param $params An array of optional values from above.
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

    public function bundleArchive($bundleLink)
    {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/archive', $params, false, true);
        return ($result === 'OK') ? true : false;
    }

    public function bundleBundlesByUser($user, $expandUser=null)
    {
        $params = array('user' => $user);
        if ($expandUser !== null) {
            $params['expand_user'] = $expandUser;
        }
        $result = $this->call('v3/bundle/bundles_by_user', $params);
        return $result['bundles'];
    }

    public function bundleClone($bundleLink)
    {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/clone', $params);
        return $result['bundle'];
    }

    public function bundleCollaboratorAdd($bundleLink, $collaborator)
    {
        $params = array('bundle_link' => $bundleLink,
                        'collaborator' => $collaborator);
        $result = $this->call('v3/bundle/collaborator_add', $params);
        return $result['bundle'];
    }

    public function bundleCollaboratorRemove($bundleLink, $collaborator)
    {
        $params = array('bundle_link' => $bundleLink,
                        'collaborator' => $collaborator);
        $result = $this->call('v3/bundle/collaborator_remove', $params);
        return $result['bundle'];
    }

    public function bundleContents($bundleLink)
    {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/contents', $params);
        return $result['bundle'];
    }

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

    public function bundleLinkAdd($bundleLink, $link, $title=null)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link);
        if ($title !== null) {
            $params['title'] = $title;
        }
        $result = $this->call('v3/bundle/link_add', $params);
        return $result['bundle'];
    }

    public function bundleLinkCommentAdd($bundleLink, $link, $comment)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'comment' => $comment);
        $result = $this->call('v3/bundle/link_comment_add', $params);
        return $result['bundle'];
    }

    public function bundleLinkCommentEdit($bundleLink, $link, $commentId,
                                          $comment)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'comment_id' => $commentId, 'comment' => $comment);
        $result = $this->call('v3/bundle/link_comment_edit', $params);
        return $result['bundle'];
    }

    public function bundleLinkCommentRemove($bundleLink, $link, $commentId)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'comment_id' => $commentId);
        $result = $this->call('v3/bundle/link_comment_remove', $params);
        return $result['bundle'];
    }

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

    public function bundleLinkRemove($bundleLink, $link)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link);
        $result = $this->call('v3/bundle/link_remove', $params);
        return $result['bundle'];
    }

    public function bundleLinkReorder($bundleLink, $link, $displayOrder)
    {
        $params = array('bundle_link' => $bundleLink, 'link' => $link,
                        'display_order' => $displayOrder);
        $result = $this->call('v3/bundle/link_reorder', $params);
        return $result['bundle'];
    }

    // TODO: How?
    //public function bundleReorder($bundleLink, Array $links) {}

    public function bundleViewCount($bundleLink) {
        $params = array('bundle_link' => $bundleLink);
        $result = $this->call('v3/bundle/view_count', $params);
        return $result;
    }

    public function userBundleHistory($expandUser=null)
    {
        $params = array();
        if ($expandUser !== null) {
            $params['expand_user'] = $expandUser;
        }
        $result = $this->call('v3/user/bundle_history', $params);
        return $result['bundles'];
    }

    public function bitlyProDomain($domain)
    {
        $params = array('domain' => $domain);
        $result = $this->call('v3/bitly_pro_domain', $params);
        return ($result['bitly_pro_domain'] == 1) ? true : false;
    }

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
        return $this->call('v3/user/tracking_domain_clicks', $params);
    }

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
        return $this->call('v3/user/tracking_domain_shorten_counts', $params);
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
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
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
