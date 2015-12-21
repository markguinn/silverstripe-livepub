<?php
/**
 * Same as FilesystemPublisher but allows use of LivePubHelper stuff.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 04.23.2013
 * @package livepub
 */
class LiveFilesystemPublisher extends FilesystemPublisher
{
    /**
     * @var string - you can change this if for some reason you need to. probably wise not to include
     *               any characters that might get encoded in url or html contexts though
     *               NOTE: setting this to empty will disable CSRF token handling
     */
    private static $security_token_placeholder = 'LIVESECURITYTOKENGOESHERE';


    /**
     * Generate the templated content for a PHP script that can serve up the
     * given piece of content with the given age and expiry.
     *
     * @param string $content
     * @param string $age
     * @param string $lastModified
     * @param string $contentType
     *
     * @return string
     */
    protected function generatePHPCacheFile($content, $age, $lastModified, $contentType)
    {
        $template = file_get_contents(dirname(__FILE__) . '/CachedPHPPage.tmpl');

        $csrfPlaceholder = Config::inst()->get('LiveFilesystemPublisher', 'security_token_placeholder');
        if (empty($csrfPlaceholder)) {
            $csrfPlaceholder = $csrfCode = 'DONT DO ANYTHING';
        } else {
            $csrfCode = '<' . '?php echo $security_token; ?' . '>';
        }

        return str_replace(
            array('**MAX_AGE**', '**LAST_MODIFIED**', '**CONTENT**', '**CONTENT_TYPE**', $csrfPlaceholder),
            array((int)$age, $lastModified, LivePubHelper::get_init_code_and_clear() . $content, $contentType, $csrfCode),
            $template
        );
    }

    /**
     * Wrapper for parent funciton. Switches on and off livepub publishing.
     *
     * @param  array $urls Relative URLs
     * @return array Result, keyed by URL. Keys:
     *               - "statuscode": The HTTP status code
     *               - "redirect": A redirect location (if applicable)
     *               - "path": The filesystem path where the cache has been written
     */
    public function publishPages($urls)
    {
        LivePubHelper::init_pub();
        $r = $this->realPublishPages($urls);
        LivePubHelper::stop_pub();
        return $r;
    }


