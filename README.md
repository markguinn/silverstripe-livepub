LivePub Module
==============
For Silverstripe Static Publisher
---------------------------------

Adds blocks of raw php to staticpublisher for limited 'live' elements (cookies, sessions, etc).


Requirements
------------
- Silverstripe 3.1+ (may work with 3.0, but hasn't been tested)
- Static Publisher module
- Works with Static Publish Queue module


Features
--------
- Allows CSRF tokens to remain enabled with static published sites
- Insert blocks of live php into published pages (must use php publishing method)
- With careful use, allows things like a "logged in as" area or sections based
  on session or cookie information, without loading any of Silverstripe.

Installation
------------
1. `composer require markguinn/silverstripe-livepub dev-master`
2. Follow normal setup for staticpublisher or staticpublishqueue. Keep in mind
   that you must use php instead of html like so: `Object::add_extension("SiteTree", "LiveFilesystemPublisher('cache/', 'php')");`
3. If you're going to use livepub hooks from templates (a common pattern), add to the LivePubControllerHooks
   extension to Controller. Like so: `Object::add_extension("Controller", "LivePubControllerHooks");`


TODO
----
- Docs
- This could be architected better by removing static LivePubHelper and using
  a singleton pattern with subclasses for "publishing" and "not publishing"


Developer(s)
------------
- Mark Guinn <mark@adaircreative.com>

Contributions welcome by pull request and/or bug report.
Please follow Silverstripe code standards.


License (MIT)
-------------
Copyright (c) 2013 Mark Guinn

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the
Software, and to permit persons to whom the Software is furnished to do so, subject
to the following conditions:

The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.