<?php
/**
 * Integration with markguinn/silverstripe-cloudassets, which gives you
 * a LiveLink() method on all files. If it's a cloud file, it will return
 * an eval_php that will pick the base/secure url in a livepub compatible
 * way.
 *
 * @author Mark Guinn <mark@adaircreative.com>
 * @date 02.26.2014
 * @package livepub
 * @subpackage integrations
 */
class LiveCloudAssetsFile extends DataExtension
{
    public function LiveLink()
    {
        if ($this->owner->hasField('CloudStatus') && $this->owner->CloudStatus === 'Live') {
            $http  = $this->owner->getCloudURL(CloudBucket::LINK_HTTP);
            $https = $this->owner->getCloudURL(CloudBucket::LINK_HTTPS);
            return LivePubHelper::eval_php('return empty($_SERVER["HTTPS"]) ? "' . $http . '" : "' . $https . '";');
        } else {
            return $this->owner->Link();
        }
    }
}
