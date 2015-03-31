# Version 3.1.2

## Bugfixes

* Closed [#141](https://github.com/appserver-io/appserver/issues/141) refactored error page rendering

## Features

* None

# Version 3.1.1

## Bugfixes

* Fixed newline behaviour in ssl context

## Features

* None

# Version 3.1.0

## Bugfixes

* None

## Features

* Add LocationModule, refactor AutoIndexModule, CoreModule + VirtualHostModule

# Version 3.0.1

## Bugfixes

* fixed error logging on multiple ssl certificate errors
* fixed relative and absolut cert path configuration

## Features

* None

# Version 3.0.0

## Bugfixes

* None

## Features

* Added sni server certs feature which needs php 5.6. This allowes more than one ssl certificates at the same time on one ip address.

# Version 2.0.1

## Bugfixes

* Server vars REQUEST_URI and X_REQUEST_URI will be url decoded to avoid problems within the modules URI handling

## Features

* None

# Version 2.0.0

## Bugfixes

* None

## Features

* Moved HTTP authentication functionality to appserver-io/http package
* Some minor comment fixes
* Updated build process

# Version 1.0.1

## Bugfixes

* Add missing REDIRECT_URI and REDIRECT_URL environment variables to FastCgiModule

## Features

* None

# Version 1.0.0

## Bugfixes

* None

## Features

* Switched to stable dependencies due to version 1.0.0 release

# Version 0.4.3

## Bugfixes

* Fixes [#492](https://github.com/appserver-io/appserver/issues/492) in [appserver-io/appserver](https://github.com/appserver-io/appserver)

## Features

* None

# Version 0.4.2

## Bugfixes

* None

## Features

* added welcome-page support

# Version 0.4.1

## Bugfixes

* Internal refactoring

## Features

* None

# Version 0.4.0

## Bugfixes

* None

## Features

* Removed obsolete ModuleParserInterface
* Applied new file name and comment conventions

# Version 0.3.9

## Bugfixes

* Fixed wrong checks against file system within rewrite conditions

## Features

* None

# Version 0.3.8

## Bugfixes

* None

## Features

* Added warning logging on invalid configuration for authentication feature

# Version 0.3.7

## Bugfixes

* None

## Features

* Added digest auth type, will fix #182 on appserver repo

# Version 0.3.6

## Bugfixes

* Fixed exception handling in connection handler to map exception code 0 to response code 500 by default

## Features

* None

# Version 0.3.5

## Bugfixes

* Fixed poblem with plain integer values within param values

## Features

* Usage of several backreferences per param value are now possible
* Added MPEventConversion connector which allows for the tracking of unique users within event hit types

# Version 0.3.4

## Bugfixes

* Wrong usage of CURLs POST fields

## Features

* None

# Version 0.3.3

## Bugfixes

* None

## Features

* Added different hit types and extended error handling
* Added GA cookie inclusion for the Client ID

# Version 0.3.2

## Bugfixes

* None

## Features

* Allow for client IP forwarding within the analytics module measurement protocol connector

# Version 0.3.1

## Bugfixes

* Updated dependencies for the webserver.json example configuration

## Features

* Added an analysis module

# Version 0.3.0

## Bugfixes

* None

## Features

* Moved to appserver-io organisation
* Refactored namespaces

# Version 0.2.5

## Bugfixes

* fixed script name server var to be set if found

## Features

* None

# Version 0.2.4

## Bugfixes

* Separated core module logic for populating http requests by given uri

## Features

* None

# Version 0.2.3

## Bugfixes

* Fixed DeflateModule to compress just a list of specific mime-types. (#112)

## Features

* None

# Version 0.2.1

## Bugfixes

* None

## Features

* Refactoring ANT PHPUnit execution process
* Composer integration by optimizing folder structure (move bootstrap.php + phpunit.xml.dist => phpunit.xml)
* Switch to new appserver-io/build build- and deployment environment
