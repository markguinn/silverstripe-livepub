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
	 * Generate the templated content for a PHP script that can serve up the
	 * given piece of content with the given age and expiry.
	 *
	 * @param string $content
	 * @param string $age
	 * @param string $lastModified
	 *
	 * @return string
	 */
	protected function generatePHPCacheFile($content, $age, $lastModified) {
		$template = file_get_contents(dirname(__FILE__) . '/CachedPHPPage.tmpl');

		return str_replace(
			array('**MAX_AGE**', '**LAST_MODIFIED**', '**CONTENT**'),
			array((int)$age, $lastModified, LivePubHelper::get_init_code_and_clear() . $content),
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
	public function publishPages($urls) {
		LivePubHelper::init_pub();
		$r = parent::publishPages($urls);
		LivePubHelper::stop_pub();
		return $r;
	}
}
