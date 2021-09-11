<?php

namespace Restruct\FormObfuscator {

    /**
     * SilverStripe Form action attribute Obfuscator
     *
     * RequestProcessor filter to automatically encode
     * all form action attributes in outputted HTML.
     * Switches between ASCII & hexadecimal encoding.
     *
     * Usage: Simply extract to your SilverStripe website path
     * and run a ?flush=1
     *
     * License: MIT-style license http://opensource.org/licenses/MIT
     */


    use SilverStripe\Control\Director;
    use SilverStripe\Control\HTTPRequest;
    use SilverStripe\Control\HTTPResponse;
    use SilverStripe\Control\RequestFilter;
    use SilverStripe\Control\Session;
    use SilverStripe\Core\Injector\Injector;
    use SilverStripe\ORM\DataObject;

    class RequestProcessor implements RequestFilter
    {
        /**
         * Filter executed AFTER a request
         * Run output through obfuscateForms filter
         * encoding emails in the $response
         */
        public function postRequest(HTTPRequest $request, HTTPResponse $response)
        {
            $base = preg_quote(Director::baseURL(), '/');
            if ( preg_match('/text\/html/', $response->getHeader('Content-Type'))
                && !preg_match('/^' . $base . '(admin|dev)\//', $request->getVar('url'))
            ) {
                $response->setBody(
                    $this->obfuscateForms($response->getBody())
                );
            }
        }

        /*
         * Obfuscate all matching form actions
         * @param string
         * @return string
         */
        public function obfuscateForms($html)
        {
            $reg = '@action=(")([^"]*)(")@i';
            if ( preg_match_all($reg, $html, $matches) ) {
                for ( $i = 0; $i < count($matches[ 0 ]); $i++ ) {
                    $html = str_replace(
                        $matches[ 0 ][ $i ],
                        'action=' . $matches[ 1 ][ $i ] . $this->encode($matches[ 2 ][ $i ]) . $matches[ 3 ][ $i ],
                        $html
                    );
                }
            }

            return $html;
        }

        /**
         * Obscure form action attribute
         *
         * @param string The action URL/URLSegement
         *
         * @return string The encoded (ASCII & hexadecimal) action attribute value
         */
        protected function encode($originalString)
        {
            $encodedString = '';
            $nowCodeString = '';
            $originalLength = strlen($originalString);
            for ( $i = 0; $i < $originalLength; $i++ ) {
                $encodeMode = ( $i % 2 == 0 ) ? 1 : 2; // Switch encoding odd/even
                switch ( $encodeMode ) {
                    case 1: // Decimal code
                        $nowCodeString = '&#' . ord($originalString[ $i ]) . ';';
                        break;
                    case 2: // Hexadecimal code
                    default:
                        $nowCodeString = '&#x' . dechex(ord($originalString[ $i ])) . ';';
                        break;
                }
                $encodedString .= $nowCodeString;
            }

            return $encodedString;
        }

        /** Needed for implementing RequestFilter */
        public function preRequest(HTTPRequest $request)
        {
        }

    }
}
