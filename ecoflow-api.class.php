<?php
    class EcoFlowAPI 
    {
        // See the docs @ https://developer-eu.ecoflow.com/us/document/generalInfo

        // Base URL for the EcoFlow API
        private $apiHost = "api-e.ecoflow.com";

         // API access key and secret key
        private $accessKey;
        private $secretKey;

        /**
         * Constructor to initialize the class with access key and secret key.
         *
         * @param string $accessKey The access key for the EcoFlow API.
         * @param string $secretKey The secret key for the EcoFlow API.
         */
        public function __construct($accessKey, $secretKey) 
        {
            $this->accessKey = $accessKey;
            $this->secretKey = $secretKey;
        }

        /**
         * Generate HMAC SHA256 hash for data with a given key.
         *
         * @param string $data The data to be hashed.
         * @param string $key The key used for hashing.
         * @return string The generated hash.
         */
        private static function getHash( $data, $key ) 
        {
            return hash_hmac('sha256', $data, $key, true);
        }

        /**
         * Flatten a multidimensional array into a single-dimensional array with dot notation for keys.
         *
         * @param array $array The array to be flattened.
         * @param string $prefix The prefix for the keys.
         * @return array The flattened array.
         */
        private static function flattenArray( $array, $prefix='' )
        {
            $result = [];

            foreach ($array as $key => $value) 
            {
                $new_key = $prefix === '' ? $key : $prefix . '.' . $key;

                if (is_array($value)) 
                {
                    $result += static::flattenArray($value, $new_key);
                } else {
                    $result[$new_key] = $value;
                }
            }

            return $result;
        }

        /**
         * Convert an array to a URL-encoded query string.
         *
         * @param array $array The array to be converted.
         * @param string $delimiter The delimiter used to separate key-value pairs.
         * @return string The resulting query string.
         */
        private static function arrayToString( $array, $delimiter='&' )
        {
            ksort($array);
            return http_build_query($array, '', $delimiter);
        }

        /**
         * Generate headers required for the EcoFlow API request.
         *
         * @param array $params Additional parameters to include in the headers.
         * @return array The generated headers.
         */
        private function getHeaders( $params=[] )
        {
            $nonce = rand(100000, 999999);
            $time  = round(microtime(true) * 1000);

            $headers = [
                'accessKey' => $this->accessKey,
                'nonce'     => $nonce,
                'timestamp' => $time,
            ];

            $sign = '';

            if( !empty($params) )
            {
                $params = static::flattenArray( $params );
                $sign .= static::arrayToString( $params ) . '&';
            }

            $sign .= static::arrayToString( $headers );

            $headers['sign'] = bin2hex( static::getHash( $sign, $this->secretKey ) );

            return $headers;
        }

        /**
         * Execute a cURL request to the EcoFlow API.
         *
         * @param string $url The URL to send the request to.
         * @param array $headers The headers to include in the request.
         * @param string $method The HTTP method to use (GET or PUT).
         * @param array|null $body The body of the request, if applicable.
         * @return mixed The response from the API.
         * @throws Exception If there is an error with the cURL request.
         */
        private function curlRequest ( $url, $headers, $method = 'GET', $body = null ) 
        {
            $headers['Content-Type'] = ( $method=='PUT' ? 'application/json;charset=UTF-8' : '' );

            $curl_headers = array_map(
                fn($header, $data) => "$header: $data", 
                array_keys($headers), 
                $headers
            );

            $ch = curl_init('https://' . $this->apiHost . $url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $curl_headers,
            ]);

            if ($method === 'PUT') {
                curl_setopt_array($ch, [
                    CURLOPT_CUSTOMREQUEST => "PUT",
                    CURLOPT_POSTFIELDS    => json_encode($body),
                ]);
            }

            $response = curl_exec($ch);

            if ($response === false) {
                throw new Exception("cURL Error: " . curl_error($ch));
            }

            curl_close($ch);

            return json_decode($response, true);
        }

        /**
         * Make a PUT request to the EcoFlow API.
         *
         * @param string $url The URL to send the request to.
         * @param array $params The parameters to include in the request body.
         * @return mixed The response from the API.
         */
        private function put( $url, $params )
        {
            $headers = $this->getHeaders( $params );
            return $this->curlRequest( $url, $headers, "PUT", $params );
        }

        /**
         * Make a GET request to the EcoFlow API.
         *
         * @param string $url The URL to send the request to.
         * @return mixed The response from the API.
         */
        private function get( $url, $params=[] )
        {
            $headers = $this->getHeaders($params);
            return $this->curlRequest( $url, $headers );
        }

        /**
         * Get the list of devices associated with the account.
         *
         * @return mixed The list of devices.
         */
        public function getDevices ()
        {
            return $this->get('/iot-open/sign/device/list');
        }

        /**
         * Get the data for a specific device by its serial number.
         *
         * @param string $serial The serial number of the device.
         * @return mixed The data for the device.
         */
        public function getDevice ( $serial )
        {
            return $this->get('/iot-open/sign/device/quota/all?sn='.$serial, ['sn' => $serial] );
        }

        /**
         * Check whether a device is online (by serial number)
         * 
         * @param string $serial The serial number of the device.
         * @return mixed True if online, false if not, -1 if device was not found
         */
        public function getDeviceOnline( $serial )
        {
            $devices = $this->getDevices();

            foreach( $devices['data'] AS $device )
            {
                if( $device['sn'] == $serial )
                {
                    return $device['online'] == 1 ? true : false;
                }
            }

            return -1;
        }
        
        /**
         * Set a function for a specific device.
         *
         * @param string $serial The serial number of the device.
         * @param string $cmd_code The command code to set the function.
         * @param array $params The parameters to include in the request.
         * @return mixed The response from the API.
         */
        public function setDeviceFunction ( $serial, $cmd_code='', $params=[] )
        {
            return $this->put( '/iot-open/sign/device/quota', [
                'sn' => $serial,
                'cmdCode' => $cmd_code,
                'params' => $params,
            ]);
        }
    }
