<?php
//Declare the namespace for the controller
namespace Drupal\inbox\Controller;

//Include all the namespaces we will be using in this class
//Drupal core controller
use Drupal\Core;
//The class extends the base controller
use Drupal\Core\Controller\ControllerBase;
//Include exceptions for exception handling
use SebastianBergmann\Exporter\Exception;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ProcessmakerController
 * @package Drupal\inbox\Controller
 * Extends the base controller
 * Core class for the ProcessMaker module
 */
class ProcessmakerController extends ControllerBase{

    /**
     * @var array
     * Holds the token information returned from the rest api: access token, refresh token and expires in
     */
    protected $token = array();
    /**
     * @var string
     * Holds the username to log into ProcessMaker
     */
    protected $username;
    /**
     * @var string
     * Holds the password to log into ProcessMaker
     */
    protected $password;
    /**
     * @var string
     * Holds the ProcessMaker URL
     */
    protected $url;
    /**
     * @var string
     * Holds the ProcessMaker port to connect on
     */
    protected $port;
    /**
     * @var string
     * Holds the ProcessMaker workspace to log into
     */
    protected $workspace;
    /**
     * @var string
     * Holds the redirect URL for the oauth app, however this isn't used in this module, it is redundant
     */
    protected $redirect_url;
    /**
     * @var string
     * Holds the client_id from the oauth app
     */
    protected $client_id;
    /**
     * @var string
     * Holds the client_secret from the oauth app
     */
    protected $client_secret;
    /**
     * @var string
     * Holds the client_scope of the oauth app
     */
    protected $client_scope;
    /**
     * @var string
     * Holds the Application UID for the case
     */
    public $appuid;
    /**
     * @var string
     * Holds the current Process UID
     */
    public $prouid;
    /**
     * @var string
     * Holds the current Task UID
     */
    public $taskuid;
    /**
     * @var string
     * Holds the current Delegation Index - This is the position in the workflow that the current task is in
     */
    public $delindex;
    /**
     * @var string
     * Holds the current Applications Case Number / App Number
     */
    public $appnumber;

    /**
     * Constructor function for the class
     */
    function __construct(){
        //Here we get all the information from the User Profile and assign it to the classes properties
        $this->username = $this->currentUser()->getAccount()->processmaker_username;
        $this->password = $this->currentUser()->getAccount()->processmaker_password;
        $this->url = $this->currentUser()->getAccount()->processmaker_url;
        $this->port = $this->currentUser()->getAccount()->processmaker_port;
        $this->workspace = $this->currentUser()->getAccount()->processmaker_workspace;
        $this->redirect_url = $this->currentUser()->getAccount()->processmaker_redirect_url;
        $this->client_id = $this->currentUser()->getAccount()->processmaker_client_id;
        $this->client_secret = $this->currentUser()->getAccount()->processmaker_client_secret;
        $this->client_scope = $this->currentUser()->getAccount()->processmaker_api_scope;
        //For development, keep uncommented for clearing the cache
        //For production, keep commented for increased performance
        //drupal_flush_all_caches();
    }

    /**
     * @return bool
     * This function authenticates the user and checks if they are authorized to access the ProcessMaker REST API
     */
    public function isAuthorized(){
        //Assign the response of the authorize function to the class property token
        $this->token = $this->oauth_authorize();
        //If there is no token, return false so that we know that the user is not authorized
        if( ! $this->token['access_token'] ){
            return false;
        }else{
            //If there is an access token, return true so that we know that the user is authorized
            return true;
        }

    }

