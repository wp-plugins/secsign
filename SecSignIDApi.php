<?php
    
    define("SCRIPT_REVISION", '$Revision: 1.15 $');
    
    class AuthSession
    {
        /**
         * No State: Used when the session state is undefined. 
         */
        const NOSTATE = 0;
        
        /**
         * Pending: The session is still pending for authentication.
         */
        const PENDING = 1;
        
        /**
         * Expired: The authentication timeout has been exceeded.
         */
        const EXPIRED = 2;
        
        /**
         * Authenticated: The user was successfully authenticated.
         */
        const AUTHENTICATED = 3;
        
        /**
         * Denied: The user denied this session.
         */
        const DENIED = 4;
		
        /**
         * Suspended: The server suspended this session, because another authentication request was received while this session was still pending.
         */
        const SUSPENDED = 5;
        
        /**
         * Canceled: The service has canceled this session.
         */
        const CANCELED = 6;
        
        /**
         * Fetched: The device has already fetched the session, but the session hasn't been authenticated or denied yet.
         */
        const FETCHED = 7;
    
        /**
         * Invalid: This session has become invalid.
         */
        const INVALID = 8;
        
        
        /** 
         * the secsign id the authentication session has been craeted for
         */
        private $secSignID      = NULL;
        
        /**
         * authentication session id
         */
        private $authSessionID   = NULL;
        
        /**
         * the name of the requesting service. this will be shown at the smartphone
         */
        private $requestingServiceName = NULL;
        
        /**
         * the address, a valid url, of the requesting service. this will be shown at the smartphone
         */
        private $requestingServiceAddress = NULL;
        
        /**
         * the request ID is similar to a server side session ID. 
         * it is generated after a authentication session has been created. all other request like dispose, withdraw or to get the auth session state
         * will be rejected if a request id is not specified.
         */
        private $requestID        = NULL;
        
        /**
         * icon data of the so called access pass. the image data needs to be displayed otherwise the user does not know which access apss he needs to choose in order to accept the authentication session.*/
        private $authSessionIconData = NULL;
        
        
        /**
         * Getter for secsign id
         */
        function getSecSignID()
        {
            return $this->secSignID;
        }
        
        /**
         * Getter for auth session id
         */
        function getAuthSessionID()
        {
            return $this->authSessionID;
        }
        
        /**
         * Getter for auth session requesting service
         */
        function getRequestingServiceName()
        {
            return $this->requestingServiceName;
        }
        
        /**
         * Getter for auth session requesting service
         */
        function getRequestingServiceAddress()
        {
            return $this->requestingServiceAddress;
        }
        
        /**
         * Getter for request id
         */
        function getRequestID()
        {
            return $this->requestID;
        }
        
        /**
         * Getter for icon data which needs to be display
         */
        function getIconData()
        {
            return $this->authSessionIconData;
        }
        
        /**
         * method to get string representation of this authentication session object
         */
        function __toString()
        {
            return $this->authSessionID . " (" . $this->secSignID . ", " . $this->requestingServiceAddress . ", icondata=" . $this->authSessionIconData . ")";
        }
        
        /**
         * builds an url parameter string like key1=value1&key2=value2&foo=bar
         */
        function getAuthSessionAsArray()
        {
            return array('secsignid'     => $this->secSignID,
                         'authsessionid' => $this->authSessionID,
                         'servicename'   => $this->requestingServiceName,
                         'serviceaddress'=> $this->requestingServiceAddress,
                         'authsessionicondata'=> $this->authSessionIconData,
                         'requestid'     => $this->requestID);
        }
        
        
        /**
         * Creates/Fills the auth session obejct using the given array. The array must use secsignid, auth session id etc as keys.
         */
        function createAuthSessionFromArray($array)
        {
            if(! isset($array)){
                throw new Exception("Parameter array is NULL.");
            }
            
            if(! is_array($array)){
                throw new Exception("Parameter array is not an array. (array=" . $array . ")");
            }

            // check mandatory parameter
            if(! isset($array['secsignid'])){
                throw new Exception("Parameter array does not contain a value 'secsignid'.");
            }
            if(! isset($array['authsessionid'])){
                throw new Exception("Parameter array does not contain a value 'authsessionid'.");
            }
            if(! isset($array['servicename'])){
                throw new Exception("Parameter array does not contain a value 'servicename'.");
            }
            if(! isset($array['serviceaddress'])){
                throw new Exception("Parameter array does not contain a value 'serviceaddress'.");
            }
            if(! isset($array['requestid'])){
                throw new Exception("Parameter array does not contain a value 'requestid'.");
            }
            
            $this->secSignID                = $array['secsignid'];
            $this->authSessionID            = $array['authsessionid'];
            $this->authSessionIconData      = $array['authsessionicondata'];
            $this->requestingServiceName    = $array['servicename'];
            $this->requestingServiceAddress = $array['serviceaddress'];
            $this->requestID                = $array['requestid'];
        }
    }
 
         
    /**
     * PHP class to connect to a secsign id server. the class will check secsign id server certificate and request for authentication session generation for a given
     * user id which is called secsign id. Each authentication session generation needs a new instance of this class.
     *
     * @version $Id: SecSignIDApi.php,v 1.15 2013-04-29 10:32:13 titus Exp $
     * @author SecCommerce Informationssysteme GmbH, Hamburg
     */
    class SecSignIDApi
    {
        // once created the api can be used to create a single request for a certain specified userid
        private $secSignIDServer     = NULL;
        private $secSignIDServerPort = NULL;
        private $secSignIDServer_fallback = NULL;
        private $secSignIDServerPort_fallback = NULL;
        
        // numeric script version.
        private $scriptVersion  = 0;
        private $referer        = NULL;
        private $logger = NULL;
        
        
        /*
         * Constructor
         */
        function __construct()
        {
            // server/secpki hostname and port
            $this->secSignIDServer     = (string) "https://httpapi.secsign.com";
            $this->secSignIDServerPort = (int) 443;
            $this->secSignIDServer_fallback = (string) "https://httpapi2.secsign.com";
            $this->secSignIDServerPort_fallback = (int) 443;
            
            // script version from cvs revision string
            $firstSpace = strpos(SCRIPT_REVISION, " ");
            $lastSpace = strrpos(SCRIPT_REVISION, " ");
            $this->scriptVersion = trim( substr(SCRIPT_REVISION, $firstSpace, $lastSpace-$firstSpace) );

            $this->referer = __CLASS__ . "_PHP";
        }
        
        /*
         * Destructor
         */
        function __destruct()
        {
            $this->secSignIDServer = NULL;
            $this->secSignIDServerPort   = NULL;
            
            $this->scriptVersion = NULL;            
            $this->logger = NULL;
        }
        
        /*
         * Sets a function which is used as a logger
         */
        function setLogger($logger)
        {
            if($logger != NULL && isset($logger) && is_callable($logger) == TRUE){
                $this->logger = $logger;
            }
        }
        
        /*
         * logs a message if logger instance is not NULL
         */
        private function log($message)
        {
            if($this->logger != NULL){
                $logMessage = __CLASS__ . " (v" . $this->scriptVersion . "): " . $message;
                call_user_func($this->logger, $logMessage);
            }
        }
        
        
        /**
         * Send query to secsign id server to create an authentication session for a certain secsign id. This method returns the authentication session itself.
         */
        function requestAuthSession($secsignid, $servicename, $serviceadress)
        {
            $this->log("Call of function 'requestAuthSession'.");
            
            if(! isset($servicename)){
                $this->log("Parameter \$servicename must not be null.");
                throw new Exception("Parameter \$servicename must not be null.");
            }
            
            if(! isset($serviceadress)){
                $this->log("Parameter \$serviceadress must not be null.");
                throw new Exception("Parameter \$serviceadress must not be null.");
            }
            
            if(! isset($secsignid)){
                $this->log("Parameter \$secsignid must not be null.");
                throw new Exception("Parameter \$secsignid must not be null.");
            }
            
            $requestParameter = array('request' => 'ReqRequestAuthSession',
                                      'secsignid' => $secsignid,
                                      'servicename' => $servicename,
                                      'serviceaddress' => $serviceadress);
                                      
            $requestQuery = http_build_query($this->buildParameterArray($requestParameter, NULL), '', '&');
            
            $response      = $this->send($requestQuery);
            $responseArray = $this->checkResponse($response, TRUE); // will throw an exception in case of an error
            
            $authSession = new AuthSession();
            $authSession->CreateAuthSessionFromArray($responseArray);
            
            return $authSession;
        }
        
        
        /**
         * Gets the authentication session state for a certain secsign id whether the authentication session is still pending or it was accepted or denied.
         */
        function getAuthSessionState($authSession)
        {
            $this->log("Call of function 'getAuthSessionState'.");
            
            if($authSession == NULL || !($authSession instanceof AuthSession)){
                $message = "Parameter \$authSession is not an instance of AuthSession. get_class(\$authSession)=" . get_class($authSession);
                $this->log($message);
                throw new Exception($message);
            }
            
            $requestParameter = array('request' => 'ReqGetAuthSessionState');
            $requestQuery = http_build_query($this->buildParameterArray($requestParameter, $authSession), '', '&');
            
            $response      = $this->send($requestQuery);
            $responseArray = $this->checkResponse($response, TRUE); // will throw an exception in case of an error
            
            return $responseArray['authsessionstate'];
        }
        
        
        /**
         * Cancel the given auth session.
         */
        function cancelAuthSession($authSession)
        {
            $this->log("Call of function 'cancelAuthSession'.");
            
            if($authSession == NULL || !($authSession instanceof AuthSession)){
                $message = "Parameter \$authSession is not an instance of AuthSession. get_class(\$authSession)=" . get_class($authSession);
                $this->log($message);
                throw new Exception($message);
            }      
            
            $requestParameter = array('request' => 'ReqCancelAuthSession');
            
            $requestQuery = http_build_query($this->buildParameterArray($requestParameter, $authSession), '', '&');
            $response     = $this->send($requestQuery);
            
            $responseArray = $this->checkResponse($response, TRUE); // will throw an exception in case of an error
            
            return $responseArray['authsessionstate'];
        }
        
        /**
         * Releases an authentication session if it was accepted and not used any longer
         */
        function releaseAuthSession($authSession)
        {
            $this->log("Call of function 'releaseAuthSession'.");
            
            if($authSession == NULL || !($authSession instanceof AuthSession)){
                $message = "Parameter \$authSession is not an instance of AuthSession. get_class(\$authSession)=" . get_class($authSession);
                $this->log($message);
                throw new Exception($message);
            }      
            
            $requestParameter = array('request' => 'ReqReleaseAuthSession');
            
            $requestQuery = http_build_query($this->buildParameterArray($requestParameter, $authSession), '', '&');
            $response     = $this->send($requestQuery);
            
            $responseArray = $this->checkResponse($response, TRUE); // will throw an exception in case of an error
            
            return $responseArray['authsessionstate'];
        }
        
        
        // private functions
        
        
        /**
         * build an array with all parameters which has to be send to server
         */
        private function buildParameterArray($parameter, $authSession)
        {
            //$mandatoryParams = array('apimethod' => $this->referer, 'scriptversion' => $this->scriptVersion);
            $mandatoryParams = array('apimethod' => $this->referer);
            if(isset($authSession))
            {
                // add auth session data to mandatory parameter array
                $authSessionData = array('secsignid' => $authSession->getSecSignID(),
                                         'authsessionid'  => $authSession->getAuthSessionID(),
                                         'requestid' => $authSession->getRequestID());
                
                $mandatoryParams = array_merge($mandatoryParams, $authSessionData);
            }
            return array_merge($mandatoryParams, $parameter);
        }
        
        
        /**
         * sends given parameters to secsign id server and wait given amount
         * of seconds till the connection is timed out
         */
        function send($parameter, $timeout_in_seconds=15)
        {		
            // create cURL resource
            $ch = $this->getCURLHandle($this->secSignIDServer, $this->secSignIDServerPort, $parameter, $timeout_in_seconds);
            $this->log("curl_init: " . $ch);
            
            // $output contains the output string
            $this->log("cURL curl_exec sent params: " . $parameter);
            $output = curl_exec($ch);
            if ($output === false) 
            {
                $this->log("curl_error: " . curl_error($ch));
            }

            // close curl resource to free up system resources
            $this->log("curl_close: " . $ch);
            curl_close($ch);
            
            // check if output is NULL. in that case the secsign id might not have been reached.
            if($output == NULL)
            {
                $this->log("curl: output is NULL. Server " . $this->secSignIDServer . ":" . $this->secSignIDServerPort . " has not been reached.");
                
                if($this->secSignIDServer_fallback != NULL)
                {
                    $this->log("curl: get new handle from fallback server.");
                    $ch = $this->getCURLHandle($this->secSignIDServer_fallback, $this->secSignIDServerPort_fallback, $parameter, $timeout_in_seconds);
                    $this->log("curl_init: " . $ch . " connecting to " . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
                    
                    // $output contains the output string
                    $output = curl_exec($ch);
                    if($output == NULL)
                    {
                        $this->log("output is NULL. Fallback server " . $this->secSignIDServer_fallback . ":" . $this->secSignIDServerPort_fallback . " has not been reached.");
                        $this->log("curl_error: " . curl_error($ch));
                        throw new Exception("curl_exec error: can't connect to Server - " . curl_error($ch));
                    }
                    
                    // close curl resource to free up system resources
                    $this->log("curl_close: " . $ch);
                    curl_close($ch);
                    
                } 
                else 
                {
                    $this->log("curl: no fallback server has been specified.");
                }
            }
            $this->log("curl_exec response: " . ($output == NULL ? "NULL" : $output));
            return $output;
        }
        
        
        /**
         * checks the secsign id server response string
         */
        private function checkResponse($response, $throwExcIfError)
        {
            if(! isset($response))
            {
                $this->log("Could not connect to host '" . $this->secSignIDServer . ":" . $this->secSignIDServerPort . "'");
                if($throwExcIfError)
                {
                    throw new Exception("Could not connect to server.");
                }
            }
            
            $responseArray = array();
            
            // server send parameter strings like:
            // var1=value1&var2=value2&var3=value3&...
            $valuePairs = explode("&", $response);
            foreach($valuePairs as $pair)
            {
                list($key, $value) = explode("=", $pair, 2);
                $responseArray[$key] = $value;
            }
            
            // check if server send a parameter named 'error'
            if(isset($responseArray['error']))
            {
                $this->log("SecSign ID server sent error. code=" . $responseArray['error'] . " message=" . $responseArray['errormsg']);
                if($throwExcIfError)
                {
                    throw new Exception($responseArray['errormsg'], $responseArray['error']);
                }
            }
            return $responseArray;
        }
        
        
        /*
         * Gets a cURL resource handle.
         */
        private function getCURLHandle($server = NULL, $port = -1, $parameter, $timeout_in_seconds)
        {
            // create cURL resource
            $ch = curl_init();
            
            // set url
            curl_setopt($ch, CURLOPT_URL, $server);
            //curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
            curl_setopt($ch, CURLOPT_PORT, $port);
            //curl_setopt($ch, CURLOPT_SSLVERSION, 3);
            
            //return the transfer as a string
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0); // value 0 will strip header information in response 
            
            // set connection timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_in_seconds);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
            
            // make sure the common name of the certificate's subject matches the server's host name
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            // validate the certificate chain of the server
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            //The CA certificates
            curl_setopt($ch, CURLOPT_CAINFO, realpath(dirname(__FILE__)) .'/curl-ca-bundle.crt');
            
            // add referer
            curl_setopt($ch, CURLOPT_REFERER, $this->referer); 
            
            // add all parameter and change request mode to POST
            curl_setopt($ch, CURLOPT_POST, 2);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameter);
            
            return $ch;
        }
    }
	
?>