    /**
     * @return Session
     */
    public function getFreshSession()
    {
        // set up CSRF token stuff
        $placeholder = Config::inst()->get('LiveFilesystemPublisher', 'security_token_placeholder');
        if ($placeholder) {
            $sessionKey = SecurityToken::inst()->getName();

            // This duplicates the SessionToken functionality exactly
            // But only requires the one class from Silverstripe
            LivePubHelper::require_session();
            LivePubHelper::add_init_code('
				function new_security_token() {
					require_once("'.BASE_PATH.'/framework/security/RandomGenerator.php");
					$generator = new RandomGenerator();
					return $_SESSION["'.$sessionKey.'"] = $generator->randomToken("sha1");
				}
				$security_token = isset($_SESSION["'.$sessionKey.'"]) ? $_SESSION["'.$sessionKey.'"] : new_security_token();
			', 'LiveSecurityToken');

            return new Session(array(
                $sessionKey => $placeholder
            ));
        } else {
            return new Session(null);
        }
    }


    /**
     * Uses {@link Director::test()} to perform in-memory HTTP requests
     * on the passed-in URLs.
     *
     * I didn't want to duplicate all this code from FilesystemPublisher but
     * I need to be able to touch the session to swap out the security token
     * stuff.
     *
     * @param  array $urls Relative URLs
     * @return array Result, keyed by URL. Keys:
     *               - "statuscode": The HTTP status code
     *               - "redirect": A redirect location (if applicable)
     *               - "path": The filesystem path where the cache has been written
     */
    public function realPublishPages($urls)
    {
        $result = array();

        // Do we need to map these?
        // Detect a numerically indexed arrays
        if (is_numeric(join('', array_keys($urls)))) {
            $urls = $this->urlsToPaths($urls);
        }

        // This can be quite memory hungry and time-consuming
        // @todo - Make a more memory efficient publisher
        increase_time_limit_to();
        increase_memory_limit_to();

        Config::inst()->nest();

        // Set the appropriate theme for this publication batch.
        // This may have been set explicitly via StaticPublisher::static_publisher_theme,
        // or we can use the last non-null theme.
        $customTheme = Config::inst()->get('FilesystemPublisher', 'static_publisher_theme');
        if ($customTheme) {
            Config::inst()->update('SSViewer', 'theme', $customTheme);
        }

        // Ensure that the theme that is set gets used.
        Config::inst()->update('SSViewer', 'theme_enabled', true);

        $currentBaseURL = Director::baseURL();
        $staticBaseUrl = Config::inst()->get('FilesystemPublisher', 'static_base_url');

        if ($staticBaseUrl) {
            Config::inst()->update('Director', 'alternate_base_url', $staticBaseUrl);
        }

        if ($this->fileExtension == 'php') {
            Config::inst()->update('SSViewer', 'rewrite_hash_links', 'php');
        }

        if (Config::inst()->get('FilesystemPublisher', 'echo_progress')) {
            echo $this->class.": Publishing to " . $staticBaseUrl . "\n";
        }

        $files = array();
        $i = 0;
        $totalURLs = sizeof($urls);

        foreach ($urls as $url => $path) {
            $origUrl = $url;
            $result[$origUrl] = array(
                'statuscode' => null,
                'redirect' => null,
                'path' => null
            );

            if ($staticBaseUrl) {
                Config::inst()->update('Director', 'alternate_base_url', $staticBaseUrl);
            }

            $i++;

            if ($url && !is_string($url)) {
                user_error("Bad url:" . var_export($url, true), E_USER_WARNING);
                continue;
            }

            if (Config::inst()->get('FilesystemPublisher', 'echo_progress')) {
                echo " * Publishing page $i/$totalURLs: $url\n";
                flush();
            }

            Requirements::clear();

            if ($url == "") {
                $url = "/";
            }
            if (Director::is_relative_url($url)) {
                $url = Director::absoluteURL($url);
            }
            $response = Director::test(str_replace('+', ' ', $url), null, $this->getFreshSession());

            if (!$response) {
                continue;
            }

            if ($response) {
                $result[$origUrl]['statuscode'] = $response->getStatusCode();
            }
            Requirements::clear();

            singleton('DataObject')->flushCache();

            // Check for ErrorPages generating output - we want to handle this in a special way below.
            $isErrorPage = false;
            $pageObject = null;
            if ($response && is_object($response) && ((int)$response->getStatusCode())>=400) {
                $obj = $this->owner->getUrlArrayObject()->getObject($url);
                if ($obj && $obj instanceof ErrorPage) {
                    $isErrorPage = true;
                }
            }

            // Skip any responses with a 404 status code unless it's the ErrorPage itself.
            if (!$isErrorPage && is_object($response) && $response->getStatusCode()=='404') {
                continue;
            }

            // Generate file content.
            // PHP file caching will generate a simple script from a template
            if ($this->fileExtension == 'php') {
                if (is_object($response)) {
                    if ($response->getStatusCode() == '301' || $response->getStatusCode() == '302') {
                        $content = $this->generatePHPCacheRedirection($response->getHeader('Location'));
                    } else {
                        $content = $this->generatePHPCacheFile($response->getBody(), HTTP::get_cache_age(), date('Y-m-d H:i:s'), $response->getHeader('Content-Type'));
                    }
                } else {
                    $content = $this->generatePHPCacheFile($response . '', HTTP::get_cache_age(), date('Y-m-d H:i:s'), $response->getHeader('Content-Type'));
                }

                // HTML file caching generally just creates a simple file
            } else {
                if (is_object($response)) {
                    if ($response->getStatusCode() == '301' || $response->getStatusCode() == '302') {
                        $absoluteURL = Director::absoluteURL($response->getHeader('Location'));
                        $result[$origUrl]['redirect'] = $response->getHeader('Location');
                        $content = "<meta http-equiv=\"refresh\" content=\"2; URL=$absoluteURL\">";
                    } else {
                        $content = $response->getBody();
                    }
                } else {
                    $content = $response . '';
                }
            }

            if (Config::inst()->get('FilesystemPublisher', 'include_caching_metadata')) {
                $content = str_replace(
                    '</html>',
                    sprintf("</html>\n\n<!-- %s -->", implode(" ", $this->getMetadata($url))),
                    $content
                );
            }

            if (!$isErrorPage) {
                $files[$origUrl] = array(
                    'Content' => $content,
                    'Folder' => dirname($path).'/',
                    'Filename' => basename($path),
                );
            } else {

                // Generate a static version of the error page with a standardised name, so they can be plugged
                // into catch-all webserver statements such as Apache's ErrorDocument.
                $code = (int)$response->getStatusCode();
                $files[$origUrl] = array(
                    'Content' => $content,
                    'Folder' => dirname($path).'/',
                    'Filename' => "error-$code.html",
                );
            }

            // Add externals
            /*
            $externals = $this->externalReferencesFor($content);
            if($externals) foreach($externals as $external) {
                // Skip absolute URLs
                if(preg_match('/^[a-zA-Z]+:\/\//', $external)) continue;
                // Drop querystring parameters
                $external = strtok($external, '?');

                if(file_exists("../" . $external)) {
                    // Break into folder and filename
                    if(preg_match('/^(.*\/)([^\/]+)$/', $external, $matches)) {
                        $files[$external] = array(
                            "Copy" => "../$external",
                            "Folder" => $matches[1],
                            "Filename" => $matches[2],
                        );

                    } else {
                        user_error("Can't parse external: $external", E_USER_WARNING);
                    }
                } else {
                    $missingFiles[$external] = true;
                }
            }*/
        }

        if (Config::inst()->get('FilesystemPublisher', 'static_base_url')) {
            Config::inst()->update('Director', 'alternate_base_url', $currentBaseURL);
        }

        if ($this->fileExtension == 'php') {
            Config::inst()->update('SSViewer', 'rewrite_hash_links', true);
        }

        $base = BASE_PATH . "/$this->destFolder";

        foreach ($files as $origUrl => $file) {
            Filesystem::makeFolder("$base/$file[Folder]");

            $path = "$base/$file[Folder]$file[Filename]";
            $result[$origUrl]['path'] = $path;

            if (isset($file['Content'])) {
                $fh = fopen($path, "w");
                fwrite($fh, $file['Content']);
                fclose($fh);
            } elseif (isset($file['Copy'])) {
                copy($file['Copy'], $path);
            }
        }

        Config::inst()->unnest();

        return $result;
    }
}
