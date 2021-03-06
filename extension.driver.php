<?php

class Extension_Multilingual extends Extension
{
    private static $languages, $language, $resolved;

    // delegates

    public function getSubscribedDelegates()
    {
        return array(

            array('page'     => '/system/preferences/',
                  'delegate' => 'AddCustomPreferenceFieldsets',
                  'callback' => 'addCustomPreferenceFieldsets'),

            array('page'     => '/frontend/',
                  'delegate' => 'FrontendPrePageResolve',
                  'callback' => 'frontendPrePageResolve'),

            array('page'     => '/frontend/',
                  'delegate' => 'FrontendParamsResolve',
                  'callback' => 'frontendParamsResolve'),

            array('page'     => '/frontend/',
                  'delegate' => 'DataSourcePreExecute',
                  'callback' => 'dataSourcePreExecute'),

            array('page'     => '/frontend/',
                  'delegate' => 'DataSourceEntriesBuilt',
                  'callback' => 'dataSourceEntriesBuilt'),

            array('page'     => '/frontend/',
                  'delegate' => 'DataSourcePostExecute',
                  'callback' => 'dataSourcePostExecute')
        );
    }

    // uninstall

    public function uninstall()
    {
        // remove languages from configuration

        Symphony::Configuration()->remove('multilingual');
        Symphony::Configuration()->write();
    }

    // preferences

    public function addCustomPreferenceFieldsets($context)
    {
        // get languages from configuration

        $languages = Symphony::Configuration()->get('languages', 'multilingual');
        $languages = str_replace(' ', '',   $languages);
        $languages = str_replace(',', ', ', $languages);

        // add settings for language codes

        $group = new XMLElement('fieldset');
        $group->setAttribute('class', 'settings');

        $children['legend'] = new XMLElement('legend', __('Multilingual'));

        $children['label'] = new XMLElement('label', __('Languages'));
        $children['label']->appendChild(Widget::Input('settings[multilingual][languages]', $languages, 'text'));

        $children['help'] = new XMLElement('p');
        $children['help']->setAttribute('class', 'help');
        $children['help']->setValue(__('Comma-separated list of <a href="http://en.wikipedia.org/wiki/ISO_639-1">ISO 639-1</a> language codes.'));

        $group->appendChildArray($children);

        $context['wrapper']->appendChild($group);
    }

    // detect & redirect language

    public function frontendPrePageResolve($context)
    {
        if (!self::$resolved) {

            // get languages from configuration

            if (self::$languages = Symphony::Configuration()->get('languages', 'multilingual')) {

                self::$languages = explode(',', str_replace(' ', '', self::$languages));

                // detect language from path

                if (preg_match('/^\/([a-z]{2})\//', $context['page'], $match)) {

                    // set language from path

                    self::$language = $match[1];

                } else {
                    
                    if (isset($_SESSION['language'])) {
	                    
	                     // detect if session has language set
	                     self::$language = substr($_SESSION['language'], 0, 2);
					} else {
						
						// detect language from browser
						$browserLang = preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);
						$browserLang = reset($lang_parse[0]);
						
						if(in_array("pt-br", reset($lang_parse))){
							$browserLang = "pb";
						}
						if(in_array("es-mx", reset($lang_parse))){
							$browserLang = "mx";
						}

						self::$language = substr($browserLang, 0, 2);
					}                    
                }

                // check if language is supported

                if (!in_array(self::$language, self::$languages)) {

                    // set to default otherwise

                    self::$language = self::$languages[0];
                }

                // redirect root page

                if (!$context['page']) {

                    // header('Location: ' . URL . '/' . self::$language . '/'); exit;
                }
            }

            self::$resolved = true;
        }
    }

    // language detect & parameters

    public function frontendParamsResolve($context)
    {
        if (self::$languages) {

            // set params

            $context['params']['languages'] = self::$languages;
            $context['params']['language']  = self::$language;
        }
    }

    // datasource filtering

    public function dataSourcePreExecute($context)
    {
        // clear preexisting output

        //$context['xml'] = null;

        // check if language preconditions are met

        
    }

    // datasource filtering fallback

    public function dataSourceEntriesBuilt($context)
    {
        // check if language preconditions are met

        
    }

    // datasource output

    public function dataSourcePostExecute($context)
    {
        
    }

    // datasource output entries

    private function findEntries(XMLElement $xml)
    {
        // check if xml has child elements

        if (($elements = $xml->getChildren()) && is_array($elements)) {

            // handle elements

            foreach ($elements as $element_index => $element) {

                // check if element is xml element

                if ($element instanceof XMLElement) {

                    // check if element is entry

                    if ($element->getName() === 'entry') {

                        // process fields

                        $element = $this->processFields($element);

                    } else {

                        // find entries

                        $element = $this->findEntries($element);
                    }

                    // replace element

                    $xml->replaceChildAt($element_index, $element);
                }
            }
        }

        return $xml;
    }

    // datasource output fields

    private function processFields(XMLElement $xml)
    {
        // check if xml has child elements

        if (($elements = $xml->getChildren()) && is_array($elements)) {

            // handle elements

            foreach ($elements as $element_index => $element) {

                // get element handle

                $element_handle = $element->getName();

                // check if element handle is multilingual

                if (preg_match('/-([a-z]{2})$/', $element_handle, $match)) {

                    // check if language is supported

                    if (in_array($match[1], self::$languages)) {

                        // remove language segment from element handle

                        $element_handle = preg_replace('/-' . $match[1] . '$/', '', $element_handle);
                        $element_mode   = $element->getAttribute('mode');

                        // set new name and language

                        $element->setName($element_handle);
                        $element->setAttribute('lang', $match[1]);
                        $element->setAttribute('translated', 'yes');

                        // store element

                        $multilingual_elements[$element_handle . ($element_mode ? ':' . $element_mode : '')][$match[1]] = $element;

                        // remove element

                        $xml->removeChildAt($element_index);
                    }
                }
            }

            // check for stored multilingual elements

            if (is_array($multilingual_elements)) {

                // handle multilingual elements

                foreach ($multilingual_elements as $element_handle => $element) {

                    // handle languages

                    foreach (self::$languages as $language) {

                        // check if element exists for each language

                        if (!isset($element[$language]) || !(str_replace('<![CDATA[]]>', '', trim($element[$language]->getValue())) || $element[$language]->getNumberOfChildren())) {

                            // fallback to default language if missing or empty

                            if (isset($element[self::$languages[0]])) {

                                $element[$language] = clone $element[self::$languages[0]];

                                $element[$language]->setAttribute('lang', $language);
                                $element[$language]->setAttribute('translated', 'no');
                            }
                        }
                    }

                    // readd elements

                    $xml->appendChildArray($element);
                }
            }
        }

        return $xml;
    }
}