    /**
     * @return array
     * This function makes the call to the ProcessMaker api to authenticate the user via oauth 2.0
     */
    public function oauth_authorize(){

        try {
            //If the token has expired, use the refresh token to get a new one
            if (isset($_SESSION['pm_token']['expires_in']) && $_SESSION['pm_token']['expires_in'] < time()) {
                //For the query params, we need client_id and client_secret, the scope, grant_type of refresh_token and the actual refresh token
                $query_params = array(
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'scope' => $this->client_scope,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $_SESSION['pm_token']['refresh_token']
                );

            } elseif ( ! isset($_SESSION['pm_token']) ) {
                //This is if there is no token, then we need to get a brand new one
                //We need to send the client_id, client_secret, the scope, grant_type of password, the username and password for logging into ProcessMaker
                $query_params = array(
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'scope' => '*',//$this->client_scope,
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password
                );
            } else {
                //If the access token is still valid, then we don't need to make an extra api call so just return it
                $this->token = $_SESSION['pm_token'];
                return $this->token;
            }
            //Specify the oauth token endpoint
            $endpoint = (strlen($this->port)>0)? $this->url . ':' . $this->port . '/'. $this->workspace .'/oauth2/token' : $this->url . '/'. $this->workspace .'/oauth2/token';
            //Create a new guzzle http client
            $client = \Drupal::httpClient();
            //Create the request as a post request and assign the body the query params
            $request = $client->createRequest('POST', $endpoint, ['body' => $query_params]);
            //$request = $client->post($endpoint, ['body' => $query_params]);
            //Specify the headers: content type as form urlencoded and the authorization as Basic with the client_id from the oauth application
            $request->addHeaders(array('content-type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Basic ' . $this->client_id));
            //Assign the class property token the returned response which holds the token array from the api
            $response = $client->send($request);
            $this->token = $response->json();
            //Assign the session array the access token so that it can also be used throughout the php session and not just within this class
            $_SESSION['pm_token']['access_token'] = (isset($this->token['access_token'])) ? $this->token['access_token'] : $_SESSION['pm_token']['access_token'];
            $_SESSION['pm_token']['refresh_token'] = (isset($this->token['refresh_token'])) ? $this->token['refresh_token'] : $_SESSION['pm_token']['refresh_token'];
            $_SESSION['pm_token']['expires_in'] = (isset($this->token['expires_in'])) ? time() + $this->token['expires_in'] : $_SESSION['pm_token']['expires_in'];
            //Return the token array
            return $_SESSION['pm_token'];
        }catch( RequestException $e){
            //If there is an Exception, return it so that the user can display it
            return $e->getMessage();
        }catch (Exception $e) {
            //If there is an Exception, return it so that the user can display it
            return $e->getMessage();
        }
    }

    /**
     * @param $endpoint
     * @param string $method
     * @param string $data
     * @return \GuzzleHttp\Message\ResponseInterface
     * This functions sole purpose is to provide a catcher in case the user changes their login information in their user profile page
     */
    function api_call($endpoint, $method = 'GET', $data=''){
        try {
            //Try to return the api call
            return $this->_api_call_request($endpoint, $method, $data);
        }catch( RequestException $e){
            //If there is an exception, this means that there is a problem with the token information, most likely because the user updated their profile information in the ProcessMaker section
            //Clear the session token
            unset($_SESSION['pm_token']);
            //Redo the authorization
            $this->isAuthorized();
            //Retry the api call
            return $this->_api_call_request($endpoint, $method, $data);

        }
    }

    /**
     * @param $endpoint
     * @param string $method
     * @param string $data
     * @return \GuzzleHttp\Message\ResponseInterface
     * This function is the main api caller
     */
    function _api_call_request($endpoint, $method = 'GET', $data=''){
        //Build the api url
        $api_url = $this->url. ':' . $this->port . '/api/1.0/'.$this->workspace;
        //Create the guzzle http client
        $client = \Drupal::httpClient();
        //Switch for each method type
        //Create the request and add the url and the requested endpoint and also add the data being sent
        switch($method){
            case 'GET':
                $request = $client->createRequest('GET', $api_url.$endpoint);
                break;
            case 'PUT':
                $request = $client->createRequest('PUT', $api_url.$endpoint, ['body' => $data]);
                break;
            case 'POST':
                $request = $client->createRequest('POST', $api_url.$endpoint, ['body' => $data]);
                break;
            case 'DELETE':
                break;
            case 'access_token':
                break;
            case 'refresh_token':
                break;
            default:
                break;
        }
        //Check to see that the request went through and it is not empty
        if(isset($request)){
            //Add the request headers, application json and bearer authorization with the access token
            $request->addHeaders(
                array(
                    'content-type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->token['access_token']
                )
            );
            //Return the response without converting to json because if there is no response, as in the routing case example, that creates an error, so we let the caller request the json obect or array object.
            return $client->send($request);
        }
    }
}